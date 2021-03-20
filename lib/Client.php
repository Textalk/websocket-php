<?php

/**
 * Copyright (C) 2014-2021 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait, LoggerInterface, NullLogger};
use WebSocket\Message\Factory;

class Client implements LoggerAwareInterface
{
    use LoggerAwareTrait; // provides setLogger(LoggerInterface $logger)
    use OpcodeTrait;

    // Default options
    protected static $default_options = [
      'context'       => null,
      'filter'        => ['text', 'binary'],
      'fragment_size' => 4096,
      'headers'       => null,
      'logger'        => null,
      'origin'        => null, // @deprecated
      'persistent'    => false,
      'return_obj'    => false,
      'timeout'       => 5,
    ];

    private $socket_uri;
    private $connection;
    private $options = [];
    private $listen = false;
    private $last_opcode = null;


    /* ---------- Magic methods ------------------------------------------------------ */

    /**
     * @param string $uri     A ws/wss-URI
     * @param array  $options
     *   Associative array containing:
     *   - context:       Set the stream context. Default: empty context
     *   - timeout:       Set the socket timeout in seconds.  Default: 5
     *   - fragment_size: Set framgemnt size.  Default: 4096
     *   - headers:       Associative array of headers to set/override.
     */
    public function __construct(string $uri, array $options = [])
    {
        $this->socket_uri = $uri;
        $this->options = array_merge(self::$default_options, [
            'logger' => new NullLogger(),
        ], $options);
        $this->setLogger($this->options['logger']);
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


    /* ---------- Client operations -------------------------------------------------- */

    /**
     * Set client to listen to incoming requests.
     * @param Closure $callback A callback function that will be called when client receives message.
     *   function (Message $message, Connection $connection = null)
     *   If callback function returns non-null value, the listener will halt and return that value.
     *   Otherwise it will continue listening and propagating messages.
     * @return mixed Returns any non-null value returned by callback function.
     */
    public function listen(Closure $callback)
    {
        $this->listen = true;
        while ($this->listen) {
            // Connect
            if (!$this->isConnected()) {
                $this->connect();
            }

            // Handle incoming
            $read = $this->connection->getStream();
            $write = [];
            $except = [];
            if (stream_select($read, $write, $except, 0)) {
                foreach ($read as $stream) {
                    try {
                        $result = null;
                        $peer = stream_socket_get_name($stream, true);
                        if (empty($peer)) {
                            $this->logger->warning("[client] Got detached stream '{$peer}'");
                            continue;
                        }
                        $this->logger->debug("[client] Handling {$peer}");
                        $message = $this->connection->pullMessage();
                        if (!$this->connection->isConnected()) {
                            $this->connection = null;
                        }
                        // Trigger callback according to filter
                        $opcode = $message->getOpcode();
                        if (in_array($opcode, $this->options['filter'])) {
                            $this->last_opcode = $opcode;
                            $result = $callback($message, $this->connection);
                        }
                        // If callback returns not null, exit loop and return that value
                        if (!is_null($result)) {
                            return $result;
                        }
                    } catch (Throwable $e) {
                        $this->logger->error("[client] Error occured on {$peer}; {$e->getMessage()}");
                    }
                }
            }
        }
    }

    /**
     * Tell client to stop listening to incoming requests.
     * Active connections are still available when restarting listening.
     */
    public function stop(): void
    {
        $this->listen = false;
    }


    /* ---------- Client option functions -------------------------------------------- */

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
        $this->connection->setTimeout($timeout);
        $this->connection->setOptions($this->options);
    }

    /**
     * Set fragmentation size.
     * @param int $fragment_size Fragment size in bytes.
     * @return self.
     */
    public function setFragmentSize(int $fragment_size): self
    {
        $this->options['fragment_size'] = $fragment_size;
        $this->connection->setOptions($this->options);
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


    /* ---------- Connection operations ---------------------------------------------- */

    /**
     * Send text message.
     * @param string $payload Content as string.
     */
    public function text(string $payload): void
    {
        $this->send($payload);
    }

    /**
     * Send binary message.
     * @param string $payload Content as binary string.
     */
    public function binary(string $payload): void
    {
        $this->send($payload, 'binary');
    }

    /**
     * Send ping.
     * @param string $payload Optional text as string.
     */
    public function ping(string $payload = ''): void
    {
        $this->send($payload, 'ping');
    }

    /**
     * Send unsolicited pong.
     * @param string $payload Optional text as string.
     */
    public function pong(string $payload = ''): void
    {
        $this->send($payload, 'pong');
    }

    /**
     * Send message.
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
        $this->connection->pushMessage($message, $masked);
    }

    /**
     * Tell the socket to close.
     * @param integer $status  http://tools.ietf.org/html/rfc6455#section-7.4
     * @param string  $message A closing message, max 125 bytes.
     */
    public function close(int $status = 1000, string $message = 'ttfn'): void
    {
        if (!$this->isConnected()) {
            return;
        }
        $this->connection->close($status, $message);
    }

    /**
     * Disconnect from server.
     */
    public function disconnect(): void
    {
        if ($this->isConnected()) {
            $this->connection->disconnect();
        }
    }

    /**
     * Receive message.
     * Note that this operation will block reading.
     * @return mixed Message, text or null depending on settings.
     * @deprecated Will be removed in future version. Use listen() instead.
     */
    public function receive()
    {
        $filter = $this->options['filter'];
        $return_obj = $this->options['return_obj'];

        if (!$this->isConnected()) {
            $this->connect();
        }

        while (true) {
            $message = $this->connection->pullMessage();
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


    /* ---------- Connection functions ----------------------------------------------- */

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
     * Get close status on connection.
     * @return int|null Close status.
     */
    public function getCloseStatus(): ?int
    {
        return $this->connection ? $this->connection->getCloseStatus() : null;
    }

    /**
     * If Client has active connection.
     * @return bool True if active connection.
     */
    public function isConnected(): bool
    {
        return $this->connection && $this->connection->isConnected();
    }

    /**
     * Get name of local socket, or null if not connected.
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->isConnected() ? $this->connection->getName() : null;
    }

    /**
     * Get name of remote socket, or null if not connected.
     * @return string|null
     */
    public function getPeer(): ?string
    {
        return $this->isConnected() ? $this->connection->getPeer() : null;
    }

    /**
     * Get name of remote socket, or null if not connected.
     * @return string|null
     * @deprecated Will be removed in future version, use getPeer() instead.
     */
    public function getPier(): ?string
    {
        return $this->getPeer();
    }


    /* ---------- Helper functions --------------------------------------------------- */

    /**
     * Perform WebSocket handshake
     */
    protected function connect(): void
    {
        $this->connection = null;

        $url_parts = parse_url($this->socket_uri);
        if (empty($url_parts) || empty($url_parts['scheme']) || empty($url_parts['host'])) {
            $error = "Invalid url '{$this->socket_uri}' provided.";
            $this->logger->error($error);
            throw new BadUriException($error);
        }
        $scheme    = $url_parts['scheme'];
        $host      = $url_parts['host'];
        $user      = isset($url_parts['user']) ? $url_parts['user'] : '';
        $pass      = isset($url_parts['pass']) ? $url_parts['pass'] : '';
        $port      = isset($url_parts['port']) ? $url_parts['port'] : ($scheme === 'wss' ? 443 : 80);
        $path      = isset($url_parts['path']) ? $url_parts['path'] : '/';
        $query     = isset($url_parts['query'])    ? $url_parts['query'] : '';
        $fragment  = isset($url_parts['fragment']) ? $url_parts['fragment'] : '';

        $path_with_query = $path;
        if (!empty($query)) {
            $path_with_query .= '?' . $query;
        }
        if (!empty($fragment)) {
            $path_with_query .= '#' . $fragment;
        }

        if (!in_array($scheme, ['ws', 'wss'])) {
            $error = "Url should have scheme ws or wss, not '{$scheme}' from URI '{$this->socket_uri}'.";
            $this->logger->error($error);
            throw new BadUriException($error);
        }

        $host_uri = ($scheme === 'wss' ? 'ssl' : 'tcp') . '://' . $host;

        // Set the stream context options if they're already set in the config
        if (isset($this->options['context'])) {
            // Suppress the error since we'll catch it below
            if (@get_resource_type($this->options['context']) === 'stream-context') {
                $context = $this->options['context'];
            } else {
                $error = "Stream context in \$options['context'] isn't a valid context.";
                $this->logger->error($error);
                throw new \InvalidArgumentException($error);
            }
        } else {
            $context = stream_context_create();
        }

        $persistent = $this->options['persistent'] === true;
        $flags = STREAM_CLIENT_CONNECT;
        $flags = $persistent ? $flags | STREAM_CLIENT_PERSISTENT : $flags;

        $error = $errno = $errstr = null;
        set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$error) {
            $this->logger->warning($message, ['severity' => $severity]);
            $error = $message;
        }, E_ALL);

        // Open the socket.
        $socket = stream_socket_client(
            "{$host_uri}:{$port}",
            $errno,
            $errstr,
            $this->options['timeout'],
            $flags,
            $context
        );

        restore_error_handler();

        if (!$socket) {
            $error = "Could not open socket to \"{$host}:{$port}\": {$errstr} ({$errno}) {$error}.";
            $this->logger->error($error);
            throw new ConnectionException($error);
        }

        $this->connection = new Connection($socket, $this->options);
        $this->connection->setLogger($this->logger);

        if (!$this->isConnected()) {
            $error = "Invalid stream on \"{$host}:{$port}\": {$errstr} ({$errno}) {$error}.";
            $this->logger->error($error);
            throw new ConnectionException($error);
        }

        if (!$persistent || $this->connection->tell() == 0) {
            // Set timeout on the stream as well.
            $this->connection->setTimeout($this->options['timeout']);

            // Generate the WebSocket key.
            $key = self::generateKey();

            // Default headers
            $headers = [
                'Host'                  => $host . ":" . $port,
                'User-Agent'            => 'websocket-client-php',
                'Connection'            => 'Upgrade',
                'Upgrade'               => 'websocket',
                'Sec-WebSocket-Key'     => $key,
                'Sec-WebSocket-Version' => '13',
            ];

            // Handle basic authentication.
            if ($user || $pass) {
                $headers['authorization'] = 'Basic ' . base64_encode($user . ':' . $pass);
            }

            // Deprecated way of adding origin (use headers instead).
            if (isset($this->options['origin'])) {
                $headers['origin'] = $this->options['origin'];
            }

            // Add and override with headers from options.
            if (isset($this->options['headers'])) {
                $headers = array_merge($headers, $this->options['headers']);
            }

            $header = "GET " . $path_with_query . " HTTP/1.1\r\n" . implode(
                "\r\n",
                array_map(
                    function ($key, $value) {
                        return "$key: $value";
                    },
                    array_keys($headers),
                    $headers
                )
            ) . "\r\n\r\n";

            // Send headers.
            $this->connection->write($header);

            // Get server response header (terminated with double CR+LF).
            $response = $this->connection->getLine(1024, "\r\n\r\n");

            /// @todo Handle version switching

            $address = "{$scheme}://{$host}{$path_with_query}";

            // Validate response.
            if (!preg_match('#Sec-WebSocket-Accept:\s(.*)$#mUi', $response, $matches)) {
                $error = "Connection to '{$address}' failed: Server sent invalid upgrade response: {$response}";
                $this->logger->error($error);
                throw new ConnectionException($error);
            }

            $keyAccept = trim($matches[1]);
            $expectedResonse
                = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

            if ($keyAccept !== $expectedResonse) {
                $error = 'Server sent bad upgrade response.';
                $this->logger->error($error);
                throw new ConnectionException($error);
            }
        }

        $this->logger->info("Client connected to {$address}");
    }

    /**
     * Generate a random string for WebSocket key.
     * @return string Random string
     */
    protected static function generateKey(): string
    {
        $key = '';
        for ($i = 0; $i < 16; $i++) {
            $key .= chr(rand(33, 126));
        }
        return base64_encode($key);
    }
}
