# Testing

Unit tests with [PHPUnit](https://phpunit.readthedocs.io/).


## How to run

To run all test, simply run in console.

```
make test
```


## Continuous integration

[Travis](https://travis-ci.org/Textalk/websocket-php) is run on PHP versions
`5.4`, `5.5`, `5.6`, `7.0`, `7.1`, `7.2`, `7.3` and `7.4`.

Code coverage by [Coveralls](https://coveralls.io/github/Textalk/websocket-php).


## Test strategy

Test set up overloads various stream and socket functions,
and use "scripts" to define and mock input/output of these functions.

This set up negates the dependency on running servers,
and allow testing various errors that might occur.
