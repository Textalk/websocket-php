<?php

namespace WebSocket;

class ConnectionException extends Exception
{
    // Native codes in interval 0-106
    public const TIMED_OUT = 1024;
    public const EOF = 1025;
    public const BAD_OPCODE = 1026;
}
