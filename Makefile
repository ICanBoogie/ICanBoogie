# customization

PACKAGE_NAME = icanboogie/icanboogie
PACKAGE_VERSION = 5.0
# we need a PHPUnit in a standalone package or it will trigger the autoload and mess with the constants.
PHPUNIT_VERSION = phpunit-7-4.phar
PHPUNIT = build/$(PHPUNIT_VERSION)

# do not edit the following lines

usage:
	@echo "test:  Runs the test suite.\ndoc:   Creates the documentation.\nclean: Removes the documentation, the dependencies and the Composer files."

vendor:
	@COMPOSER_ROOT_VERSION=$(PACKAGE_VERSION) composer install

update:
	@COMPOSER_ROOT_VERSION=$(PACKAGE_VERSION) composer update

# testing

test-dependencies: vendor $(PHPUNIT)

$(PHPUNIT):
	mkdir -p build
	wget https://phar.phpunit.de/$(PHPUNIT_VERSION) -O $(PHPUNIT)
	chmod +x $(PHPUNIT)

test-container:
	@docker-compose run --rm app sh
	@docker-compose down

test: test-dependencies
	@$(PHPUNIT)

test-coverage: test-dependencies
	@mkdir -p build/coverage
	@$(PHPUNIT) --coverage-html build/coverage --coverage-text

test-coveralls: test-dependencies
	@mkdir -p build/logs
	@$(PHPUNIT) --coverage-clover build/logs/clover.xml

#doc

doc: vendor
	@mkdir -p build/docs
	@apigen generate \
	--source lib \
	--destination build/docs/ \
	--title "$(PACKAGE_NAME) v$(PACKAGE_VERSION)" \
	--template-theme "bootstrap"

# utils

clean:
	@rm -fR build
	@rm -fR vendor
	@rm -f composer.lock

.PHONY: all autoload doc clean test test-coverage test-coveralls test-dependencies update
