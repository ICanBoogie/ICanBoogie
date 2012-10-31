phar:
	@php -d phar.readonly=0 ./build/phar.php;

install:
	php composer.phar install
	
test:
	phpunit

docs:
	mkdir -p "docs"
	apigen \
	--source ./ \
	--destination docs/ --title ICanBoogie \
	--exclude "*/build/*" \
	--exclude "*/tests/*" \
	--exclude "*/composer/*" \
	--template-config /usr/share/php/data/ApiGen/templates/bootstrap/config.neon

clean:
	rm -fR docs
	rm -fR vendor