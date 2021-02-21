<?php

/**
 * Copyright (C) 2014-2021 Textalk/Abicart and contributors.
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
    protected $connection;
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
        return $this->connection && $this->connection->isConnected();
    }

    public function setTimeout(int $timeout): void
    {
        $this->options['timeout'] = $timeout;
        if ($this->isConnected()) {
            $this->connection->setTimeout($timeout);
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
        //$this->connection->send($payload, $opcode, $masked);

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

            $this->connection->pushFrame([$final, $chunk, $frame_opcode, $masked]);

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
        return $this->isConnected() ? $this->connection->getName() : null;
    }

    /**
     * Get name of remote socket, or null if not connected
     * @return string|null
     */
    public function getPier(): ?string
    {
        return $this->isConnected() ? $this->connection->getPier() : null;
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

    public function receive()
    {
        $filter = $this->options['filter'];
        if (!$this->isConnected()) {
            $this->connect();
        }

        do {
            //$response = $this->connection->pullFrame();
            $response = $this->receiveFragment();
            list ($payload, $final, $opcode) = $response;
            //$this->connection->autoRespond($response, $this->is_closing);

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
        $frame = $this->connection->pullFrame();
        list ($final, $payload, $opcode, $masked) = $frame;
        $payload_length = strlen($payload);

//        $this->close_status = $this->connection->autoRespond($frame, $this->is_closing);
//        $this->is_closing = false;
//        return $frame;

        $this->logger->debug("Read '{opcode}' frame", [
            'opcode' => $opcode,
            'final' => $final,
            'content-length' => strlen($payload),
        ]);

        switch ($opcode) {
            case 'ping':
                // If we received a ping, respond with a pong
                $this->logger->debug("Received 'ping', sending 'pong'.");
                $this->connection->pushFrame([true, $payload, 'pong', $masked]);
                return [$payload, true, $opcode];
            case 'close':
                // If we received close, possibly acknowledge and close connection
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

                $this->logger->debug("[connection] Received 'close', status: {$this->close_status}.");

                if ($this->is_closing) {
                    $this->is_closing = false; // A close response, all done.
                } else {
                    $ack =  "{$status_bin}Close acknowledged: {$status}";
                    $this->connection->pushFrame([true, $ack, 'close', $masked]);
                }

                // Close the socket.
                $this->connection->disconnect();
                $this->connection = null;

                // Closing should not return message.
                return [$payload, true, $opcode];
            default:
                return [$payload, $final, $opcode];
        }
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
        //$this->connection->close($status, $message, $this);


        $status_binstr = sprintf('%016b', $status);
        $status_str = '';
        foreach (str_split($status_binstr, 8) as $binstr) {
            $status_str .= chr(bindec($binstr));
        }
if (!$this->isConnected()) {
            $this->connect();
        }
        $this->connection->pushFrame([true, $status_str . $message, 'close', true]);
//        $this->send($status_str . $message, 'close', true);
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
            $this->connection->disconnect();
        }
        $this->connection = null;
    }
}
