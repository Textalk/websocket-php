<?php

namespace WebSocket\Message;

class Ping extends Message
{
    protected $opcode = 'ping';
}
