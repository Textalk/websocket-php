<?php

namespace WebSocket\Message;

class Close extends Message
{
    protected $opcode = 'close';
}
