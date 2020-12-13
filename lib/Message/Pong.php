<?php

namespace WebSocket\Message;

class Pong extends Message
{
    protected $opcode = 'pong';
}
