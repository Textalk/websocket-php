<?php

/**
 * Copyright (C) 2014-2020 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

use Psr\Log\{LoggerAwareInterface, LoggerInterface, NullLogger};
use WebSocket\Message\Factory;

class Base implements LoggerAwareInterface
{
    protected $socket;
    protected $options = [];
    protected $is_closing = false;
    protected $last_opcode = null;
    protected $close_status = null;
    protected $logger;
    private $read_buffer;

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

    public function setTimeout(int $timeout): void
    {
        $this->options['timeout'] = $timeout;

        if ($this->isConnected()) {
            stream_set_timeout($this->socket, $timeout);
        }
    }

    public function setFragmentSize(int $fragment_size): self
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

    public function send(string $payload, string $opcode = 'text', bool $masked = true): void
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

        $this->logger->info("Sent '{$opcode}' message", [
            'opcode' => $opcode,
            'content-length' => strlen($payload),
            'frames' => count($payload_chunks),
        ]);
    }

    /**
     * Convenience method to send text message
     * @param string $payload Content as string
     */
    public function text(string $payload): void
    {
        $this->send($payload);
    }

    /**
     * Convenience method to send binary message
     * @param string $payload Content as binary string
     */
    public function binary(string $payload): void
    {
        $this->send($payload, 'binary');
    }

    /**
     * Convenience method to send ping
     * @param string $payload Optional text as string
     */
    public function ping(string $payload = ''): void
    {
        $this->send($payload, 'ping');
    }

    /**
     * Convenience method to send unsolicited pong
     * @param string $payload Optional text as string
     */
    public function pong(string $payload = ''): void
    {
        $this->send($payload, 'pong');
    }

    /**
     * Get name of local socket, or null if not connected
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->isConnected() ? stream_socket_get_name($this->socket, false) : null;
    }

    /**
     * Get name of remote socket, or null if not connected
     * @return string|null
     */
    public function getPier(): ?string
    {
        return $this->isConnected() ? stream_socket_get_name($this->socket, true) : null;
    }

    /**
     * Get string representation of instance
     * @return string String representation
     */
    public function __toString(): string
    {
        return sprintf(
            "%s(%s)",
            get_class($this),
            $this->getName() ?: 'closed'
        );
    }

    /**
     * Receive one message.
     * Will continue reading until read message match filter settings.
     * Return Message instance or string according to settings.
     */
    protected function sendFragment(bool $final, string $payload, string $opcode, bool $masked): void
    {
        $data = '';

        $byte_1 = $final ? 0b10000000 : 0b00000000; // Final fragment marker.
        $byte_1 |= self::$opcodes[$opcode]; // Set opcode.
        $data .= pack('C', $byte_1);

        $byte_2 = $masked ? 0b10000000 : 0b00000000; // Masking bit marker.

        // 7 bits of payload length...
        $payload_length = strlen($payload);
        if ($payload_length > 65535) {
            $data .= pack('C', $byte_2 | 0b01111111);
            $data .= pack('J', $payload_length);
        } elseif ($payload_length > 125) {
            $data .= pack('C', $byte_2 | 0b01111110);
            $data .= pack('n', $payload_length);
        } else {
            $data .= pack('C', $byte_2 | $payload_length);
        }

        // Handle masking
        if ($masked) {
            // generate a random mask:
            $mask = '';
            for ($i = 0; $i < 4; $i++) {
                $mask .= chr(rand(0, 255));
            }
            $data .= $mask;

            // Append payload to frame:
            for ($i = 0; $i < $payload_length; $i++) {
                $data .= $payload[$i] ^ $mask[$i % 4];
            }
        } else {
            $data .= $payload;
        }

        $this->write($data);
        $this->logger->debug("Sent '{$opcode}' frame", [
            'opcode' => $opcode,
            'final' => $final,
            'content-length' => strlen($payload),
        ]);
    }

    public function receive()
    {
        $filter = $this->options['filter'];
        if (!$this->isConnected()) {
            $this->connect();
        }

        do {
            $response = $this->receiveFragment();
            list ($payload, $final, $opcode) = $response;

            // Continuation and factual opcode
            $continuation = ($opcode == 'continuation');
            $payload_opcode = $continuation ? $this->read_buffer['opcode'] : $opcode;

            // Filter frames
            if (!in_array($payload_opcode, $filter)) {
                if ($payload_opcode == 'close') {
                    return null; // Always abort receive on close
                }
                $final = false;
                continue; // Continue reading
            }

            // First continuation frame, create buffer
            if (!$final && !$continuation) {
                $this->read_buffer = ['opcode' => $opcode, 'payload' => $payload, 'frames' => 1];
                continue; // Continue reading
            }

            // Subsequent continuation frames, add to buffer
            if ($continuation) {
                $this->read_buffer['payload'] .= $payload;
                $this->read_buffer['frames']++;
            }
        } while (!$final);

        // Final, return payload
        $frames = 1;
        if ($continuation) {
            $payload = $this->read_buffer['payload'];
            $frames = $this->read_buffer['frames'];
            $this->read_buffer = null;
        }
        $this->logger->info("Received '{opcode}' message", [
            'opcode' => $payload_opcode,
            'content-length' => strlen($payload),
            'frames' => $frames,
        ]);

        $this->last_opcode = $payload_opcode;
        $factory = new Factory();
        return $this->options['return_obj']
            ? $factory->create($payload_opcode, $payload)
            : $payload;
    }

    protected function receiveFragment(): array
    {
        // Read the fragment "header" first, two bytes.
        $data = $this->read(2);
        list ($byte_1, $byte_2) = array_values(unpack('C*', $data));

        $final = (bool)($byte_1 & 0b10000000); // Final fragment marker.
        $rsv = $byte_1 & 0b01110000; // Unused bits, ignore

        // Parse opcode
        $opcode_int = $byte_1 & 0b00001111;
        $opcode_ints = array_flip(self::$opcodes);
        if (!array_key_exists($opcode_int, $opcode_ints)) {
            $warning = "Bad opcode in websocket frame: {$opcode_int}";
            $this->logger->warning($warning);
            throw new ConnectionException($warning, ConnectionException::BAD_OPCODE);
        }
        $opcode = $opcode_ints[$opcode_int];

        // Masking bit
        $mask = (bool)($byte_2 & 0b10000000);

        $payload = '';

        // Payload length
        $payload_length = $byte_2 & 0b01111111;

        if ($payload_length > 125) {
            if ($payload_length === 126) {
                $data = $this->read(2); // 126: Payload is a 16-bit unsigned int
                $payload_length = current(unpack('n', $data));
            } else {
                $data = $this->read(8); // 127: Payload is a 64-bit unsigned int
                $payload_length = current(unpack('J', $data));
            }
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

        $this->logger->debug("Read '{opcode}' frame", [
            'opcode' => $opcode,
            'final' => $final,
            'content-length' => strlen($payload),
        ]);

        // if we received a ping, send a pong and wait for the next message
        if ($opcode === 'ping') {
            $this->logger->debug("Received 'ping', sending 'pong'.");
            $this->send($payload, 'pong', true);
            return [$payload, true, $opcode];
        }

        // if we received a pong, wait for the next message
        if ($opcode === 'pong') {
            $this->logger->debug("Received 'pong'.");
            return [$payload, true, $opcode];
        }

        if ($opcode === 'close') {
            $status_bin = '';
            $status = '';
            // Get the close status.
            $status_bin = '';
            $status = '';
            if ($payload_length > 0) {
                $status_bin = $payload[0] . $payload[1];
                $status = current(unpack('n', $payload));
                $this->close_status = $status;
            }
            // Get additional close message
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
            return [$payload, true, $opcode];
        }

        return [$payload, $final, $opcode];
    }

    /**
     * Tell the socket to close.
     *
     * @param integer $status  http://tools.ietf.org/html/rfc6455#section-7.4
     * @param string  $message A closing message, max 125 bytes.
     */
    public function close(int $status = 1000, string $message = 'ttfn'): void
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

    /**
     * Disconnect from client/server.
     */
    public function disconnect(): void
    {
        if ($this->isConnected()) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    protected function write(string $data): void
    {
        $length = strlen($data);
        $written = @fwrite($this->socket, $data);
        if ($written === false) {
            $this->throwException("Failed to write {$length} bytes.");
        }
        if ($written < strlen($data)) {
            $this->throwException("Could only write {$written} out of {$length} bytes.");
        }
        $this->logger->debug("Wrote {$written} of {$length} bytes.");
    }

    protected function read(string $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $buffer = @fread($this->socket, $length - strlen($data));

            if (!$buffer) {
                $meta = stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out'])) {
                    $message = 'Client read timeout';
                    $this->logger->error($message, $meta);
                    throw new TimeoutException($message, ConnectionException::TIMED_OUT, $meta);
                }
            }
            if ($buffer === false) {
                $read = strlen($data);
                $this->throwException("Broken frame, read {$read} of stated {$length} bytes.");
            }
            if ($buffer === '') {
                $this->throwException("Empty read; connection dead?");
            }
            $data .= $buffer;
            $read = strlen($data);
            $this->logger->debug("Read {$read} of {$length} bytes.");
        }
        return $data;
    }

    protected function throwException(string $message, int $code = 0): void
    {
        $meta = ['closed' => true];
        if ($this->isConnected()) {
            $meta = stream_get_meta_data($this->socket);
            fclose($this->socket);
            $this->socket = null;
        }
        if (!empty($meta['timed_out'])) {
            $this->logger->error($message, $meta);
            throw new TimeoutException($message, ConnectionException::TIMED_OUT, $meta);
        }
        if (!empty($meta['eof'])) {
            $code = ConnectionException::EOF;
        }
        $this->logger->error($message, $meta);
        throw new ConnectionException($message, $code, $meta);
    }
}
