<?php

/**
 * Copyright (C) 2014-2021 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

use RuntimeException;

class Connection
{

    protected $stream;

    /* ---------- Construct & Destruct ----------------------------------------------- */

    public function __construct($stream)
    {
        echo "Connection.__construct \n";
        $this->stream = $stream;
    }

    public function __destruct()
    {
        echo "Connection.__destruct \n";
/*
        if ($this->isConnected() && $this->getType() !== 'persistent stream') {
            fclose($this->stream);
        }
*/
    }


    /* ---------- Stream handler methods --------------------------------------------- */

    public function close(): bool
    {
        echo "Connection.close \n";
        return fclose($this->stream);
    }


    /* ---------- Stream state methods ----------------------------------------------- */

    public function isConnected(): bool
    {
        echo "Connection.isConnected \n";
        return $this->stream && in_array($this->getType(), ['stream', 'persistent stream']);
    }

    public function getType(): ?string
    {
        echo "Connection.getType \n";
        return get_resource_type($this->stream);
    }

    /**
     * Get name of local socket, or null if not connected
     * @return string|null
     */
    public function getName(): ?string
    {
        echo "Connection.getName \n";
        return stream_socket_get_name($this->stream, false);
    }

    /**
     * Get name of remote socket, or null if not connected
     * @return string|null
     */
    public function getPier(): ?string
    {
        echo "Connection.getPier \n";
        return stream_socket_get_name($this->stream, true);
    }

    public function getMeta(): array
    {
        echo "Connection.getMeta \n";
        return stream_get_meta_data($this->stream);
    }

    public function tell(): int
    {
        echo "Connection.tell \n";
        $tell = ftell($this->stream);
        if ($tell === false) {
            throw new RuntimeException('Could not resolve stream pointer position');
        }
        return $tell;
    }

    public function eof(): int
    {
        echo "Connection.eof \n";
        return feof($this->stream);
    }

    /* ---------- Stream option methods ---------------------------------------------- */

    public function setTimeout(int $seconds, int $microseconds = 0): bool
    {
        echo "Connection.setTimeout \n";
        return stream_set_timeout($this->stream, $seconds, $microseconds);
    }


    /* ---------- Stream read/write methods ------------------------------------------ */

    public function getLine(int $length, string $ending): string
    {
        echo "Connection.getLine \n";
        $line = stream_get_line($this->stream, $length, $ending);
        if ($line === false) {
            throw new RuntimeException('Could not read from stream');
        }
        return $line;
    }
}
