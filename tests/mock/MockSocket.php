<?php

/**
 * This class is used by tests to mock and track various socket/stream calls.
 */

namespace WebSocket;

class MockSocket
{

    private static $queue = [];
    private static $asserter = [];

    // Handler called by function overloads in mock-socket.php
    public static function handle($function, $params = [])
    {
        $current = array_shift(self::$queue);
        self::$asserter->assertEquals($function, $current['function']);
        foreach ($current['params'] as $index => $param) {
            self::$asserter->assertEquals($param, $params[$index], json_encode([$current, $params]));
        }
        if (isset($current['return-op'])) {
            return self::op($current['return-op'], $current['return']);
        }
        if (isset($current['return'])) {
            return $current['return'];
        }
        return call_user_func_array($function, $params);
    }

    // Check if all expected calls are performed
    public static function isEmpty()
    {
        return empty(self::$queue);
    }

    // Initialize call queue
    public static function initialize($op_file, $asserter)
    {
        self::$queue = json_decode(file_get_contents(__DIR__ . "/{$op_file}.json"), true);
        self::$asserter = $asserter;
    }

    // Special output handling
    private static function op($op, $data)
    {
        switch ($op) {
            case 'chr-array':
                // Convert int array to string
                $out = '';
                foreach ($data as $val) {
                    $out .= chr($val);
                }
                return $out;
        }
    }
}
