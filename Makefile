# customization

PACKAGE_NAME = icanboogie/icanboogie
PACKAGE_VERSION = 4.0
PHPUNIT_VERSION=phpunit-5.7.phar
PHPUNIT=./build/$(PHPUNIT_VERSION)

# do not edit the following lines

usage:
	@echo "test:  Runs the test suite.\ndoc:   Creates the documentation.\nclean: Removes the documentation, the dependencies and the Composer files."

vendor:
	@COMPOSER_ROOT_VERSION=$(PACKAGE_VERSION) composer install

update:
	@COMPOSER_ROOT_VERSION=$(PACKAGE_VERSION) composer update

autoload: vendor
	@composer dump-autoload

$(PHPUNIT):
	wget https://phar.phpunit.de/$(PHPUNIT_VERSION) -O $(PHPUNIT)
	chmod +x $(PHPUNIT)

test: vendor $(PHPUNIT)
	@$(PHPUNIT)

test-coverage: vendor
	@mkdir -p build/coverage
	@$(PHPUNIT) --coverage-html ../build/coverage

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
