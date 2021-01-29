<?php

namespace WebSocket;

use Throwable;

class ConnectionException extends Exception
{
    // Native codes in interval 0-106
    public const TIMED_OUT = 1024;
    public const EOF = 1025;
    public const BAD_OPCODE = 1026;

    private $data;

    public function __construct(string $message, int $code = 0, array $data = [], Throwable $prev = null)
    {
        parent::__construct($message, $code, $prev);
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
