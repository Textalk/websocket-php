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

    usleep(500000);
    self::$port = trim(file_get_contents($outputfile));

    echo "Server started with port: ", self::$port, "\n";
  }

  public static function tearDownAfterClass() {
    $ws = new Client('ws://localhost:' . self::$port);
    $ws->send('exit');
  }

  public function setup() {
    // Setup server side coverage catching
    $this->test_id = rand();
  }

  protected function getCodeCoverage() {
    $files = glob(dirname(dirname(dirname(__FILE__))) . '/build/tmp/' . $this->test_id . '.*');

    if (count($files) > 1) {
      echo "We have more than one coverage file...\n";
    }

    foreach ($files as $file) {
      $buffer = file_get_contents($file);
      $coverage_data = unserialize($buffer);
    }

    if (!isset($coverage_data)) return array();

    return $coverage_data;
  }

  public function run(PHPUnit_Framework_TestResult $result = NULL) {
    if ($result === NULL) {
      $result = $this->createResult();
    }

    $this->collectCodeCoverageInformation = $result->getCollectCodeCoverageInformation();

    parent::run($result);

    if ($this->collectCodeCoverageInformation) {
      $result->getCodeCoverage()->append(
        $this->getCodeCoverage(), $this
      );
    }

    return $result;
  }

  public function testInstantiation() {
    $ws = new Client('ws://localhost:' . self::$port . '/' . $this->test_id);

    $this->assertInstanceOf('WebSocket\Client', $ws);
  }

  /**
   * @dataProvider dataLengthProvider
   */
  public function testEcho($data_length) {
    $ws = new Client('ws://localhost:' . self::$port . '/' . $this->test_id);

    $greeting = '';
    for ($i = 0; $i < $data_length; $i++) $greeting .= 'o';

    $ws->send($greeting);
    $response = $ws->receive();

    $this->assertEquals($greeting, $response);
  }

  public function testBasicAuth() {
    $user = 'JohnDoe';
    $pass = 'eoDnhoJ';

    $ws = new Client("ws://$user:$pass@localhost:" . self::$port . '/' . $this->test_id);

    $greeting = 'Howdy';
    $ws->send($greeting);
    $response = $ws->receive();

    // Echo server will prefix basic authed requests.
    $this->assertEquals("Basic Sm9obkRvZTplb0RuaG9K - $greeting", $response);
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
    $ws = new Client('ws://localhost:' . self::$port . '/' . $this->test_id);

    for ($i = 0; $i < 2; $i++) {
      $greeting = 'Hello WebSockets ' . $i;
      $ws->send($greeting);
      $response = $ws->receive();
      $this->assertEquals($greeting, $response);
    }
  }

  public function testClose() {
    // Start a NEW dedicated server for this test
    $cmd = 'php examples/echoserver.php';
    $outputfile = 'build/serveroutput_close.txt';
    $pidfile    = 'build/server_close.pid';
    exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));

    usleep(500000);
    $port = trim(file_get_contents($outputfile));

    $ws = new Client('ws://localhost:' . $port . '/' . $this->test_id);
    $ws->send('exit');
    $response = $ws->receive();

    $this->assertEquals('ttfn', $response);
    $this->assertEquals(1000, $ws->getCloseStatus());
    $this->assertFalse($ws->isConnected());
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
    $ws = new Client('ws://localhost:' . self::$port . '/' . $this->test_id);

    $greeting = 'Hello WebSockets';
    $ws->send($greeting, 'text', true);
    $response = $ws->receive();
    $this->assertEquals($greeting, $response);
  }

  /**
   * @dataProvider timeoutProvider
   */
  public function testTimeout($timeout) {
    try {
      $ws = new Client('ws://example.org:1111', array('timeout' => $timeout));
      $start_time = microtime(true);
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

  public function testChangeTimeout() {
    $timeout = 1;

    try {
      $ws = new Client('ws://example.org:1111', array('timeout' => 5));
      $ws->setTimeout($timeout);
      $start_time = microtime(true);
      $ws->send('foo');
    }
    catch (WebSocket\ConnectionException $e) {
      $this->assertLessThan($timeout + 0.2, microtime(true) - $start_time);
      $this->assertGreaterThan($timeout - 0.2, microtime(true) - $start_time);
    }

    if (!isset($e)) $this->fail('Should have timed out and thrown a ConnectionException');
  }

  public function testDefaultHeaders() {
    $ws = new Client('ws://localhost:' . self::$port . '/' . $this->test_id);

    $ws->send('Dump headers');

    $this->assertRegExp(
      "/GET \/$this->test_id HTTP\/1.1\r\n"
      . "host: localhost:".self::$port."\r\n"
      . "user-agent: websocket-client-php\r\n"
      . "connection: Upgrade\r\n"
      . "upgrade: websocket\r\n"
      . "sec-websocket-key: .*\r\n"
      . "sec-websocket-version: 13\r\n/",
      $ws->receive()
    );
  }

  public function testUserAgentOverride() {
    $ws = new Client(
      'ws://localhost:' . self::$port . '/' . $this->test_id,
      array('headers' => array('User-Agent' => 'Deep thought'))
    );

    $ws->send('Dump headers');

    $this->assertRegExp(
      "/GET \/$this->test_id HTTP\/1.1\r\n"
      . "host: localhost:".self::$port."\r\n"
      . "user-agent: Deep thought\r\n"
      . "connection: Upgrade\r\n"
      . "upgrade: websocket\r\n"
      . "sec-websocket-key: .*\r\n"
      . "sec-websocket-version: 13\r\n/",
      $ws->receive()
    );
  }

  public function testAddingHeaders() {
    $ws = new Client(
      'ws://localhost:' . self::$port . '/' . $this->test_id,
      array('headers' => array('X-Cooler-Than-Beeblebrox' => 'Slartibartfast'))
    );

    $ws->send('Dump headers');

    $this->assertRegExp(
      "/GET \/$this->test_id HTTP\/1.1\r\n"
      . "host: localhost:".self::$port."\r\n"
      . "user-agent: websocket-client-php\r\n"
      . "connection: Upgrade\r\n"
      . "upgrade: websocket\r\n"
      . "sec-websocket-key: .*\r\n"
      . "sec-websocket-version: 13\r\n"
      . "x-cooler-than-beeblebrox: Slartibartfast\r\n/",
      $ws->receive()
    );
  }

  /**
   * @expectedException WebSocket\BadOpcodeException
   * @expectedExceptionMessage Bad opcode 'bad_opcode'
   */
  public function testSendBadOpcode() {
    $ws = new Client('ws://localhost:' . self::$port);
    $ws->send('foo', 'bad_opcode');
  }
}
