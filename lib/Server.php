<?php

/**
 * Copyright (C) 2014-2021 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

use Closure;
use Psr\Log\NullLogger;
use Throwable;

class Server extends Base
{
    // Default options
    protected static $default_options = [
      'filter'        => ['text', 'binary'],
      'fragment_size' => 4096,
      'logger'        => null,
      'port'          => 8000,
      'return_obj'    => false,
      'timeout'       => null,
    ];

    protected $port;
    protected $listening;
    protected $request;
    protected $request_path;
    private $connections = [];
    private $listen = false;


    /* ---------- Construct & Destruct ----------------------------------------------- */

    /**
     * @param array $options
     *   Associative array containing:
     *   - filter:        Array of opcodes to handle. Default: ['text', 'binary'].
     *   - fragment_size: Set framgemnt size.  Default: 4096
     *   - logger:        PSR-3 compatible logger.  Default NullLogger.
     *   - port:          Chose port for listening.  Default 8000.
     *   - return_obj:    If receive() function return Message instance.  Default false.
     *   - timeout:       Set the socket timeout in seconds.
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::$default_options, [
            'logger' => new NullLogger(),
        ], $options);
        $this->port = $this->options['port'];
        $this->setLogger($this->options['logger']);

        $error = $errno = $errstr = null;
        set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$error) {
            $this->logger->warning($message, ['severity' => $severity]);
            $error = $message;
        }, E_ALL);

        do {
            $this->listening = stream_socket_server("tcp://0.0.0.0:$this->port", $errno, $errstr);
        } while ($this->listening === false && $this->port++ < 10000);

        restore_error_handler();

        if (!$this->listening) {
            $error = "Could not open listening socket: {$errstr} ({$errno}) {$error}";
            $this->logger->error($error);
            throw new ConnectionException($error, (int)$errno);
        }

        $this->logger->info("Server listening to port {$this->port}");
    }

    /**
     * Disconnect streams on shutdown.
     */
    public function __destruct()
    {
/*
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->disconnect();
        }
        $this->connection = null;
*/
        foreach ($this->connections as $connection) {
            if ($connection->isConnected()) {
                $connection->disconnect();
            }
        }
        $this->connections = [];
    }


    /* ---------- Server operations -------------------------------------------------- */

    /**
     * Set server to listen to incoming requests.
     * @param Closure A callback function that will be called when server receives message.
     *   function (Message $message, Connection $connection = null)
     *   If callback function returns non-null value, the listener will halt and return that value.
     *   Otherwise it will continue listening and propagating messages.
     * @return mixed Returns any non-null value returned by callback function.
     */
    public function listen(Closure $callback)
    {
        $this->listen = true;
        while ($this->listen) {
            // Server accept
            if ($stream = @stream_socket_accept($this->listening, 0)) {
                $peer = stream_socket_get_name($stream, true);
                $this->logger->info("[server] Accepted connection from {$peer}");
                $connection = new Connection($stream, $this->options);
                $connection->setLogger($this->logger);
                if ($this->options['timeout']) {
                    $connection->setTimeout($this->options['timeout']);
                }
                $this->performHandshake($connection);
                $this->connections[$peer] = $connection;
            }

            // Collect streams to listen to
            $streams = array_filter(array_map(function ($connection, $peer) {
                $stream = $connection->getStream();
                if (is_null($stream)) {
                    $this->logger->debug("[server] Remove {$peer} from listener stack");
                    unset($this->connections[$peer]);
                }
                return $stream;
            }, $this->connections, array_keys($this->connections)));

            // Handle incoming
            if (!empty($streams)) {
                $read = $streams;
                $write = [];
                $except = [];
                if (stream_select($read, $write, $except, 0)) {
                    foreach ($read as $stream) {
                        try {
                            $result = null;
                            $peer = stream_socket_get_name($stream, true);
                            if (empty($peer)) {
                                $this->logger->warning("[server] Got detached stream '{$peer}'");
                                continue;
                            }
                            $connection = $this->connections[$peer];
                            $this->logger->debug("[server] Handling {$peer}");
                            $message = $connection->pullMessage();
                            if (!$connection->isConnected()) {
                                unset($this->connections[$peer]);
                                $connection = null;
                            }
                            // Trigger callback according to filter
                            $opcode = $message->getOpcode();
                            if (in_array($opcode, $this->options['filter'])) {
                                $this->last_opcode = $opcode;
                                $result = $callback($message, $connection);
                            }
                            // If callback returns not null, exit loop and return that value
                            if (!is_null($result)) {
                                return $result;
                            }
                        } catch (Throwable $e) {
                            $this->logger->error("[server] Error occured on {$peer}; {$e->getMessage()}");
                        }
                    }
                }
            }
        }
    }

    /**
     * Tell server to stop listening to incoming requests.
     * Active connections are still available when restarting listening.
     */
    public function stop(): void
    {
        $this->listen = false;
    }

    /**
     * Accept an incoming request.
     * Note that this operation will block accepting additional requests.
     * @return bool True if listening
     * @deprecated Will be removed in future version
     */
    public function accept(): bool
    {
        $this->connection = null;
        return (bool)$this->listening;
    }


    /* ---------- Server option functions -------------------------------------------- */

    /**
     * Get current port.
     * @return int port
     */
    public function getPort(): int
    {
        return $this->port;
    }

    // Inherited from Base:
    // - setLogger
    // - setTimeout
    // - setFragmentSize
    // - getFragmentSize


    /* ---------- Connection broadcast operations ------------------------------------ */

    /**
     * Close all connections.
     * @param int Close status, default: 1000
     * @param string Close message, default: 'ttfn'
     */
    public function close(int $status = 1000, string $message = 'ttfn'): void
    {
        foreach ($this->connections as $connection) {
            if ($connection->isConnected()) {
                $connection->close($status, $message);
            }
        }
    }

    // Inherited from Base:
    // - receive
    // - send
    // - text, binary, ping, pong


    /* ---------- Connection functions (all deprecated) ------------------------------ */

    public function getPath(): string
    {
        return $this->request_path;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

    public function getHeader($header): ?string
    {
        foreach ($this->request as $row) {
            if (stripos($row, $header) !== false) {
                list($headername, $headervalue) = explode(":", $row);
                return trim($headervalue);
            }
        }
        return null;
    }

    // Inherited from Base:
    // - getLastOpcode
    // - getCloseStatus
    // - isConnected
    // - disconnect
    // - getName, getPeer, getPier


    /* ---------- Helper functions --------------------------------------------------- */

    // Connect when read/write operation is performed.
    protected function connect(): void
    {
        $error = null;
        set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$error) {
            $this->logger->warning($message, ['severity' => $severity]);
            $error = $message;
        }, E_ALL);

        if (isset($this->options['timeout'])) {
            $socket = stream_socket_accept($this->listening, $this->options['timeout']);
        } else {
            $socket = stream_socket_accept($this->listening);
        }

        restore_error_handler();

        if (!$socket) {
            throw new ConnectionException("Server failed to connect. {$error}");
        }

        $this->connection = new Connection($socket, $this->options);
        $this->connection->setLogger($this->logger);

        if (isset($this->options['timeout'])) {
            $this->connection->setTimeout($this->options['timeout']);
        }

        $this->logger->info("Client has connected to port {port}", [
            'port' => $this->port,
            'pier' => $this->connection->getPeer(),
        ]);
        $this->performHandshake($this->connection);
        $this->connections = ['*' => $this->connection];
    }

    // Perform upgrade handshake on new connections.
    protected function performHandshake(Connection $connection): void
    {
        $request = '';
        do {
            $buffer = $connection->getLine(1024, "\r\n");
            $request .= $buffer . "\n";
            $metadata = $connection->getMeta();
        } while (!$connection->eof() && $metadata['unread_bytes'] > 0);

        if (!preg_match('/GET (.*) HTTP\//mUi', $request, $matches)) {
            $error = "No GET in request: {$request}";
            $this->logger->error($error);
            throw new ConnectionException($error);
        }
        $get_uri = trim($matches[1]);
        $uri_parts = parse_url($get_uri);

        $this->request = explode("\n", $request);
        $this->request_path = $uri_parts['path'];
        /// @todo Get query and fragment as well.

        if (!preg_match('#Sec-WebSocket-Key:\s(.*)$#mUi', $request, $matches)) {
            $error = "Client had no Key in upgrade request: {$request}";
            $this->logger->error($error);
            throw new ConnectionException($error);
        }

        $key = trim($matches[1]);

        /// @todo Validate key length and base 64...
        $response_key = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        $header = "HTTP/1.1 101 Switching Protocols\r\n"
                . "Upgrade: websocket\r\n"
                . "Connection: Upgrade\r\n"
                . "Sec-WebSocket-Accept: $response_key\r\n"
                . "\r\n";

        $connection->write($header);
        $this->logger->debug("Handshake on {$get_uri}");
    }
}
