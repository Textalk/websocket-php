<?php

namespace WebSocket\Message;

use WebSocket\BadOpcodeException;

class Factory
{
    public function create(string $opcode, string $payload = ''): Message
    {
        switch ($opcode) {
            case 'text':
                return new Text($payload);
            case 'binary':
                return new Binary($payload);
            case 'ping':
                return new Ping($payload);
            case 'pong':
                return new Pong($payload);
            case 'close':
                return new Close($payload);
        }
        throw new BadOpcodeException("Invalid opcode '{$opcode}' provided");
    }
}
