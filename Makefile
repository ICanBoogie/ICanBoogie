# customization

PACKAGE_NAME = icanboogie/icanboogie
# we need a PHPUnit in a standalone package or it will trigger the autoload and mess with the constants.
PHPUNIT_VERSION = phpunit-9-5.phar
PHPUNIT = build/$(PHPUNIT_VERSION)

# do not edit the following lines

.PHONY: usage
usage:
	@echo "test:  Runs the test suite.\ndoc:   Creates the documentation.\nclean: Removes the documentation, the dependencies and the Composer files."

vendor:
	@composer install

.PHONY: update
update:
	@composer update

test-dependencies: vendor $(PHPUNIT)

$(PHPUNIT):
	mkdir -p build
	curl -L https://phar.phpunit.de/$(PHPUNIT_VERSION) -o $(PHPUNIT)
	chmod +x $(PHPUNIT)

.PHONY: test
test: test-dependencies
	@$(PHPUNIT)

.PHONY: test-coverage
test-coverage: test-dependencies
	@mkdir -p build/coverage
	@$(PHPUNIT) --coverage-html ../build/coverage --coverage-text

.PHONY: test-coveralls
test-coveralls: test-dependencies
	@mkdir -p build/logs
	@$(PHPUNIT) --coverage-clover ../build/logs/clover.xml

.PHONY: test-container
test-container:
	@docker-compose run --rm app bash
	@docker-compose down -v

.PHONY: lint
lint:
	@phpcs -s
	@vendor/bin/phpstan

.PHONY: doc
doc: vendor
	@mkdir -p build/docs
	@apigen generate \
	--source lib \
	--destination build/docs/ \
	--title "$(PACKAGE_NAME)" \
	--template-theme "bootstrap"

.PHONY: clean
clean:
	@rm -fR build
	@rm -fR vendor
	@rm -f composer.lock
