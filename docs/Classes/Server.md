Classes: [Client](Client.md) • Server

# Server class

Websocket Server class. Support multiple connections through the `listen()` method.

##  Class synopsis

```php
WebSocket\Server implements Psr\Log\LoggerAwareInterface {

    // Magic methods
    public __construct(array $options = [])
    public __toString() : string

    // Server operations
    public listen(Closure $callback) : mixed
    public stop(): void

    // Server option functions
    public getPort() : int
    public setTimeout(int $seconds) : void
    public setFragmentSize(int $fragment_size) : self
    public getFragmentSize() : int

    // Connection broadcast operations
    public text(string $payload) : void
    public binary(string $payload) : void
    public ping(string $payload = '') : void
    public pong(string $payload = '') : void
    public send(mixed $payload, string $opcode = 'text', bool $masked = true) : void
    public close(int $status = 1000, mixed $message = 'ttfn') : void
    public disconnect() : void
    public receive() : mixed

    // Provided by Psr\Log\LoggerAwareTrait
    public setLogger(Psr\Log\LoggerInterface $logger) : void

    // Deprecated functions
    public accept() : bool
    public getPath() : string
    public getRequest() : array
    public getHeader(string $header_name) : string|null
    public getLastOpcode() : string
    public getCloseStatus() : int
    public isConnected() : bool
    public getName() : string|null
    public getPeer() : string|null
    public getPier() : string|null
}
```

## __construct

Constructor for Websocket Server.

#### Description

```php
public function __construct(array $options = [])
```

#### Parameters

###### `options`

An optional array of parameters.
Name | Type | Default | Description
--- | --- | --- | ---
filter` | array | ['text', 'binary'] | Array of opcodes to return on receive and listen functions
fragment_size | int | 4096 | Maximum payload size
logger | Psr\Log\LoggerInterface | Psr\Log\NullLogger |A [PSR-3](https://www.php-fig.org/psr/psr-3/) compatible logger
port | int | 8000 | The server port to listen to
return_obj | bool | false | Return a [Message](Message.md) instance on receive function
timeout | int | 5 | Time out in seconds

#### Return Values

Returns a new WebSocket\Server instance.

#### Errors/Exceptions

Emits [ConnectionException](ConnectionException.md) on failure.

#### Examples

```php
<?php

// Without options
$server = new WebSocket\Server();

// With options
$server = new WebSocket\Server(['port' => 8080, 'timeout' => 60]);

?>
```


## __toString

Get string representation of instance.

#### Description

```php
public function __toString() : string
```

#### Return Values

Returns a string to represent current instance.


## listen

Set server to listen to incoming requests.

#### Description

```php
public function listen(Closure $callback) : mixed
```

#### Parameters

###### `callback`

A callback function that is triggered whenever the server receives a message matching the filter.

The callback takes two parameters;
* The [Message](Message/Message.md) that has been received
* The [Connection](Connection.md) the server has receievd on, can be `null` if connection is closed

If callback function returns non-null value, the listener will halt and return that value.
Otherwise it will continue listening and propagating messages.

#### Return Values

Returns any non-null value returned by callback function.

#### Errors/Exceptions

Emits [ConnectionException](ConnectionException.md) on failure.

#### Examples

Minimal setup that continuously listens to incoming text and binary messages.
```php
<?php

$server = new WebSocket\Server();
$server->listen(function ($message, $connection) {
    echo $message->getContent();
});
?>
```

Listen to all incoming message types and respond with a text message.
```php
<?php

$server = new WebSocket\Server(['filter' => ['text', 'binary', 'ping', 'pong', 'close']]);
$server->listen(function ($message, $connection) {
    if (!$connection) {
        $connection->text("Confirm " . $message->getOpcode());
    }
});
?>
```

Halt listener and return a value to calling code.
```php
<?php

$server = new WebSocket\Server();
$content = $server->listen(function ($message, $connection) {
    return $message->getContent();
});
echo $content;
?>
```

## stop

Tell server to stop listening to incoming requests.

#### Description

```php
public function stop(): void
```

#### Examples

Use stop() in listener.
```php
<?php

$server = new WebSocket\Server();
while (true) {
    $server->listen(function ($message, $connection) use ($server) {
        echo $message->getContent();
        $server->stop();
    });
    // Do things, listener will be restarted in next loop.
}
?>
```

## getPort

#### Description

```php
public function getPort(): void
```

## setTimeout

#### Description

```php
public function setTimeout(int $seconds): void
```

## setFragmentSize

#### Description

```php
public function setFragmentSize(int $fragment_size): self
```

## getFragmentSize

#### Description

```php
public function getFragmentSize(): int
```

## text

#### Description

```php
public function text(string $payload) : void
```

## binary

#### Description

```php
public function binary(string $payload) : void
```

## ping

#### Description

```php
public function ping(string $payload = '') : void
```

## pong

#### Description

```php
public function pong(string $payload = '') : void
```

## send

#### Description

```php
public function send(mixed $payload, string $opcode = 'text', bool $masked = true) : void
```

## close

#### Description

```php
public function close(int $status = 1000, mixed $message = 'ttfn') : void
```

## disconnect

#### Description

```php
public function disconnect() : void
```

## receive

#### Description

```php
public function receive() : mixed
```

## setLogger

#### Description

```php
public setLogger(Psr\Log\LoggerInterface $logger) : void
```
