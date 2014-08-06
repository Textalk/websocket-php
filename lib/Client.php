<?php

namespace WebSocket;

class Client extends Base {
  protected $socket_uri;

  /**
   * @param string  $socket   A ws/wss-URI
   * @param array   $options
   *   Associative array containing:
   *   - origin:       Used to set the origin header.
   *   - timeout:      Set the socket timeout in seconds.  Default: 5
   */
  public function __construct($uri, $options = array()) {
    $this->options = $options;

    if (!array_key_exists('timeout', $this->options)) $this->options['timeout'] = 5;

    $this->socket_uri = $uri;
  }

  public function __destruct() {
    if ($this->socket) {
      if (get_resource_type($this->socket) === 'stream') fclose($this->socket);
      $this->socket = null;
    }
  }

  /**
   * Perform WebSocket handshake
   */
  protected function connect() {
    $url_parts = parse_url($this->socket_uri);
    $scheme    = $url_parts['scheme'];
    $host      = $url_parts['host'];
    $user      = isset($url_parts['user']) ? $url_parts['user'] : '';
    $pass      = isset($url_parts['pass']) ? $url_parts['pass'] : '';
    $port      = isset($url_parts['port']) ? $url_parts['port'] : ($scheme === 'wss' ? 443 : 80);
    $path      = isset($url_parts['path']) ? $url_parts['path'] : '/';
    $query     = isset($url_parts['query'])    ? $url_parts['query'] : '';
    $fragment  = isset($url_parts['fragment']) ? $url_parts['fragment'] : '';

    $path_with_query = $path;
    if (!empty($query))    $path_with_query .= '?' . $query;
    if (!empty($fragment)) $path_with_query .= '#' . $fragment;

    if (!in_array($scheme, array('ws', 'wss'))) {
      throw new BadUriException(
        "Url should have scheme ws or wss, not '$scheme' from URI '$this->socket_uri' ."
      );
    }

    $host_uri = ($scheme === 'wss' ? 'ssl' : 'tcp') . '://' . $host;

    // Open the socket.  @ is there to supress warning that we will catch in check below instead.
    $this->socket = @fsockopen($host_uri, $port, $errno, $errstr, $this->options['timeout']);

    if ($this->socket === false) {
      throw new ConnectionException(
        "Could not open socket to \"$host:$port\": $errstr ($errno)."
      );
    }

    $authHeader = '';
    if ($user || $pass) {
        $authHeader = 'Authorization: Basic ' . base64_encode($user . ':' . $pass) . "\r\n";
    }

    $key = self::generateKey();
    $header =
      "GET " . $path_with_query . " HTTP/1.1\r\n"
      . $authHeader
      . (array_key_exists('origin', $this->options) ? "Origin: {$this->options['origin']}\r\n" : '')
      . "Host: " . $host . "\r\n"
      . "Sec-WebSocket-Key: " . $key . "\r\n"
      . "User-Agent: websocket-client-php\r\n"
      . "Upgrade: websocket\r\n"
      . "Connection: Upgrade\r\n"
      . "Sec-WebSocket-Version: 13\r\n"
      . "\r\n";

    $this->write($header);

    $response = '';
    do {
      $buffer = stream_get_line($this->socket, 1024, "\r\n");
      $response .= $buffer . "\n";
      $metadata = stream_get_meta_data($this->socket);
    } while ($buffer !== 'adsf' && !feof($this->socket) && $metadata['unread_bytes'] > 0);

    /// @todo Handle version switching

    if (!preg_match('#Sec-WebSocket-Accept:\s(.*)$#mUi', $response, $matches)) {
      $address = $scheme . '://' . $host . '/' . $path_with_query;
      throw new ConnectionException(
        "Connection to '{$address}' failed: Server sent invalid upgrade response:\n"
        . $response
      );
    }

    $keyAccept = trim($matches[1]);
    $expectedResonse
      = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

    if ($keyAccept !== $expectedResonse) {
      throw new ConnectionException('Server sent bad upgrade response.');
    }

    $this->is_connected = true;
  }

  /**
   * Generate a random string for WebSocket key.
   * @return string Random string
   */
  protected static function generateKey() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"$&/()=[]{}0123456789';
    $key = '';
    $chars_length = strlen($chars);
    for ($i = 0; $i < 16; $i++) $key .= $chars[mt_rand(0, $chars_length-1)];
    return base64_encode($key);
  }
}
