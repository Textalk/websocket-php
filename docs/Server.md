[Client](Client.md) • Server • [Examples](Examples.md) • [Changelog](Changelog.md) • [Contributing](Contributing.md)

# Websocket: Server

The library contains a rudimentary single stream/single thread server.
It internally supports Upgrade handshake and implicit close and ping/pong operations.

Note that it does **not** support threading or automatic association ot continuous client requests.
If you require this kind of server behavior, you need to build it on top of provided server implementation.

##  Class synopsis

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

## Examples

### Simple receive-send operation

This example reads a single message from a client, and respond with the same message.

```php
$server = new WebSocket\Server();
$server->accept();
$message = $server->receive();
$server->send($message);
$server->close();
```

### Listening to clients

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

## Constructor options

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
* `WebSocket\ConnectionException` - Thrown on any socket I/O failure.
* `WebSocket\TimeoutException` - Thrown when the socket experiences a time out.
