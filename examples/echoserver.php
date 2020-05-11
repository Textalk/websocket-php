<?php

error_reporting(-1);

/**
 * This file is used for the tests, but can also serve as an example of a WebSocket\Server.
 */

require(dirname(dirname(__FILE__)) . '/vendor/autoload.php');

use WebSocket\Server;

// Setting timeout to 200 seconds to make time for all tests and manual runs.
$server = new Server(array('timeout' => 200));

echo $server->getPort(), "\n";

while ($server->accept()) {

  try {
    while (true) {
      $message = $server->receive();
      echo "Received $message\n\n";

      if ($message === 'exit') {
        echo microtime(true), " Client told me to quit.  Bye bye.\n";
        echo microtime(true), " Close response: ", $server->close(), "\n";
        echo microtime(true), " Close status: ", $server->getCloseStatus(), "\n";
        exit;
      }

      if ($message === 'Dump headers') {
        $server->send(implode("\r\n", $server->getRequest()));
      }
      if ($message === 'ping') {
        $server->send('ping', 'ping', true);
      }
      elseif ($auth = $server->getHeader('Authorization')) {
        $server->send("$auth - $message", 'text', false);
      }
      else {
        $server->send($message, 'text', false);
      }
    }
  }
  catch (WebSocket\ConnectionException $e) {
    echo "\n", microtime(true), " Connection died: $e\n";
  }

}
