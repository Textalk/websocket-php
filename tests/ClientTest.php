<?php

/**
 * Test case for Client.
 * Note that this test is performed by mocking socket/stream calls.
 */

namespace WebSocket;

class ClientTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        error_reporting(-1);
    }

    public function testClientMasked()
    {
        MockSocket::initialize('client.init', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('send-receive', $this);
        $client->send('Sending a message');
        $message = $client->receive();
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertTrue($client->isConnected());
        $this->assertNull($client->getCloseStatus());
        $this->assertEquals('text', $client->getLastOpcode());

        MockSocket::initialize('client.close', $this);
        $client->close();
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertFalse($client->isConnected());
        $this->assertEquals(1000, $client->getCloseStatus());
        $this->assertEquals('close', $client->getLastOpcode());

        // Catch destruct routine
        MockSocket::initialize('client.destruct', $this);
    }

    // testServerWithTimeout

    public function testPayload128()
    {
        MockSocket::initialize('client.init', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        $payload = file_get_contents(__DIR__ . '/mock/payload.128.txt');

        MockSocket::initialize('send-receive-128', $this);
        $client->send($payload, 'text', false);
        $message = $client->receive();
        $this->assertEquals($payload, $message);
        $this->assertTrue(MockSocket::isEmpty());

        // Catch destruct routine
        MockSocket::initialize('client.destruct', $this);
    }

    public function testPayload65536()
    {
        MockSocket::initialize('client.init', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        $payload = file_get_contents(__DIR__ . '/mock/payload.65536.txt');
        $client->setFragmentSize(65540);

        MockSocket::initialize('send-receive-65536', $this);
        $client->send($payload, 'text', false);
        $message = $client->receive();
        $this->assertEquals($payload, $message);
        $this->assertTrue(MockSocket::isEmpty());

        // Catch destruct routine
        MockSocket::initialize('client.destruct', $this);
    }

    public function testMultiFragment()
    {
        MockSocket::initialize('client.init', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('send-receive-multi-fragment', $this);
        $client->setFragmentSize(8);
        $client->send('Multi fragment test');
        $message = $client->receive();
        $this->assertEquals('Multi fragment test', $message);
        $this->assertTrue(MockSocket::isEmpty());

        // Catch destruct routine
        MockSocket::initialize('client.destruct', $this);
    }

    public function testPingPong()
    {
        MockSocket::initialize('client.init', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('ping-pong', $this);
        $client->send('Server ping', 'ping');
        $message = $client->receive();
        $this->assertEquals('pong', $message);
        $this->assertEquals('pong', $client->getLastOpcode());

        $message = $client->receive();
        $this->assertEquals('Client ping', $message);

        $this->assertTrue(MockSocket::isEmpty());
        $this->assertEquals('ping', $client->getLastOpcode());

        // Catch destruct routine
        MockSocket::initialize('client.destruct', $this);
    }

    public function testRemoteClose()
    {
        MockSocket::initialize('client.init', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('close-remote', $this);

        /// @todo: Payload substr in Base.php probably wrong
        $message = $client->receive();
        $this->assertEquals('osing', $message);

        $this->assertTrue(MockSocket::isEmpty());
        $this->assertFalse($client->isConnected());
        $this->assertEquals(17260, $client->getCloseStatus());
        $this->assertEquals('close', $client->getLastOpcode());

        // Catch destruct routine
        MockSocket::initialize('client.destruct', $this);
    }

    /**
     * @expectedException        WebSocket\BadOpcodeException
     * @expectedExceptionMessage Bad opcode 'bad'.  Try 'text' or 'binary'.
     */
    public function testSendBadOpcode()
    {
        MockSocket::initialize('client.init', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('client.destruct', $this);
        $client->send('Bad Opcode', 'bad');
    }

    // testRecieveBadOpcode
    // testBrokenWrite
    // testBrokenRead
    // testEmptyRead
}
