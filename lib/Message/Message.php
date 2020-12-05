<?php

namespace WebSocket\Message;

abstract class Message
{
    protected $opcode;
    protected $payload;

    public function __construct(string $payload = '')
    {
        $this->payload = $payload;
    }

    public function getOpcode(): string
    {
        return $this->opcode;
    }

    public function getLength(): int
    {
        return strlen($this->payload);
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
