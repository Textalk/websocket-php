<?php

/**
 * Copyright (C) 2014-2022 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

use Closure;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait, LoggerInterface, NullLogger};
use Throwable;
use WebSocket\Message\Factory;

class Server implements LoggerAwareInterface
{
    use LoggerAwareTrait; // Provides setLogger(LoggerInterface $logger)
    use OpcodeTrait;

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
    private $options = [];
    private $listen = false;
    private $last_opcode;


    /* ---------- Magic methods ------------------------------------------------------ */

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
     * Get string representation of instance.
     * @return string String representation.
     */
    public function __toString(): string
    {
        return sprintf(
            "%s(%s)",
            get_class($this),
            $this->getName() ?: 'closed'
        );
    }


    /* ---------- Server operations -------------------------------------------------- */

    /**
     * Set server to listen to incoming requests.
     * @param Closure $callback A callback function that will be called when server receives message.
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
     * Accept a single incoming request.
     * Note that this operation will block accepting additional requests.
     * @return bool True if listening.
     * @deprecated Will be removed in future version. Use listen() instead.
     */
    public function accept(): bool
    {
        $this->disconnect();
        return (bool)$this->listening;
    }


    /* ---------- Server option functions -------------------------------------------- */

    /**
     * Get current port.
     * @return int port.
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Set timeout.
     * @param int $timeout Timeout in seconds.
     */
    public function setTimeout(int $timeout): void
    {
        $this->options['timeout'] = $timeout;
        if (!$this->isConnected()) {
            return;
        }
        foreach ($this->connections as $connection) {
            $connection->setTimeout($timeout);
            $connection->setOptions($this->options);
        }
    }

    /**
     * Set fragmentation size.
     * @param int $fragment_size Fragment size in bytes.
     * @return self.
     */
    public function setFragmentSize(int $fragment_size): self
    {
        $this->options['fragment_size'] = $fragment_size;
        foreach ($this->connections as $connection) {
            $connection->setOptions($this->options);
        }
        return $this;
    }

    /**
     * Get fragmentation size.
     * @return int $fragment_size Fragment size in bytes.
     */
    public function getFragmentSize(): int
    {
        return $this->options['fragment_size'];
    }


    /* ---------- Connection broadcast operations ------------------------------------ */

    /**
     * Broadcast text message to all conenctions.
     * @param string $payload Content as string.
     */
    public function text(string $payload): void
    {
        $this->send($payload);
    }

    /**
     * Broadcast binary message to all conenctions.
     * @param string $payload Content as binary string.
     */
    public function binary(string $payload): void
    {
        $this->send($payload, 'binary');
    }

    /**
     * Broadcast ping message to all conenctions.
     * @param string $payload Optional text as string.
     */
    public function ping(string $payload = ''): void
    {
        $this->send($payload, 'ping');
    }

    /**
     * Broadcast pong message to all conenctions.
     * @param string $payload Optional text as string.
     */
    public function pong(string $payload = ''): void
    {
        $this->send($payload, 'pong');
    }

    /**
     * Send message on all connections.
     * @param string $payload Message to send.
     * @param string $opcode Opcode to use, default: 'text'.
     * @param bool $masked If message should be masked default: true.
     */
    public function send(string $payload, string $opcode = 'text', bool $masked = true): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
        if (!in_array($opcode, array_keys(self::$opcodes))) {
            $warning = "Bad opcode '{$opcode}'.  Try 'text' or 'binary'.";
            $this->logger->warning($warning);
            throw new BadOpcodeException($warning);
        }

        $factory = new Factory();
        $message = $factory->create($opcode, $payload);

        foreach ($this->connections as $connection) {
            $connection->pushMessage($message, $masked);
        }
    }

    /**
     * Close all connections.
     * @param int $status Close status, default: 1000.
     * @param string $message Close message, default: 'ttfn'.
     */
    public function close(int $status = 1000, string $message = 'ttfn'): void
    {
        foreach ($this->connections as $connection) {
            if ($connection->isConnected()) {
                $connection->close($status, $message);
            }
        }
    }

    /**
     * Disconnect all connections.
     */
    public function disconnect(): void
    {
        foreach ($this->connections as $connection) {
            if ($connection->isConnected()) {
                $connection->disconnect();
            }
        }
        $this->connections = [];
    }

    /**
     * Receive message from single connection.
     * Note that this operation will block reading and only read from first available connection.
     * @return mixed Message, text or null depending on settings.
     */
    public function receive()
    {
        $filter = $this->options['filter'];
        $return_obj = $this->options['return_obj'];

        if (!$this->isConnected()) {
            $this->connect();
        }
        $connection = current($this->connections);

        while (true) {
            $message = $connection->pullMessage();
            $opcode = $message->getOpcode();
            if (in_array($opcode, $filter)) {
                $this->last_opcode = $opcode;
                $return = $return_obj ? $message : $message->getContent();
                break;
            } elseif ($opcode == 'close') {
                $this->last_opcode = null;
                $return = $return_obj ? $message : null;
                break;
            }
        }
        return $return;
    }


    /* ---------- Connection functions (all deprecated) ------------------------------ */

    /**
     * Get requested path from last connection.
     * @return string Path.
     * @deprecated Will be removed in future version.
     */
    public function getPath(): string
    {
        return $this->request_path;
    }

    /**
     * Get request from last connection.
     * @return array Request.
     * @deprecated Will be removed in future version.
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * Get headers from last connection.
     * @return string|null Headers.
     * @deprecated Will be removed in future version.
     */
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

    /**
     * Get last received opcode.
     * @return string|null Opcode.
     * @deprecated Will be removed in future version. Get opcode from Message instead.
     */
    public function getLastOpcode(): ?string
    {
        return $this->last_opcode;
    }

    /**
     * Get close status from single connection.
     * @return int|null Close status.
     * @deprecated Will be removed in future version. Get close status from Connection instead.
     */
    public function getCloseStatus(): ?int
    {
        return $this->connections ? current($this->connections)->getCloseStatus() : null;
    }

    /**
     * If Server has active connections.
     * @return bool True if active connection.
     * @deprecated Will be removed in future version.
     */
    public function isConnected(): bool
    {
        foreach ($this->connections as $connection) {
            if ($connection->isConnected()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get name of local socket from single connection.
     * @return string|null Name of local socket.
     * @deprecated Will be removed in future version. Get name from Connection instead.
     */
    public function getName(): ?string
    {
        return $this->isConnected() ? current($this->connections)->getName() : null;
    }

    /**
     * Get name of remote socket from single connection.
     * @return string|null Name of remote socket.
     * @deprecated Will be removed in future version. Get peer from Connection instead.
     */
    public function getPeer(): ?string
    {
        return $this->isConnected() ? current($this->connections)->getPeer() : null;
    }

    /**
     * @deprecated Will be removed in future version.
     */
    public function getPier(): ?string
    {
        return $this->getPeer();
    }


    /* ---------- Helper functions --------------------------------------------------- */

    // Connect when read/write operation is performed.
    private function connect(): void
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

        $connection = new Connection($socket, $this->options);
        $connection->setLogger($this->logger);

        if (isset($this->options['timeout'])) {
            $connection->setTimeout($this->options['timeout']);
        }

        $this->logger->info("Client has connected to port {port}", [
            'port' => $this->port,
            'pier' => $connection->getPeer(),
        ]);
        $this->performHandshake($connection);
        $this->connections = ['*' => $connection];
    }

    // Perform upgrade handshake on new connections.
    private function performHandshake(Connection $connection): void
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
