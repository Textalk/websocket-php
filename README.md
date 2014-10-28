Websocket Client for PHP
========================

[![Build Status](https://travis-ci.org/Textalk/websocket-php.png)](https://travis-ci.org/Textalk/websocket-php)
[![Coverage Status](https://coveralls.io/repos/Textalk/websocket-php/badge.png)](https://coveralls.io/r/Textalk/websocket-php)

This package mainly contains a WebSocket client for PHP.

I made it because the state of other WebSocket clients I could found was either very poor
(sometimes failing on large frames) or had huge dependencies (React…).

The Client should be good.  If it isn't, tell me!

The Server there because much of the code would be identical in writing a server, and because it is
used for the tests.  To be really useful though, there should be a Connection-class returned from a
new Connection, and the Server-class only handling the handshake.  Then you could hold a full array
of Connections and check them periodically for new data, send something to them all or fork off a
process handling one connection.  But, I have no use for that right now.  (Actually, I would
suggest a language with better asynchronous handling than PHP for that.)

Installing
----------

Preferred way to install is with [Composer](https://getcomposer.org/).

Just add

    "require": {
      "textalk/websocket": "1.0.*"
    }

in your projects composer.json.

Client usage:
-------------
```php
require('vendor/autoload.php');

use WebSocket\Client;

$client = new Client("ws://echo.websocket.org/");
$client->send("Hello WebSocket.org!");

echo $client->receive(); // Will output 'Hello WebSocket.org!'
```

Developer install
-----------------

Development depends on php, php-curl and php-xdebug.

```bash
# Will get composer, install dependencies and run tests
make test
```

Changelog
---------

1.0.0

 * Release as production ready.
 * Adding option to set/override headers.
 * Supporting basic authentication from user:pass in URL.
