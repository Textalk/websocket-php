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
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertEquals(4096, $client->getFragmentSize());

        MockSocket::initialize('send-receive', $this);
        $client->send('Sending a message');
        $message = $client->receive();
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertEquals('text', $client->getLastOpcode());

        MockSocket::initialize('client.close', $this);
        $this->assertTrue($client->isConnected());
        $this->assertNull($client->getCloseStatus());

        $client->close();
        $this->assertFalse($client->isConnected());
        $this->assertEquals(1000, $client->getCloseStatus());
        $this->assertEquals('close', $client->getLastOpcode());

        $client->close();
        $this->assertFalse($client->isConnected());
        $this->assertEquals(1000, $client->getCloseStatus());
        $this->assertEquals('close', $client->getLastOpcode());

        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testDestruct()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('client.destruct', $this);
    }

    public function testClienExtendedUrl()
    {
        MockSocket::initialize('client.connect-extended', $this);
        $client = new Client('ws://localhost:8000/my/mock/path?my_query=yes#my_fragment');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testClientWithTimeout()
    {
        MockSocket::initialize('client.connect-timeout', $this);
        $client = new Client('ws://localhost:8000/my/mock/path', ['timeout' => 300]);
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testClientWithContext()
    {
        MockSocket::initialize('client.connect-context', $this);
        $client = new Client('ws://localhost:8000/my/mock/path', ['context' => '@mock-stream-context']);
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testClientAuthed()
    {
        MockSocket::initialize('client.connect-authed', $this);
        $client = new Client('wss://usename:password@localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testWithHeaders()
    {
        MockSocket::initialize('client.connect-headers', $this);
        $client = new Client('ws://localhost:8000/my/mock/path', [
            'origin' => 'Origin header',
            'headers' => ['Generic header' => 'Generic content'],
        ]);
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testPayload128()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        $payload = file_get_contents(__DIR__ . '/mock/payload.128.txt');

        MockSocket::initialize('send-receive-128', $this);
        $client->send($payload, 'text', false);
        $message = $client->receive();
        $this->assertEquals($payload, $message);
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testPayload65536()
    {
        MockSocket::initialize('client.connect', $this);
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
        $this->assertEquals(65540, $client->getFragmentSize());
    }

    public function testMultiFragment()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('send-receive-multi-fragment', $this);
        $client->setFragmentSize(8);
        $client->send('Multi fragment test');
        $message = $client->receive();
        $this->assertEquals('Multi fragment test', $message);
        $this->assertTrue(MockSocket::isEmpty());
        $this->assertEquals(8, $client->getFragmentSize());
    }

    public function testPingPong()
    {
        MockSocket::initialize('client.connect', $this);
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
    }

    public function testRemoteClose()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('close-remote', $this);

        /// @todo: Payload substr in Base.php probably wrong
        $message = $client->receive();
        $this->assertEquals('osing', $message);

        $this->assertFalse($client->isConnected());
        $this->assertEquals(17260, $client->getCloseStatus());
        $this->assertEquals('close', $client->getLastOpcode());
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testSetTimeout()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('config-timeout', $this);
        $client->setTimeout(300);
        $this->assertTrue($client->isConnected());
        $this->assertTrue(MockSocket::isEmpty());
    }

    public function testReconnect()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('client.close', $this);
        $this->assertTrue($client->isConnected());
        $this->assertNull($client->getCloseStatus());
        $client->close();
        $this->assertFalse($client->isConnected());
        $this->assertEquals(1000, $client->getCloseStatus());
        $this->assertEquals('close', $client->getLastOpcode());
        $this->assertTrue(MockSocket::isEmpty());

        MockSocket::initialize('client.reconnect', $this);
        $message = $client->receive();
        $this->assertTrue($client->isConnected());
        $this->assertTrue(MockSocket::isEmpty());
    }

    /**
     * @expectedException        WebSocket\BadUriException
     * @expectedExceptionMessage Url should have scheme ws or wss
     */
    public function testBadScheme()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('bad://localhost:8000/my/mock/path');
        $client->send('Connect');
    }

    /**
     * @expectedException        WebSocket\BadUriException
     * @expectedExceptionMessage Invalid url 'this is not an url' provided.
     */
    public function testBadUrl()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('this is not an url');
        $client->send('Connect');
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Stream context in $options['context'] isn't a valid context
     */
    public function testBadStreamContext()
    {
        MockSocket::initialize('client.connect-bad-context', $this);
        $client = new Client('ws://localhost:8000/my/mock/path', ['context' => 'BAD']);
        $client->send('Connect');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Could not open socket to "localhost:8000"
     */
    public function testFailedConnection()
    {
        MockSocket::initialize('client.connect-failed', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Connection to 'ws://localhost/my/mock/path' failed
     */
    public function testInvalidUpgrade()
    {
        MockSocket::initialize('client.connect-invalid-upgrade', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Server sent bad upgrade response
     */
    public function testInvalidKey()
    {
        MockSocket::initialize('client.connect-invalid-key', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
    }

    /**
     * @expectedException        WebSocket\BadOpcodeException
     * @expectedExceptionMessage Bad opcode 'bad'.  Try 'text' or 'binary'.
     */
    public function testSendBadOpcode()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');

        MockSocket::initialize('send-bad-opcode', $this);
        $client->send('Bad Opcode', 'bad');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Bad opcode in websocket frame: 12
     */
    public function testRecieveBadOpcode()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        MockSocket::initialize('receive-bad-opcode', $this);
        $message = $client->receive();
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Could only write 18 out of 22 bytes.
     */
    public function testBrokenWrite()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        MockSocket::initialize('send-broken-write', $this);
        $client->send('Failing to write');
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Broken frame, read 0 of stated 2 bytes.
     */
    public function testBrokenRead()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        MockSocket::initialize('receive-broken-read', $this);
        $client->receive();
    }

    /**
     * @expectedException        WebSocket\ConnectionException
     * @expectedExceptionMessage Empty read; connection dead?
     */
    public function testEmptyRead()
    {
        MockSocket::initialize('client.connect', $this);
        $client = new Client('ws://localhost:8000/my/mock/path');
        $client->send('Connect');
        MockSocket::initialize('receive-empty-read', $this);
        $client->receive();
    }
}
