<?php

namespace WebSocket;

class Base {
  protected $socket, $is_connected = false;

  protected static $opcodes = array(
    'text'   => 1,
    'binary' => 2,
    'close'  => 8,
    'ping'   => 9,
    'pong'   => 10,
  );

  public function send($payload, $opcode = 'text', $masked = false) {
    if (!$this->is_connected) $this->connect();

    if (!in_array($opcode, array('text', 'binary'))) {
      throw new BadOpcodeException('Only opcodes "text" and "binary" are supported in send.');
    }

    // Binary string for header.
    $frame_head_binstr = '';


    // Write FIN, final fragment bit.
    $final = true; /// @todo Support HUGE payloads.
    $frame_head_binstr .= $final ? '1' : '0';

    // RSV 1, 2, & 3 false and unused.
    $frame_head_binstr .= '000';

    // Opcode rest of the byte.
    $frame_head_binstr .= sprintf('%04b', self::$opcodes[$opcode]);

    // Use masking?
    $frame_head_binstr .= $masked ? '1' : '0';

    // 7 bits of payload length...
    $payload_length = strlen($payload);
    if ($payload_length > 65535) {
      $frame_head_binstr .= decbin(127);
      $frame_head_binstr .= sprintf('%064b', $payload_length);
    }
    elseif ($payload_length > 125) {
      $frame_head_binstr .= decbin(126);
      $frame_head_binstr .= sprintf('%016b', $payload_length);
    }
    else {
      $frame_head_binstr .= sprintf('%07b', $payload_length);
    }

    $frame = '';

    // Write frame head to frame.
    foreach (str_split($frame_head_binstr, 8) as $binstr) $frame .= chr(bindec($binstr));

    // Handle masking
    if ($masked) {
      // generate a random mask:
      $mask = '';
      for ($i = 0; $i < 4; $i++) $mask .= chr(rand(0, 255));
      $frame .= $mask;
    }

    // Append payload to frame:
    for ($i = 0; $i < $payload_length; $i++) {
      $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
    }

    $this->write($frame);
  }

  public function receive() {
    // Just read the main fragment information first.
    $data = $this->read(2);

    // Is this the final fragment?  // Bit 0 in byte 0
    /// @todo Handle huge payloads with multiple fragments.
    $final = (boolean) (ord($data[0]) & 1 << 7);

    // Should be unused, and must be false…  // Bits 1, 2, & 3
    $rsv1  = (boolean) (ord($data[0]) & 1 << 6);
    $rsv2  = (boolean) (ord($data[0]) & 1 << 5);
    $rsv3  = (boolean) (ord($data[0]) & 1 << 4);

    // Parse opcode
    $opcode_int = ord($data[0]) & 31; // Bits 4-7
    $opcode_ints = array_flip(self::$opcodes);
    if (!array_key_exists($opcode_int, $opcode_ints)) {
      throw new ConnectionException("Bad opcode in websocket frame: $opcode_int");
    }
    $opcode = $opcode_ints[$opcode_int];

    if ($opcode === 'close') {
      fclose($this->socket);
      $this->is_connected = false;

      // Throw ConnectionException?
      return false;
    }

    // Masking?
    $mask = (boolean) (ord($data[1]) >> 7);  // Bit 0 in byte 1
    if ($mask) echo "Using masking! ", self::sprintB($data), "\n";

    // Payload length
    $payload_length = (integer) ord($data[1]) & 127; // Bits 1-7 in byte 1
    if ($payload_length > 125) {
      if ($payload_length === 126) $data = $this->read(2); // 126: Payload is a 16-bit unsigned int
      else                         $data = $this->read(8); // 127: Payload is a 64-bit unsigned int
      $payload_length = bindec(self::sprintB($data));
    }

    // Get masking key.
    if ($mask) $masking_key = $this->read(4);

    $data = $this->read($payload_length);

    if (!$mask) return $data;

    // Unmask payload.
    $payload = '';
    for ($i = 0; $i < $payload_length; $i++) $payload .= ($data[$i] ^ $masking_key[$i % 4]);
    return $payload;
  }

  protected function write($data) {
    //echo "Writing: ”{$data}”\n";
    $written = fwrite($this->socket, $data);

    if ($written < strlen($data)) {
      throw new ConnectionException(
        "Could only write $written out of " . strlen($data) . " bytes."
      );
    }
  }

  protected function read($length) {
    $data = '';
    while (strlen($data) < $length) {
      $buffer = fread($this->socket, $length - strlen($data));
      if ($buffer === false) {
        $metadata = stream_get_meta_data($this->socket);
        throw new ConnectionException(
          'Broken frame, read ' . strlen($payload_data) . ' of stated '
          . $payload_length . ' bytes.  Stream state: '
          . json_encode($metadata)
        );
      }
      if ($buffer === '') {
        $metadata = stream_get_meta_data($this->socket);
        throw new ConnectionException(
          'Empty read; connection dead?  Stream state: ' . json_encode($metadata)
        );
      }
      $data .= $buffer;
    }
    //echo "Read: ”{$data}”\n";
    return $data;
  }


  /**
   * Helper to convert a binary to a string of '0' and '1'.
   */
  protected static function sprintB($string) {
    $return = '';
    for ($i = 0; $i < strlen($string); $i++) $return .= sprintf("%08b", ord($string[$i]));
    return $return;
  }
}
