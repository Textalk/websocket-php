// Source - https://stackoverflow.com/a/66789406
// Posted by Derzu, modified by community. See post 'Timeline' for change history
// Retrieved 2026-03-03, License - CC BY-SA 4.0

// https://stackoverflow.com/questions/21797299/convert-base64-string-to-arraybuffer
function base64ToArrayBuffer(base64) {
  var binary_string = Buffer.from(base64, 'base64').toString('binary');
  var len = binary_string.length;
  var bytes = new Uint8Array(len);
  for (var i = 0; i < len; i++) {
    bytes[i] = binary_string.charCodeAt(i);
  }    

  return bytes.buffer;
}

// websocket server
const WebSocket = require("ws"); // websocket server
const wss = new WebSocket.Server({ port: 9999 });
console.log("WebSocket Server Started on port 9999");

wss.binaryType = 'arraybuffer';
const content_base64 = "c3RyZWFtIGV2ZW50"; // Place your base64 content here.
const binaryData = base64ToArrayBuffer(content_base64);

wss.on("connection", (ws) => {
  console.log("WebSocket sending msg");
  ws.send(binaryData);
});
