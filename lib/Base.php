<?php

/**
 * Copyright (C) 2014-2020 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Base implements LoggerAwareInterface
{
    protected $socket;
    protected $options = [];
    protected $is_closing = false;
    protected $last_opcode = null;
    protected $close_status = null;
    protected $logger;

    protected static $opcodes = [
        'continuation' => 0,
        'text'         => 1,
        'binary'       => 2,
        'close'        => 8,
        'ping'         => 9,
        'pong'         => 10,
    ];

    public function getLastOpcode(): ?string
    {
        return $this->last_opcode;
    }

    public function getCloseStatus(): ?int
    {
        return $this->close_status;
    }

    public function isConnected(): bool
    {
        return $this->socket &&
            (get_resource_type($this->socket) == 'stream' ||
             get_resource_type($this->socket) == 'persistent stream');
    }

    public function setTimeout($timeout): void
    {
        $this->options['timeout'] = $timeout;

        if ($this->isConnected()) {
            stream_set_timeout($this->socket, $timeout);
        }
    }

    public function setFragmentSize($fragment_size): self
    {
        $this->options['fragment_size'] = $fragment_size;
        return $this;
    }

    public function getFragmentSize(): int
    {
        return $this->options['fragment_size'];
    }

    public function setLogger(LoggerInterface $logger = null): void
    {
        $this->logger = $logger ?: new NullLogger();
    }

    public function send($payload, $opcode = 'text', $masked = true): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        if (!in_array($opcode, array_keys(self::$opcodes))) {
            $warning = "Bad opcode '{$opcode}'.  Try 'text' or 'binary'.";
            $this->logger->warning($warning);
            throw new BadOpcodeException($warning);
        }

        $payload_chunks = str_split($payload, $this->options['fragment_size']);
        $frame_opcode = $opcode;

        for ($index = 0; $index < count($payload_chunks); ++$index) {
            $chunk = $payload_chunks[$index];
            $final = $index == count($payload_chunks) - 1;

            $this->sendFragment($final, $chunk, $frame_opcode, $masked);

            // all fragments after the first will be marked a continuation
            $frame_opcode = 'continuation';
        }

        $this->logger->info("Sent '{$opcode}' message");
    }

    protected function sendFragment($final, $payload, $opcode, $masked): void
    {
        // Binary string for header.
        $frame_head_binstr = '';

        // Write FIN, final fragment bit.
        $frame_head_binstr .= (bool) $final ? '1' : '0';

        // RSV 1, 2, & 3 false and unused.
        $frame_head_binstr .= '000';

        // Opcode rest of the byte.
        $frame_head_binstr .= sprintf('%04b', self::$opcodes[$opcode]);

        // Use masking?
        $frame_head_binstr .= $masked ? '1' : '0';

        // 7 bits of payload length...
        $payload_length = strlen($payload);
        if ($payload_length > 65535) {
            $frame_head_binstr .= decbin(127);
            $frame_head_binstr .= sprintf('%064b', $payload_length);
        } elseif ($payload_length > 125) {
            $frame_head_binstr .= decbin(126);
            $frame_head_binstr .= sprintf('%016b', $payload_length);
        } else {
            $frame_head_binstr .= sprintf('%07b', $payload_length);
        }

        $frame = '';

        // Write frame head to frame.
        foreach (str_split($frame_head_binstr, 8) as $binstr) {
            $frame .= chr(bindec($binstr));
        }

        // Handle masking
        if ($masked) {
            // generate a random mask:
            $mask = '';
            for ($i = 0; $i < 4; $i++) {
                $mask .= chr(rand(0, 255));
            }
            $frame .= $mask;
        }

        // Append payload to frame:
        for ($i = 0; $i < $payload_length; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        $this->write($frame);
    }

    public function receive(): string
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $payload = '';
        do {
            $response = $this->receiveFragment();
            $payload .= $response[0];
        } while (!$response[1]);

        $this->logger->info("Received '{$this->last_opcode}' message");
        return $payload;
    }

    protected function receiveFragment(): array
    {
        // Just read the main fragment information first.
        $data = $this->read(2);

        // Is this the final fragment?  // Bit 0 in byte 0
        $final = (bool) (ord($data[0]) & 1 << 7);

        // Should be unused, and must be false…  // Bits 1, 2, & 3
        $rsv1  = (bool) (ord($data[0]) & 1 << 6);
        $rsv2  = (bool) (ord($data[0]) & 1 << 5);
        $rsv3  = (bool) (ord($data[0]) & 1 << 4);

        // Parse opcode
        $opcode_int = ord($data[0]) & 15; // Bits 4-7
        $opcode_ints = array_flip(self::$opcodes);
        if (!array_key_exists($opcode_int, $opcode_ints)) {
            $warning = "Bad opcode in websocket frame: {$opcode_int}";
            $this->logger->warning($warning);
            throw new ConnectionException($warning, ConnectionException::BAD_OPCODE);
        }
        $opcode = $opcode_ints[$opcode_int];

        // Masking?
        $mask = (bool) (ord($data[1]) >> 7);  // Bit 0 in byte 1

        $payload = '';

        // Payload length
        $payload_length = (int) ord($data[1]) & 127; // Bits 1-7 in byte 1
        if ($payload_length > 125) {
            if ($payload_length === 126) {
                $data = $this->read(2); // 126: Payload is a 16-bit unsigned int
            } else {
                $data = $this->read(8); // 127: Payload is a 64-bit unsigned int
            }
            $payload_length = bindec(self::sprintB($data));
        }

        // Get masking key.
        if ($mask) {
            $masking_key = $this->read(4);
        }

        // Get the actual payload, if any (might not be for e.g. close frames.
        if ($payload_length > 0) {
            $data = $this->read($payload_length);

            if ($mask) {
                // Unmask payload.
                for ($i = 0; $i < $payload_length; $i++) {
                    $payload .= ($data[$i] ^ $masking_key[$i % 4]);
                }
            } else {
                $payload = $data;
            }
        }

        // if we received a ping, send a pong and wait for the next message
        if ($opcode === 'ping') {
            $this->logger->debug("Received 'ping', sending 'pong'.");
            $this->send($payload, 'pong', true);
            return [null, false];
        }

        // if we received a pong, wait for the next message
        if ($opcode === 'pong') {
            $this->logger->debug("Received 'pong'.");
            return [null, false];
        }

        // Record the opcode if we are not receiving a continutation fragment
        if ($opcode !== 'continuation') {
            $this->last_opcode = $opcode;
        }

        if ($opcode === 'close') {
            // Get the close status.
            $status_bin = '';
            $status = '';
            if ($payload_length > 0) {
                $status_bin = $payload[0] . $payload[1];
                $status = bindec(sprintf("%08b%08b", ord($payload[0]), ord($payload[1])));
                $this->close_status = $status;
            }
            // Get additional close message-
            if ($payload_length >= 2) {
                $payload = substr($payload, 2);
            }

            $this->logger->debug("Received 'close', status: {$this->close_status}.");

            if ($this->is_closing) {
                $this->is_closing = false; // A close response, all done.
            } else {
                $this->send($status_bin . 'Close acknowledged: ' . $status, 'close', true); // Respond.
            }

            // Close the socket.
            fclose($this->socket);

            // Closing should not return message.
            return [null, true];
        }

        return [$payload, $final];
    }

    /**
     * Tell the socket to close.
     *
     * @param integer $status  http://tools.ietf.org/html/rfc6455#section-7.4
     * @param string  $message A closing message, max 125 bytes.
     */
    public function close($status = 1000, $message = 'ttfn'): void
    {
        if (!$this->isConnected()) {
            return;
        }
        $status_binstr = sprintf('%016b', $status);
        $status_str = '';
        foreach (str_split($status_binstr, 8) as $binstr) {
            $status_str .= chr(bindec($binstr));
        }
        $this->send($status_str . $message, 'close', true);
        $this->logger->debug("Closing with status: {$status_str}.");

        $this->is_closing = true;
        $this->receive(); // Receiving a close frame will close the socket now.
    }

    protected function write($data): void
    {
        $length = strlen($data);
        $written = fwrite($this->socket, $data);
        if ($written === false) {
            $this->throwException("Failed to write {$length} bytes.");
        }
        if ($written < strlen($data)) {
            $this->throwException("Could only write {$written} out of {$length} bytes.");
        }
        $this->logger->debug("Wrote {$written} of {$length} bytes.");
    }

    protected function read($length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $buffer = fread($this->socket, $length - strlen($data));
            if ($buffer === false) {
                $read = strlen($data);
                $this->throwException("Broken frame, read {$read} of stated {$length} bytes.");
            }
            if ($buffer === '') {
                $this->throwException("Empty read; connection dead?");
            }
            $data .= $buffer;
        }
        return $data;
    }

    protected function throwException($message, $code = 0): void
    {
        $meta = $this->isConnected() ? stream_get_meta_data($this->socket) : [];
        $json_meta = json_encode($meta);
        fclose($this->socket);
        if (!empty($meta['timed_out'])) {
            $code = ConnectionException::TIMED_OUT;
            $this->logger->warning("{$message}", (array)$meta);
            throw new TimeoutException("{$message} Stream state: {$json_meta}", $code);
        }
        if (!empty($meta['eof'])) {
            $code = ConnectionException::EOF;
        }
        $this->logger->error("{$message}", (array)$meta);
        throw new ConnectionException("{$message}  Stream state: {$json_meta}", $code);
    }

    /**
     * Helper to convert a binary to a string of '0' and '1'.
     */
    protected static function sprintB($string): string
    {
        $return = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $return .= sprintf("%08b", ord($string[$i]));
        }
        return $return;
    }
}
