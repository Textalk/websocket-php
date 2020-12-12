<?php

namespace WebSocket\Message;

use DateTime;

abstract class Message
{
    protected $opcode;
    protected $payload;
    protected $timestamp;

    public function __construct(string $payload = '')
    {
        $this->payload = $payload;
        $this->timestamp = new DateTime();
    }

    public function getOpcode(): string
    {
        return $this->opcode;
    }

    public function getLength(): int
    {
        return strlen($this->payload);
    }

    public function getTimestamp(): DateTime
    {
        return $this->timestamp;
    }

    public function getContent(): string
    {
        return $this->payload;
    }

    public function setContent(string $payload = ''): void
    {
        $this->payload = $payload;
    }

    public function hasContent(): bool
    {
        return $this->payload != '';
    }

    public function __toString(): string
    {
        return get_class($this);
    }
}
