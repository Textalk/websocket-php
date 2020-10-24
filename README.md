# Websocket Client and Server for PHP

[![Build Status](https://travis-ci.org/Textalk/websocket-php.svg?branch=master)](https://travis-ci.org/Textalk/websocket-php)
[![Coverage Status](https://coveralls.io/repos/github/Textalk/websocket-php/badge.svg?branch=master)](https://coveralls.io/github/Textalk/websocket-php)

This library contains WebSocket client and server for PHP.

The client and server provides methods for reading and writing to WebSocket streams.
It does not include convenience operations such as listeners and implicit error handling.

## Installing

Preferred way to install is with [Composer](https://getcomposer.org/).
```
composer require textalk/websocket
```

Current version support PHP versions `^7.1`.
For PHP `^5.4` and `^7.0` support use version `1.3`.

## Client

The client can read and write on a WebSocket stream.
It internally supports Upgrade handshake and implicit close and ping/pong operations.

###  Class synopsis

```php
WebSocket\Client {

    public __construct(string $uri, array $options = [])
    public __destruct()

    public send(mixed $payload, string $opcode = 'text', bool $masked = true) : void
    public receive() : mixed
    public close(int $status = 1000, mixed $message = 'ttfn') : mixed

    public getLastOpcode() : string
    public getCloseStatus() : int
    public isConnected() : bool
    public setTimeout(int $seconds) : void
    public setFragmentSize(int $fragment_size) : self
    public getFragmentSize() : int
    public setLogger(Psr\Log\LoggerInterface $logger = null) : void
}
```

### Example: Simple send-receive operation

This example send a single message to a server, and output the response.

```php
$client = new WebSocket\Client("ws://echo.websocket.org/");
$client->send("Hello WebSocket.org!");
echo $client->receive();
$client->close();
```

### Example: Listening to a server

To continuously listen to incoming messages, you need to put the receive operation within a loop.
Note that these functions **always** throw exception on any failure, including recoverable failures such as connection time out.
By consuming exceptions, the code will re-connect the socket in next loop iteration.

```php
$client = new WebSocket\Client("ws://echo.websocket.org/");
while (true) {
    try {
        $message = $client->receive();
        // Act on received message
        // Break while loop to stop listening
    } catch (\WebSocket\ConnectionException $e) {
        // Possibly log errors
    }
}
$client->close();
```

### Constructor options

The `$options` parameter in constructor accepts an associative array of options.

* `timeout` - Time out in seconds. Default 5 seconds.
* `fragment_size` - Maximum payload size. Default 4096 chars.
* `context` - A stream context created using [stream_context_create](https://www.php.net/manual/en/function.stream-context-create).
* `headers` - Additional headers as associative array name => content.
* `logger` - A [PSR-3](https://www.php-fig.org/psr/psr-3/) compatible logger.
* `persistent` - Connection is re-used between requests until time out is reached. Default false.

```php
$context = stream_context_create();
stream_context_set_option($context, 'ssl', 'verify_peer', false);
stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

$client = new WebSocket\Client("ws://echo.websocket.org/", [
    'timeout' => 60, // 1 minute time out
    'context' => $context,
    'headers' => [
        'Sec-WebSocket-Protocol' => 'soap',
        'origin' => 'localhost',
    ],
]);
```

## Server

The library contains a rudimentary single stream/single thread server.
It internally supports Upgrade handshake and implicit close and ping/pong operations.

Note that it does **not** support threading or automatic association ot continuous client requests.
If you require this kind of server behavior, you need to build it on top of provided server implementation.

###  Class synopsis

```php
WebSocket\Server {

    public __construct(array $options = [])
    public __destruct()

    public accept() : bool
    public send(mixed $payload, string $opcode = 'text', bool $masked = true) : void
    public receive() : mixed
    public close(int $status = 1000, mixed $message = 'ttfn') : mixed

    public getPort() : int
    public getPath() : string
    public getRequest() : array
    public getHeader(string $header_name) : string|null

    public getLastOpcode() : string
    public getCloseStatus() : int
    public isConnected() : bool
    public setTimeout(int $seconds) : void
    public setFragmentSize(int $fragment_size) : self
    public getFragmentSize() : int
    public setLogger(Psr\Log\LoggerInterface $logger = null) : void
}
```

### Example: Simple receive-send operation

This example reads a single message from a client, and respond with the same message.

```php
$server = new WebSocket\Server();
$server->accept();
$message = $server->receive();
$server->send($message);
$server->close();
```

### Example: Listening to clients

To continuously listen to incoming messages, you need to put the receive operation within a loop.
Note that these functions **always** throw exception on any failure, including recoverable failures such as connection time out.
By consuming exceptions, the code will re-connect the socket in next loop iteration.

```php
$server = new WebSocket\Server();
while ($server->accept()) {
    try {
        $message = $server->receive();
        // Act on received message
        // Break while loop to stop listening
    } catch (\WebSocket\ConnectionException $e) {
        // Possibly log errors
    }
}
$server->close();
```

### Constructor options

The `$options` parameter in constructor accepts an associative array of options.

* `timeout` - Time out in seconds. Default 5 seconds.
* `port` - The server port to listen to. Default 8000.
* `fragment_size` - Maximum payload size. Default 4096 chars.
* `logger` - A [PSR-3](https://www.php-fig.org/psr/psr-3/) compatible logger.

```php
$server = new WebSocket\Server([
    'timeout' => 60, // 1 minute time out
    'port' => 9000,
]);
```

## Exceptions

* `WebSocket\BadOpcodeException` - Thrown if provided opcode is invalid.
* `WebSocket\BadUriException` - Thrown if provided URI is invalid.
* `WebSocket\ConnectionException` - Thrown on any socket I/O failure.
* `WebSocket\TimeoutExeception` - Thrown when the socket experiences a time out.


## Development and contribution

Requirements on pull requests;
* All tests **MUST** pass.
* Code coverage **MUST** remain on 100%.
* Code **MUST** adhere to PSR-1 and PSR-12 code standards.

Install or update dependencies using [Composer](https://getcomposer.org/).
```
# Install dependencies
make install

# Update dependencies
make update
```

This project uses [PSR-1](https://www.php-fig.org/psr/psr-1/) and [PSR-12](https://www.php-fig.org/psr/psr-12/) code standards.
```
# Check code standard adherence
make cs-check
```

Unit tests with [PHPUnit](https://phpunit.readthedocs.io/).
```
# Run unit tests
make test
```

## License ([ISC](http://en.wikipedia.org/wiki/ISC_license))

Copyright (C) 2014-2020 Textalk/Abicart and contributors.

Websocket PHP is free software: Permission to use, copy, modify, and/or distribute this software
for any purpose with or without fee is hereby granted, provided that the above copyright notice and
this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS
SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT,
NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF
THIS SOFTWARE.

See [Copying](COPYING).

### Contributors

Fredrik Liljegren, Armen Baghumian Sankbarani, Ruslan Bekenev,
Joshua Thijssen, Simon Lipp, Quentin Bellus, Patrick McCarren, swmcdonnell,
Ignas Bernotas, Mark Herhold, Andreas Palm, Sören Jensen, pmaasz, Alexey Stavrov,
Michael Slezak.


## Changelog

1.4.0

 * Dropped support of old PHP versions (@sirn-se)
 * Added PSR-3 Logging support (@sirn-se)
 * Persistent connection option (@slezakattack)
 * TimeoutException on connection time out (@slezakattack)

1.3.1

 * Allow control messages without payload (@Logioniz)
 * Error code in ConnectionException (@sirn-se)

1.3.0

 * Implements ping/pong frames (@pmccarren @Logioniz)
 * Close behaviour (@sirn-se)
 * Various fixes concerning connection handling (@sirn-se)
 * Overhaul of Composer, Travis and Coveralls setup, PSR code standard and unit tests (@sirn-se)

1.2.0

 * Adding stream context options (to set e.g. SSL `allow_self_signed`).

1.1.2

 * Fixed error message on broken frame.

1.1.1

 * Adding license information.

1.1.0

 * Supporting huge payloads.

1.0.3

 * Bugfix: Correcting address in error-message

1.0.2

 * Bugfix: Add port in request-header.

1.0.1

 * Fixing a bug from empty payloads.

1.0.0

 * Release as production ready.
 * Adding option to set/override headers.
 * Supporting basic authentication from user:pass in URL.
