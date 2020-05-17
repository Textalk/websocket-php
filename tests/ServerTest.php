<?php

/**
 * Test case for Server.
 * Note that this test is performed by mocking socket/stream calls.
 */

namespace WebSocket;

class ServerTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        error_reporting(-1);
    }

    public function testServerMasked()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        $this->assertEquals(8000, $server->getPort());
        $this->assertEquals('/my/mock/path', $server->getPath());
        $this->assertTrue($server->isConnected());
        $this->assertEquals(4096, $server->getFragmentSize());
        $this->assertNull($server->getCloseStatus());
        $this->assertEquals([
            'GET /my/mock/path HTTP/1.1',
            'host: localhost:8000',
            'user-agent: websocket-client-php',
            'connection: Upgrade',
            'upgrade: websocket',
            'sec-websocket-key: cktLWXhUdDQ2OXF0ZCFqOQ==',
            'sec-websocket-version: 13',
            '',
            '',
        ], $server->getRequest());
        $this->assertEquals('websocket-client-php', $server->getHeader('USER-AGENT'));
        $this->assertNull($server->getHeader('no such header'));
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('send-receive', $this);
        $server->send('Sending a message');
        $message = $server->receive();
        $this->assertEquals('Receiving a message', $message);
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertNull($server->getCloseStatus());
        $this->assertEquals('text', $server->getLastOpcode());

        MockSocket::initialize('server.close', $this);
        $server->close();
        $this->assertFalse($server->isConnected());
        $this->assertEquals(1000, $server->getCloseStatus());
        $this->assertEquals('close', $server->getLastOpcode());
        $this->assertTrue(MockSocket::isEmpty());

        $server->close(); // Already closed
    }

    public function testDestruct()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();

        MockSocket::initialize('server.accept-destruct', $this);
        $server->accept();
        $message = $server->receive();
    }

    public function testServerWithTimeout()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server(['timeout' => 300]);
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept-timeout', $this);
        $server->accept();
        $server->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testPayload128()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        $this->assertTrue($server->isConnected());
        $this->assertTrue(MockSocket::isEmpty());

        $payload = file_get_contents(__DIR__ . '/mock/payload.128.txt');

        MockSocket::initialize('send-receive-128', $this);
        $server->send($payload, 'text', false);
        $message = $server->receive();
        $this->assertEquals($payload, $message);
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testPayload65536()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        $this->assertTrue($server->isConnected());
        $this->assertTrue(MockSocket::isEmpty());

        $payload = file_get_contents(__DIR__ . '/mock/payload.65536.txt');
        $server->setFragmentSize(65540);

        MockSocket::initialize('send-receive-65536', $this);
        $server->send($payload, 'text', false);
        $message = $server->receive();
        $this->assertEquals($payload, $message);
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testMultiFragment()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        $this->assertTrue($server->isConnected());
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('send-receive-multi-fragment', $this);
        $server->setFragmentSize(8);
        $server->send('Multi fragment test');
        $message = $server->receive();
        $this->assertEquals('Multi fragment test', $message);
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testPingPong()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        $this->assertTrue($server->isConnected());
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('ping-pong', $this);
        $server->send('Server ping', 'ping');
        $message = $server->receive();
        $this->assertEquals('pong', $message);
        $this->assertEquals('pong', $server->getLastOpcode());

        $message = $server->receive();
        $this->assertEquals('Client ping', $message);

        $this->assertTrue(MockSocket::isEmpty());
        $this->assertEquals('ping', $server->getLastOpcode());
    }

    public function testRemoteClose()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        $this->assertTrue($server->isConnected());
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('close-remote', $this);

        /// @todo: Payload substr in Base.php probably wrong
        $message = $server->receive();
        $this->assertEquals('osing', $message);

        $this->assertTrue(MockSocket::isEmpty());
        $this->assertFalse($server->isConnected());
        $this->assertEquals(17260, $server->getCloseStatus());
        $this->assertEquals('close', $server->getLastOpcode());
    }

    public function testSetTimeout()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        $this->assertTrue($server->isConnected());
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('config-timeout', $this);
        $server->setTimeout(300);
        $this->assertTrue($server->isConnected());
        $this->assertTrue(MockSocket::isEmpty());
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Could not open listening socket:
     */
    public function testFailedSocketServer()
    {
        MockSocket::initialize('server.construct-failed-socket-server', $this);
        $server = new Server(['port' => 9999]);
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Server failed to connect
     */
    public function testFailedConnect()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();

        MockSocket::initialize('server.accept-failed-connect', $this);
        $server->accept();
        $server->send('Connect');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Server failed to connect
     */
    public function testFailedConnectTimeout()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server(['timeout' => 300]);

        MockSocket::initialize('server.accept-failed-connect', $this);
        $server->accept();
        $server->send('Connect');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage No GET in request
     */
    public function testFailedHttp()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        MockSocket::initialize('server.accept-failed-http', $this);
        $server->accept();
        $server->send('Connect');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Client had no Key in upgrade request
     */
    public function testFailedWsKey()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        MockSocket::initialize('server.accept-failed-ws-key', $this);
        $server->accept();
        $server->send('Connect');
    }

    /**
     * @expectedException        WebSocket\BadOpcodeException
     * @expectedExceptionMessage Bad opcode 'bad'.  Try 'text' or 'binary'.
     */
    public function testSendBadOpcode()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        $server->send('Bad Opcode', 'bad');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Bad opcode in websocket frame: 12
     */
    public function testRecieveBadOpcode()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        MockSocket::initialize('receive-bad-opcode', $this);
        $message = $server->receive();
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Could only write 18 out of 22 bytes.
     */
    public function testBrokenWrite()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        MockSocket::initialize('send-broken-write', $this);
        $server->send('Failing to write');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Broken frame, read 0 of stated 2 bytes.
     */
    public function testBrokenRead()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        MockSocket::initialize('receive-broken-read', $this);
        $server->receive();
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Empty read; connection dead?
     */
    public function testEmptyRead()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $server->send('Connect');
        MockSocket::initialize('receive-empty-read', $this);
        $server->receive();
    }
}
