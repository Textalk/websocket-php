<?php

use WebSocket\Client;

class WebSocketTest extends PHPUnit_Framework_TestCase {
  protected static $port;

  public static function setUpBeforeClass() {
    // Start server to run client tests on
    $cmd = 'php examples/echoserver.php';
    $outputfile = 'build/serveroutput.txt';
    $pidfile    = 'build/server.pid';
    exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));

    usleep(10000);
    self::$port = trim(file_get_contents($outputfile));

    echo "Server started with port: ", self::$port, "\n";
  }

  public static function tearDownAfterClass() {
    $ws = new Client('ws://localhost:' . self::$port);
    $ws->send('exit');
  }

  public function testInstantiation() {
    $ws = new Client('ws://localhost:' . self::$port);

    $this->assertInstanceOf('WebSocket\Client', $ws);
  }

  /**
   * @dataProvider dataLengthProvider
   */
  public function testEcho($data_length) {
    $ws = new Client('ws://localhost:' . self::$port);

    $greeting = '';
    for ($i = 0; $i < $data_length; $i++) $greeting .= 'o';

    $ws->send($greeting);
    $response = $ws->receive();

    $this->assertEquals($greeting, $response);
  }

  public function dataLengthProvider() {
    return array(
      array(8),
      array(126),
      array(127),
      array(128),
      array(65000),
      array(66000),
    );
  }

  public function testOrgEchoTwice() {
    $ws = new Client('ws://localhost:' . self::$port);

    for ($i = 0; $i < 2; $i++) {
      $greeting = 'Hello WebSockets ' . $i;
      $ws->send($greeting);
      $response = $ws->receive();
      $this->assertEquals($greeting, $response);
    }
  }

  /**
   * @expectedException        WebSocket\BadUriException
   * @expectedExceptionMessage Url should have scheme ws or wss
   */
  public function testBadUrl() {
    $ws = new Client('http://echo.websocket.org');
    $ws->send('foo');
  }

  /**
   * @expectedException        WebSocket\ConnectionException
   */
  public function testNonWSSite() {
    $ws = new Client('ws://example.org');
    $ws->send('foo');
  }

  public function testSslUrl() {
    $ws = new Client('wss://echo.websocket.org');

    $greeting = 'Hello WebSockets';
    $ws->send($greeting);
    $response = $ws->receive();
    $this->assertEquals($greeting, $response);
  }

  public function testSslUrlMasked() {
    $ws = new Client('wss://echo.websocket.org');

    $greeting = 'Hello WebSockets';
    $ws->send($greeting, 'text', true);
    $response = $ws->receive();
    $this->assertEquals($greeting, $response);
  }

  public function testMaskedEcho() {
    $ws = new Client('ws://localhost:' . self::$port);

    $greeting = 'Hello WebSockets';
    $ws->send($greeting, 'text', true);
    $response = $ws->receive();
    $this->assertEquals($greeting, $response);
  }

  /**
   * @dataProvider timeoutProvider
   */
  public function testTimeout($timeout) {
    $start_time = microtime(true);

    try {
      $ws = new Client('ws://example.org:1111', array('timeout' => $timeout));
      $ws->send('foo');
    }
    catch (WebSocket\ConnectionException $e) {
      $this->assertLessThan($timeout + 0.2, microtime(true) - $start_time);
      $this->assertGreaterThan($timeout - 0.2, microtime(true) - $start_time);
    }

    if (!isset($e)) $this->fail('Should have timed out and thrown a ConnectionException');
  }

  public function timeoutProvider() {
    return array(
      array(1),
      array(2),
    );
  }

  public function testSocketCloseOnDestroy() {}

  /**
   * @expectedException WebSocket\BadOpcodeException
   * @expectedExceptionMessage Only opcodes "text" and "binary" are supported in send.
   */
  public function testSendBadOpcode() {
    $ws = new Client('ws://echo.websocket.org');
    $ws->send('foo', 'bad_opcode');
  }
}
