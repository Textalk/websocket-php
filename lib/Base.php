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
    protected $connection;
    protected $options = [];
    protected $last_opcode = null;
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
        return $this->connection ? $this->connection->getCloseStatus() : null;
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
            $this->connection->setOptions($this->options);
        }
    }

    public function setFragmentSize(int $fragment_size): self
    {
        $this->options['fragment_size'] = $fragment_size;
        if ($this->connection) {
            $this->connection->setOptions($this->options);
        }
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

        $factory = new Factory();
        $message = $factory->create($opcode, $payload);
        $this->connection->pushMessage($message, $masked);
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
     * @deprecated Will be removed in future version, use getPeer() instead
     */
    public function getPier(): ?string
    {
        return $this->getPeer();
    }

    /**
     * Get name of remote socket, or null if not connected
     * @return string|null
     */
    public function getPeer(): ?string
    {
        return $this->isConnected() ? $this->connection->getPeer() : null;
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
        $return_obj = $this->options['return_obj'];

        if (!$this->isConnected()) {
            $this->connect();
        }

        while (true) {
            $message = $this->connection->pullMessage();
            $opcode = $message->getOpcode();
            if (in_array($opcode, $filter)) {
                $this->last_opcode = $opcode;
                $return = $return_obj ? $message : $message->getContent();
                break;
            } elseif ($opcode == 'close') {
                $this->last_opcode = null;
                $return = $return_obj ? $message : null;
                break;
            }
        }
        return $return;
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
        $this->connection->close($status, $message);
    }

    /**
     * Disconnect from client/server.
     */
    public function disconnect(): void
    {
        if ($this->isConnected()) {
            $this->connection->disconnect();
        }
    }
}
