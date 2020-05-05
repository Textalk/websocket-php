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

The unit tests rely on overloading certain socket and stream functions in PHP.
The method parametres and returns are perofrmed as "scripts", defined as JSON files.

This set up is somewhat complex, but allow testing without actual servers and streams active.
It is also possible to mock various failures and unexpected results.