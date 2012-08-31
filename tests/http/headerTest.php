<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Tests\HTTP\Header;

use ICanBoogie\HTTP\Header;

class HeaderTest extends \PHPUnit_Framework_TestCase
{
	public function testDateTimeFromDateTime()
	{
		$datetime = new \DateTime();
		$header_datetime = new Header\DateTime($datetime);

		$this->assertEquals($datetime->format('D, d M Y H:i:s') . ' GMT', (string) $header_datetime);
	}

	public function testDateTimeFromDateTimeString()
	{
		$datetime = new \DateTime();

		$this->assertEquals($datetime->format('D, d M Y H:i:s') . ' GMT'
		, (string) new Header\DateTime($datetime->format('D, d M Y H:i:s P')));

		$this->assertEquals($datetime->format('D, d M Y H:i:s') . ' GMT'
		, (string) new Header\DateTime($datetime->format('D, d M Y H:i:s')));

		$this->assertEquals($datetime->format('D, d M Y H:i:s') . ' GMT'
		, (string) new Header\DateTime($datetime->format('Y-m-d H:i:s')));
	}

	public function testContentTypeObject()
	{
		$content_type = new Header\ContentType();

		$this->assertNull($content_type->type);
		$this->assertNull($content_type->charset);

		$content_type->type = 'text/html';
		$this->assertEquals('text/html', (string) $content_type);

		$content_type->charset = 'utf-8';
		$this->assertEquals('text/html; charset=utf-8', (string) $content_type);

		# if there is no `type` the string must be empty
		$content_type->type = null;
		$this->assertEquals('', (string) $content_type);
	}

	public function testContentType()
	{
		$header = new Header();

		$this->assertTrue($header['Content-Type'] instanceof Header\ContentType);
		$this->assertNull($header['Content-Type']->type);
		$this->assertNull($header['Content-Type']->charset);

		$header['Content-Type']->type = 'text/html';
		$this->assertEquals('text/html', (string) $header['Content-Type']);

		$header['Content-Type']->charset = 'utf-8';
		$this->assertEquals('text/html; charset=utf-8', (string) $header['Content-Type']);

		# if there is no `type` the string must be empty
		$header['Content-Type']->type = null;
		$this->assertEquals('', (string) $header['Content-Type']);

		$header['Content-Type'] = 'text/plain; charset=iso-8859-1';
		$this->assertEquals('text/plain', $header['Content-Type']->type);
		$this->assertEquals('iso-8859-1', $header['Content-Type']->charset);
	}

	public function testCacheControl()
	{
		$header = new Header();

		$this->assertTrue($header['Cache-Control'] instanceof Header\CacheControl);

		$header['Cache-Control']->cacheable = 'public';
		$this->assertEquals('public', (string) $header['Cache-Control']);

		$header['Cache-Control']->no_transform = true;
		$this->assertEquals('public, no-transform', (string) $header['Cache-Control']);

		$header['Cache-Control']->must_revalidate = false;
		$this->assertEquals('public, no-transform', (string) $header['Cache-Control']);

		$header['Cache-Control']->max_age = 3600;
		$this->assertEquals('public, max-age=3600, no-transform', (string) $header['Cache-Control']);

		$header['Cache-Control']->cacheable = null;
		$this->assertEquals('max-age=3600, no-transform', (string) $header['Cache-Control']);
	}
}