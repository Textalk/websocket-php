<?php

namespace WebSocket;

class ConnectionException extends Exception
{
    // Native codes in interval 0-106
    public static $TIMED_OUT = 1024;
    public static $EOF = 1025;
    public static $BAD_OPCODE = 1026;
}
