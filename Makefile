install:
	@if [ ! -f "composer.phar" ] ; then \
		echo "Installing composer..." ; \
		curl -s https://getcomposer.org/installer | php ; \
	fi
	
	@php composer.phar install

test:
	@if [ ! -d "vendor" ] ; then \
		make install ; \
	fi

	@phpunit

doc:
	@if [ ! -d "vendor" ] ; then \
		make install ; \
	fi

	@mkdir -p "docs"

	@apigen \
	--source ./vendor/icanboogie/common \
	--source ./vendor/icanboogie/prototype \
	--source ./vendor/icanboogie/activerecord \
	--source ./vendor/icanboogie/event \
	--source ./ \
	--destination docs/ --title ICanBoogie \
	--exclude "*/tests/*" \
	--exclude "*/composer/*" \
	--template-config /usr/share/php/data/ApiGen/templates/bootstrap/config.neon

phar:
	@php -d phar.readonly=0 ./build/phar.php;
	
clean:
	@rm -fR docs
	@rm -fR vendor
	@rm -f composer.lock
	@rm -f composer.phar