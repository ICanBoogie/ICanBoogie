# customization

PACKAGE_NAME = icanboogie/icanboogie
PACKAGE_VERSION = 4.0
PHPUNIT_VERSION=phpunit-5.7.phar
PHPUNIT_FILENAME=build/$(PHPUNIT_VERSION)
PHPUNIT=php $(PHPUNIT_FILENAME)

# do not edit the following lines

all: $(PHPUNIT) vendor

usage:
	@echo "test:  Runs the test suite.\ndoc:   Creates the documentation.\nclean: Removes the documentation, the dependencies and the Composer files."

vendor:
	@COMPOSER_ROOT_VERSION=$(PACKAGE_VERSION) composer install

update:
	@COMPOSER_ROOT_VERSION=$(PACKAGE_VERSION) composer update

autoload: vendor
	@composer dump-autoload

$(PHPUNIT):
	mkdir -p build
	wget https://phar.phpunit.de/$(PHPUNIT_VERSION) -O $(PHPUNIT_FILENAME)

test: vendor $(PHPUNIT)
	@$(PHPUNIT)

test-coverage: vendor
	@mkdir -p build/coverage
	@$(PHPUNIT) --coverage-html ../build/coverage

test-clover: vendor
	@$(PHPUNIT) --coverage-clover ../clover.xml

doc: vendor
	@mkdir -p build/docs
	@apigen generate \
	--source lib \
	--destination build/docs/ \
	--title "$(PACKAGE_NAME) v$(PACKAGE_VERSION)" \
	--template-theme "bootstrap"

clean:
	@rm -fR build
	@rm -fR vendor
	@rm -f composer.lock

.PHONY: all test test-coverage test-clover doc clean
