install: composer.phar
	./composer.phar install

update:
	./composer.phar update

test: vendor/bin/phpunit build
	./vendor/bin/phpunit

cs-check: vendor/bin/phpunit
	./vendor/bin/phpcs --standard=codestandard.xml lib tests

coverage: vendor/bin/phpunit build
	./vendor/bin/phpunit --coverage-clover build/logs/clover.xml
	./vendor/bin/php-coveralls -v

composer.phar:
	curl -s http://getcomposer.org/installer | php

vendor/bin/phpunit: install

build:
	mkdir build

clean:
	rm composer.phar
	rm -r vendor
	rm -r build
