<?php

/**
 * This file is constructed to avoid issues with using the provided Client towards a Ratchet server.
 */

require dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use WebSocket\Tests\Chat;

$port = 8000;
$server = null;

while (!$server && $port++ < 8100) {
    try {
        $server = IoServer::factory(new HttpServer(new WsServer(new Chat())), $port);
        echo "$port\n";
    } catch (React\Socket\ConnectionException $e) {
    }
}

$server->run();
