#!/usr/bin/env python

"""Echo server using the asyncio API."""

import asyncio
from websockets.asyncio.server import serve


async def echo(websocket):
    async for message in websocket:
        # print ("debug message:", message);
        print ("obtained:", ":".join("{:02x}".format(ord(c)) for c in message));
        await websocket.send(message);


async def main():
    async with serve(echo, "127.0.0.1", 9999) as server:
        await server.serve_forever();


if __name__ == "__main__":
    asyncio.run(main());
