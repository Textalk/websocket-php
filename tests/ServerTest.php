<?php

/**
 * Test case for Server
 */

namespace WebSocket;

class ServerTest extends \PHPUnit_Framework_TestCase
{

    public function testServerMasked()
    {
        MockSocket::initialize('server.construct', $this);
        $server = new Server();
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('server.accept', $this);
        $server->accept();
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertEquals(8000, $server->getPort());
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

        MockSocket::initialize('server.receive-simple', $this);
        $message = $server->receive();
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertEquals('Client sending a message', $message);
        $this->assertTrue($server->isConnected());
        $this->assertEquals(4096, $server->getFragmentSize());
        $this->assertNull($server->getCloseStatus());
        $this->assertEquals('text', $server->getLastOpcode());

        MockSocket::initialize('server.send-simple', $this);
        $server->send('Server sending a message');
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertTrue($server->isConnected());
        $this->assertEquals(4096, $server->getFragmentSize());
        $this->assertNull($server->getCloseStatus());
        $this->assertEquals('text', $server->getLastOpcode());
    }
}
