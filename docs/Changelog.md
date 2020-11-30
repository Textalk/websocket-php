[Client](Client.md) • [Server](Server.md) • [Examples](Examples.md) • Changelog • [Contributing](Contributing.md)

# Websocket: Changelog

## `v1.5`

 > PHP version `^7.2`

#### `1.5.0`

 * Convenience send methods; text(), binary(), ping(), pong() (@sirn-se)
 * Opcode filter for receive() method (@sirn-se)
 * getName(), getRemoteName(), __toString() methods to identify instance (@sirn-se)
 * Fix for unordered fragmented messages (@sirn-se)
 * Various code re-write (@sirn-se)

## `v1.4`

 > PHP version `^7.1`

#### `1.4.2`

 * Force stream close on read error (@sirn-se)
 * Authorization headers line feed (@sirn-se)
 * Documentation (@matias-pool, @sirn-se)

#### `1.4.1`

 * Ping/Pong, handled internally to avoid breaking fragmented messages (@nshmyrev, @sirn-se)
 * Fix for persistent connections (@rmeisler)
 * Fix opcode bitmask (@peterjah)

#### `1.4.0`

 * Dropped support of old PHP versions (@sirn-se)
 * Added PSR-3 Logging support (@sirn-se)
 * Persistent connection option (@slezakattack)
 * TimeoutException on connection time out (@slezakattack)

## `v1.3`

 > PHP version `^5.4` and `^7.0`

#### `1.3.1`

 * Allow control messages without payload (@Logioniz)
 * Error code in ConnectionException (@sirn-se)

#### `1.3.0`

 * Implements ping/pong frames (@pmccarren @Logioniz)
 * Close behaviour (@sirn-se)
 * Various fixes concerning connection handling (@sirn-se)
 * Overhaul of Composer, Travis and Coveralls setup, PSR code standard and unit tests (@sirn-se)

## `v1.2`

 > PHP version `^5.4` and `^7.0`

#### `1.2.0`

 * Adding stream context options (to set e.g. SSL `allow_self_signed`).

## `v1.1`

 > PHP version `^5.4` and `^7.0`

#### `1.1.2`

 * Fixed error message on broken frame.

#### `1.1.1`

 * Adding license information.

#### `1.1.0`

 * Supporting huge payloads.

## `v1.0`

 > PHP version `^5.4` and `^7.0`

#### `1.0.3`

 * Bugfix: Correcting address in error-message

#### `1.0.2`

 * Bugfix: Add port in request-header.

#### `1.0.1`

 * Fixing a bug from empty payloads.

#### `1.0.0`

 * Release as production ready.
 * Adding option to set/override headers.
 * Supporting basic authentication from user:pass in URL.

