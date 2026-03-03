<?php

/**
 * Integration tests for Client using public WebSocket servers.
 * Run with: vendor/bin/phpunit tests/ClientIntegrationTest.php
 * Or for specific server: vendor/bin/phpunit --filter PostmanEcho tests/ClientIntegrationTest.php
 *                                     vendor/bin/phpunit --filter BinanceStream tests/ClientIntegrationTest.php
 */

declare(strict_types=1);

namespace WebSocket;

use PHPUnit\Framework\TestCase;

class ClientIntegrationTest extends TestCase
{
    private const SERVER_LOCAL = 'ws://127.0.0.1:9999';
    private const SERVER_POSTMAN = 'wss://ws.postman-echo.com/raw';
    private const SERVER_WEBSOCKET = 'wss://echo.websocket.org';
    private const SERVER_BINANCE = 'wss://stream.binance.com:9443/ws/btcusdt@trade';

    private const TIMEOUT = 10;

    protected function setUp(): void
    {
        parent::setUp();
        error_reporting(-1);
    }

    // =========================================================================
    // Postman Echo Server Tests
    // =========================================================================

    public function testPostmanEchoConnectAndHandshake(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => true]);
        // Trigger connection by sending a message
        $client->send('test');
        $this->assertTrue($client->isConnected(), 'Failed to connect to ws.postman-echo.com');
        $client->close();
    }

    public function testPostmanEchoSendTextMessage(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => true]);
        $message = 'Test message for Postman';
        $client->send($message);
        $response = $client->receive();
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testPostmanEchoSendBinaryMessage(): void
    {
        $this->markTestSkipped('Seems like servers are not supporting echo binary data..');
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => true]);
        $message = '';
        for ($i = 1; $i < 500; $i++) {
            $message .= chr($i % 256);
        }
        $client->send($message, 'binary', false);
        $response = $client->receive();
        // print (strlen($message) . " == " . strlen($response) . "\n");
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testPostmanEchoMultipleMessages(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => true]);

        $messages = ['One', 'Two', 'Three', 'Four', 'Five'];
        foreach ($messages as $message) {
            $client->send($message);
            $response = $client->receive();
            $this->assertEquals($message, $response);
        }

        $client->close();
    }

    public function testPostmanEchoPingPong(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => true]);
        // Note: Echo server may not respond to ping, but should not error
        $client->send('ping-test');
        $response = $client->receive();
        $this->assertNotEmpty($response);
        $client->close();
    }

    public function testPostmanEchoLargeMessage(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => true]);
        $message = str_repeat('TestData-', 2000);
        $client->send($message, 'text', false);
        $response = $client->receive();
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testPostmanEchoConnectionClose(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => true]);
        $client->send('trigger connection');

        $this->assertTrue($client->isConnected());

        $client->close(1000, 'Test complete');

        $this->assertFalse($client->isConnected());
        $this->assertEquals(1000, $client->getCloseStatus());
    }

    // =========================================================================
    // Binance Stream Tests
    // =========================================================================

    public function testBinanceStreamConnect(): void
    {
        $client = new Client(self::SERVER_BINANCE, ['timeout' => self::TIMEOUT, 'blocking' => true]);
        // Trigger connection
        $client->send('');
        $this->assertTrue($client->isConnected(), 'Failed to connect to Binance stream');
        $client->close();
    }

    public function testBinanceStreamReceiveRealTimeData(): void
    {
        $client = new Client(self::SERVER_BINANCE, ['timeout' => self::TIMEOUT, 'blocking' => true]);

        // Wait for at least one message
        $received = false;
        $attempts = 0;
        $maxAttempts = 10;

        while (!$received && $attempts < $maxAttempts) {
            try {
                $response = $client->receive();
                if (!empty($response)) {
                    $data = json_decode($response, true);
                    $this->assertNotNull($data, 'Response is not valid JSON');
                    $this->assertArrayHasKey('e', $data, 'Missing event type field');
                    $received = true;
                }
            } catch (\Exception $e) {
                $attempts++;
                usleep(100000); // 100ms
            }
        }

        $this->assertTrue($received, 'Did not receive any data from Binance stream');
        $client->close();
    }

    public function testBinanceStreamReceiveMultipleMessages(): void
    {
        $client = new Client(self::SERVER_BINANCE, ['timeout' => self::TIMEOUT, 'blocking' => true]);

        $messages = [];
        $targetMessages = 3;

        $startTime = time();
        while (count($messages) < $targetMessages && (time() - $startTime) < 10) {
            try {
                $response = $client->receive();
                if (!empty($response)) {
                    $data = json_decode($response, true);
                    if ($data !== null) {
                        $messages[] = $data;
                    }
                }
            } catch (\Exception $e) {
                // Continue trying
            }
        }

        $this->assertGreaterThanOrEqual(1, count($messages), 'Did not receive enough messages from Binance');
        $client->close();
    }

    public function testBinanceStreamConnectionClose(): void
    {
        $client = new Client(self::SERVER_BINANCE, ['timeout' => self::TIMEOUT, 'blocking' => true]);
        $client->send('');

        $this->assertTrue($client->isConnected());

        $client->close(1000, 'Done testing');

        $this->assertFalse($client->isConnected());
    }

    // =========================================================================
    // Non-Blocking Mode Tests
    // =========================================================================

    public function testNonBlockingSendAndReceive(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);
        $message = 'Test message for Postman';
        $client->send($message);
        $response = '';
        for ($i=0; $i<10; $i++) {
            usleep(100000);
            $response .= $client->receive();
        }
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testNonBlockingMultipleMessages(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);

        $messages = ['One', 'Two', 'Three', 'Four', 'Five'];
        foreach ($messages as $message) {
            $client->send($message);
            $response = '';
            for ($i=0; $i<10; $i++) {
                usleep(100000);
                $response .= $client->receive();
            }
            $this->assertEquals($message, $response);
        }

        $client->close();
    }

    public function testNonBlockingLargeMessage(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);
        $message = str_repeat('TestData-', 2000);
        $client->send($message, 'text', false);
        $response = '';
        for ($i=0; $i<10; $i++) {
            usleep(100000);
            $response .= $client->receive();
        }
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testNonBlockingConnectionClose(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);
        $client->send('trigger connection');

        $this->assertTrue($client->isConnected());

        $client->close(1000, 'Test complete');

        $this->assertFalse($client->isConnected());
        $this->assertEquals(1000, $client->getCloseStatus());
    }

    // =========================================================================
    // Large Variable Size Tests
    // =========================================================================

    public function testLargeMessage10KB(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);
        $message = str_repeat('0123456789', 1024);
        $client->send($message, 'text', false);
        $response = '';
        for ($i = 0; $i < 10; $i++) {
            usleep(100000);
            $response .= $client->receive();
        }
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testLargeMessage100KB(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);
        $message = str_repeat('ABCDEFGHIJ', 10240);
        $client->send($message, 'text', false);
        $response = '';
        for ($i = 0; $i < 15; $i++) {
            usleep(100000);
            $response .= $client->receive();
        }
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testLargeMessage1MB(): void
    {
        $client = new Client(self::SERVER_LOCAL, ['timeout' => 30*3, 'blocking' => false]);
        $message = str_repeat('LargePayload-', 65536);
        $client->send($message, 'text', false);
        $response = '';
        for ($i = 0; $i < 25; $i++) {
            usleep(100000);
            $response .= $client->receive();
        }
        $this->assertEquals($message, $response);
        $client->close();
    }

    // =========================================================================
    // Multiple Large Messages Tests
    // =========================================================================

    public function testNonBlockingMultipleLargeMessages(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);

        $messages = [
            str_repeat('First-', 1000),
            str_repeat('Second-', 2000),
            str_repeat('Third-', 3000),
        ];

        foreach ($messages as $message) {
            $client->send($message, 'text', false);
            $response = '';
            for ($i = 0; $i < 15; $i++) {
                usleep(100000);
                $response .= $client->receive();
            }
            $this->assertEquals($message, $response);
        }

        $client->close();
    }

    public function testMultipleLargeMessages(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);

        $messages = [
            str_repeat('Alpha-', 1000),
            str_repeat('Beta-', 2000),
            str_repeat('Gamma-', 3000),
        ];

        foreach ($messages as $message) {
            $client->send($message, 'text', false);
            $response = '';
            for ($i = 0; $i < 15; $i++) {
                usleep(100000);
                $response .= $client->receive();
            }
            $this->assertEquals($message, $response);
        }

        $client->close();
    }

    // =========================================================================
    // Large Message Edge Cases
    // =========================================================================

    public function testLargeBinaryMessage(): void
    {
        $this->markTestSkipped('Seems like servers are not supporting echo binary data..');
        $client = new Client(self::SERVER_WEBSOCKET, ['timeout' => self::TIMEOUT, 'blocking' => false]);
        $message = '';
        for ($i = 0; $i < 5000; $i++) {
            $message .= chr($i % 256);
        }
        $client->send($message, 'binary', false);
        $response = '';
        for ($i = 0; $i < 10; $i++) {
            usleep(100000);
            $response .= $client->receive();
        }
        // print (strlen($message) . " == " . strlen($response) . "\n");
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testLargeUnicodeMessage(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);
        $message = str_repeat('こんにちは世界🌍', 500);
        $client->send($message, 'text', false);
        $response = '';
        for ($i = 0; $i < 10; $i++) {
            usleep(100000);
            $response .= $client->receive();
        }
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testLargeMessageWithNewlines(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);
        $message = str_repeat("Line1\tTabbed\r\nLine2\tTabbed\r\n", 500);
        $client->send($message, 'text', false);
        $response = '';
        for ($i = 0; $i < 10; $i++) {
            usleep(100000);
            $response .= $client->receive();
        }
        $this->assertEquals($message, $response);
        $client->close();
    }

    // =========================================================================
    // Ping/Pong Tests
    // =========================================================================
    public function testPingPongSingle(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);
        $client->send('trigger');
        $this->assertFalse($client->pongs4pings(), 'Check initial state');
        $client->ping();

        $rmsg = '';
        for ($i=0; $i<10; $i++) {
            usleep(100000);
            $rmsg .= $client->receive();
        }

        $this->assertEquals('trigger', $rmsg);
        $this->assertTrue($client->pongs4pings(), 'Pong not received for ping');
        $client->close();
    }

    public function testPingPongMultipleRandom(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT, 'blocking' => false]);

        $client->send('trigger');
        $this->assertFalse($client->pongs4pings(), 'Check initial state');

        $pingCount = 5;
        for ($i = 0; $i < $pingCount; $i++) {
            $client->ping('');
            usleep(100000);
        }

        $rmsg = '';
        for ($i=0; $i<10; $i++) {
            usleep(100000);
            $rmsg .= $client->receive();
        }

        $this->assertEquals('trigger', $rmsg);
        $this->assertTrue($client->pongs4pings(), 'Pong not received for ping');
        $client->close();
    }
}
