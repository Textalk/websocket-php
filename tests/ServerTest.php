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
        $this->assertTrue(MockSocket::isEmpty());
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

        MockSocket::initialize('server.receive-simple', $this);
        $message = $server->receive();
        $this->assertEquals('Client sending a message', $message);
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertTrue($server->isConnected());
        $this->assertNull($server->getCloseStatus());
        $this->assertEquals('text', $server->getLastOpcode());

        MockSocket::initialize('server.send-simple', $this);
        $server->send('Server sending a message');
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertTrue($server->isConnected());
        $this->assertNull($server->getCloseStatus());
        $this->assertEquals('text', $server->getLastOpcode());

        MockSocket::initialize('server.close', $this);
        $server->close();
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertFalse($server->isConnected());
        $this->assertEquals(1000, $server->getCloseStatus());
        $this->assertEquals('close', $server->getLastOpcode());
    }

    public function testServerWithTimeout()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server(['timeout' => 300]);
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept-timeout', $this);
        $server->accept();
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testPayload128()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $this->assertTrue(MockSocket::isEmpty());

        $payload = file_get_contents(__DIR__ . '/mock/payload.128.txt');

        MockSocket::initialize('server.send-payload-128', $this);
        $server->send($payload);
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testPayload65536()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $this->assertTrue(MockSocket::isEmpty());

        $payload = file_get_contents(__DIR__ . '/mock/payload.65536.txt');
        $server->setFragmentSize(65540);

        MockSocket::initialize('server.send-payload-65536', $this);
        $server->send($payload);
        $this->assertTrue(MockSocket::isEmpty());
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Could not open listening socket.
     */
    public function testFailedSocketServer()
    {
        MockSocket::initialize('server.construct-failed-socket-server', $this);
        $server = new Server(['port' => 9999]);
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
        MockSocket::initialize('server.receive-bad-opcode', $this);
        $message = $server->receive();
        var_dump($server->getLastOpcode());
    }
}
