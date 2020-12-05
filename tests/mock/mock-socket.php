<?php

/**
 * This file is used by tests to overload and mock various socket/stream calls.
 */

namespace WebSocket;

require 'mock-socket-functions.php';

$options = getopt('', ['debug']);
MockSocket::debug(isset($options['debug']));
