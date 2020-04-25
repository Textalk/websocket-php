Websocket Client for PHP
========================

[![Build Status](https://travis-ci.org/Textalk/websocket-php.png)](https://travis-ci.org/Textalk/websocket-php)
[![Coverage Status](https://coveralls.io/repos/Textalk/websocket-php/badge.png)](https://coveralls.io/r/Textalk/websocket-php)

This package mainly contains a WebSocket client for PHP.

I made it because the state of other WebSocket clients I could found was either very poor
(sometimes failing on large frames) or had huge dependencies (React…).

The Client should be good.

The Server there because much of the code would be identical in writing a server, and because it is
used for the tests.  To be really useful though, there should be a Connection-class returned from a
new Connection, and the Server-class only handling the handshake.  Then you could hold a full array
of Connections and check them periodically for new data, send something to them all or fork off a
process handling one connection.  But, I have no use for that right now.  (Actually, I would
suggest a language with better asynchronous handling than PHP for that.)

Installing
----------

Preferred way to install is with [Composer](https://getcomposer.org/).
```
composer require textalk/websocket
```

Currently support PHP versions `^5.4` and `^7.0`.


Client usage:
-------------
```php
require('vendor/autoload.php');

use WebSocket\Client;

$client = new Client("ws://echo.websocket.org/");
$client->send("Hello WebSocket.org!");

echo $client->receive(); // Will output 'Hello WebSocket.org!'
```

Development and contribution
-----------------

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

License ([ISC](http://en.wikipedia.org/wiki/ISC_license))
---------------------------------------------------------

Copyright (C) 2014-2020 Textalk/Abicart
Copyright (C) 2015 Patrick McCarren - added payload fragmentation for huge payloads
Copyright (C) 2015 Ignas Bernotas - added stream context options
Copyright (C) 2015 Patrick McCarren - added ping/pong support

Websocket PHP is free software: Permission to use, copy, modify, and/or distribute this software
for any purpose with or without fee is hereby granted, provided that the above copyright notice and
this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS
SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT,
NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF
THIS SOFTWARE.

See COPYING.


Changelog
---------

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
