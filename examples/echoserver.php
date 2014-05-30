<?php

require(dirname(dirname(__FILE__)) . '/vendor/autoload.php');

use WebSocket\Server;

$server = new Server(array('timeout' => 2));

echo $server->getPort(), "\n";

while ($connection = $server->accept()) {

  try {
    while(1) {
      $message = $server->receive();
      echo "Received $message\n\n";
      $server->send($message);
      if ($message === 'exit') {
        echo "Client told me to quit.  Bye bye.\n";
        exit;
      }
    }
  }
  catch (WebSocket\ConnectionException $e) {
    echo "Client died.\n";
  }
}

exit;
