<?php

/**
 * This file is used for the tests, but can also serve as an example of a WebSocket\Server.
 * Run in console: php examples/echoserver.php
 *
 * Console options:
 *  --port <int> : The port to listen to, default 8000
 *  --timeout <int> : Timeout in seconds, default 200 seconds
 *  --debug : Output log data (if logger is available)
 */

namespace WebSocket;

require __DIR__ . '/../vendor/autoload.php';

error_reporting(-1);

echo "> Random server\n";

// Server options specified or random
$options = array_merge([
    'port'          => 8000,
    'timeout'       => 200,
], getopt('', ['port:', 'timeout:', 'debug']));

// If debug mode and logger is available
if (isset($options['debug']) && class_exists('WebSocket\EchoLog')) {
    $logger = new EchoLog();
    $options['logger'] = $logger;
    echo "> Using logger\n";
}

// Setting timeout to 200 seconds to make time for all tests and manual runs.
$server = new Server($options);

echo "> Listening to port {$server->getPort()}\n";

while ($server->accept()) {
    try {
        while (true) {
            $message = $server->receive();
            $opcode = $server->getLastOpcode();
            if ($opcode == 'close') {
                echo "> Closed connection\n";
                continue;
            }
            echo "> Got '{$message}' [opcode: {$opcode}]\n";

            switch ($message) {
                case 'exit':
                    echo "> Client told me to quit.  Bye bye.\n";
                    $server->close();
                    echo "> Close status: {$server->getCloseStatus()}\n";
                    exit;
                case 'headers':
                    $server->send(implode("\r\n", $server->getRequest()));
                    break;
                case 'ping':
                    $server->send($message, 'ping');
                    break;
                case 'auth':
                    $auth = $server->getHeader('Authorization');
                    $server->send("{$auth} - {$message}", $opcode);
                    break;
                default:
                    $server->send($message, $opcode);
            }
        }
    } catch (WebSocket\ConnectionException $e) {
        echo "\n", microtime(true), " Connection died: $e\n";
    }
}
