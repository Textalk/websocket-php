<?php

namespace WebSocket\Tests;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface
{
    public function onMessage(ConnectionInterface $from, $message)
    {
        if ($message === 'exit') {
            exit;
        }

        if ($message === 'Dump headers') {
            $from->send($from->WebSocket->request->getRawHeaders());
        } elseif ($auth = $from->WebSocket->request->getHeader('Authorization')) {
            $from->send("$auth - $message");
        } else {
            $from->send($message);
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
    }

    public function onClose(ConnectionInterface $conn)
    {
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
    }
}
