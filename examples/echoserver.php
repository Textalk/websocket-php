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

echo "> Echo server\n";

// Server options specified or random
$options = array_merge([
    'port'          => 8000,
    'timeout'       => 200,
    'filter'        => ['text', 'binary', 'ping', 'pong', 'close'],
], getopt('', ['port:', 'timeout:', 'debug']));

// If debug mode and logger is available
if (isset($options['debug']) && class_exists('WebSocket\EchoLog')) {
    $logger = new EchoLog();
    $options['logger'] = $logger;
    echo "> Using logger\n";
}

// Initiate server
try {
    $server = new Server($options);
} catch (ConnectionException $e) {
    echo "> ERROR: {$e->getMessage()}\n";
    die();
}

echo "> Listening to port {$server->getPort()}\n";

$server->listen(function ($message, $connection = null) use ($server) {
    $content = $message->getContent();
    $opcode = $message->getOpcode();
    $peer = $connection ? $connection->getPeer() : '(closed)';
    echo "> Got '{$content}' [opcode: {$opcode}, peer: {$peer}]\n";

    // Connection closed, can't respond
    if (!$connection) {
        echo "> Connection closed\n";
        return; // Continue listening
    }

    if (in_array($opcode, ['ping', 'pong'])) {
        $connection->text($content);
        echo "< Sent '{$content}' [opcode: text, peer: {$peer}]\n";
        return; // Continue listening
    }

    // Allow certain string to trigger server action
    switch ($content) {
        case 'auth':
            $auth = "{$server->getHeader('Authorization')} - {$content}";
            $connection->text($auth);
            echo "< Sent '{$auth}' [opcode: text, peer: {$peer}]\n";
            break;
        case 'close':
            $connection->close(1000, $content);
            echo "< Sent '{$content}' [opcode: close, peer: {$peer}]\n";
            break;
        case 'exit':
            echo "> Client told me to quit.\n";
            $server->close();
            return true; // Stop listener
        case 'headers':
            $headers = trim(implode("\r\n", $server->getRequest()));
            $connection->text($headers);
            echo "< Sent '{$headers}' [opcode: text, peer: {$peer}]\n";
            break;
        case 'ping':
            $connection->ping($content);
            echo "< Sent '{$content}' [opcode: ping, peer: {$peer}]\n";
            break;
        case 'pong':
            $connection->pong($content);
            echo "< Sent '{$content}' [opcode: pong, peer: {$peer}]\n";
            break;
        case 'stop':
            $server->stop();
            echo "> Client told me to stop listening.\n";
            break;
        default:
            $connection->text($content);
            echo "< Sent '{$content}' [opcode: text, peer: {$peer}]\n";
    }
});
