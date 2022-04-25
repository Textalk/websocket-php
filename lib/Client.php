<?php

/**
 * Copyright (C) 2014-2020 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

class Client extends Base
{
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

    protected $socket_uri;

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
        $this->options = array_merge(self::$default_options, $options);
        $this->socket_uri = $uri;
        $this->setLogger($this->options['logger']);
    }

    public function __destruct()
    {
        if ($this->isConnected() && get_resource_type($this->socket) !== 'persistent stream') {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    /**
     * Perform WebSocket handshake
     */
    protected function connect(): void
    {
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
        $this->socket = stream_socket_client(
            "{$host_uri}:{$port}",
            $errno,
            $errstr,
            $this->options['timeout'],
            $flags,
            $context
        );

        restore_error_handler();

        if (!$this->isConnected()) {
            $error = "Could not open socket to \"{$host}:{$port}\": {$errstr} ({$errno}) {$error}.";
            $this->logger->error($error);
            throw new ConnectionException($error);
        }

        $address = "{$scheme}://{$host}{$path_with_query}";

        if (!$persistent || ftell($this->socket) == 0) {
            // Set timeout on the stream as well.
            stream_set_timeout($this->socket, $this->options['timeout']);

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
            $this->write($header);

            // Get server response header (terminated with double CR+LF).
            $response = '';
            do {
                $buffer = fgets($this->socket, 1024);
                if ($buffer === false) {
                    $meta = stream_get_meta_data($this->socket);
                    $message = 'Client handshake error';
                    $this->logger->error($message, $meta);
                    throw new ConnectionException($message);
                }
                $response .= $buffer;
            } while (substr_count($response, "\r\n\r\n") == 0);

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
     *
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
