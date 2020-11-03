# Websocket: Contributing

- [Client](Client.md)
- [Server](Server.md)
- [Changelog](Changelog.md)
- Contributing
- [License](COPYING.md)

### Contribution Requirements

Requirements on pull requests;
* All tests **MUST** pass.
* Code coverage **MUST** remain on 100%.
* Code **MUST** adhere to PSR-1 and PSR-12 code standards.

### Dependency management

Install or update dependencies using [Composer](https://getcomposer.org/).

```
# Install dependencies
make install

# Update dependencies
make update
```

### Code standard

This project uses [PSR-1](https://www.php-fig.org/psr/psr-1/) and [PSR-12](https://www.php-fig.org/psr/psr-12/) code standards.
```
# Check code standard adherence
make cs-check
```

### Unit testing

Unit tests with [PHPUnit](https://phpunit.readthedocs.io/).
```
# Run unit tests
make test
