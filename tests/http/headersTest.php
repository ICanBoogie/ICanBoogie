<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Tests\HTTP\Headers;

use ICanBoogie\HTTP\Headers;

class HeadersTest extends \PHPUnit_Framework_TestCase
{
	public function testDateTimeFromDateTime()
	{
		$datetime = new \DateTime();
		$headers_datetime = new Headers\DateTime($datetime);

		$this->assertEquals($datetime->format('D, d M Y H:i:s') . ' GMT', (string) $headers_datetime);
	}

	public function testDateTimeFromDateTimeString()
	{
		$datetime = new \DateTime();

		$this->assertEquals($datetime->format('D, d M Y H:i:s') . ' GMT'
		, (string) new Headers\DateTime($datetime->format('D, d M Y H:i:s P')));

		$this->assertEquals($datetime->format('D, d M Y H:i:s') . ' GMT'
		, (string) new Headers\DateTime($datetime->format('D, d M Y H:i:s')));

		$this->assertEquals($datetime->format('D, d M Y H:i:s') . ' GMT'
		, (string) new Headers\DateTime($datetime->format('Y-m-d H:i:s')));
	}

	public function testContentTypeObject()
	{
		$content_type = new Headers\ContentType();

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
		$headers = new Headers();

		$this->assertInstanceOf('ICanBoogie\HTTP\Headers\ContentType', $headers['Content-Type']);
		$this->assertNull($headers['Content-Type']->type);
		$this->assertNull($headers['Content-Type']->charset);

		$headers['Content-Type']->type = 'text/html';
		$this->assertEquals('text/html', (string) $headers['Content-Type']);

		$headers['Content-Type']->charset = 'utf-8';
		$this->assertEquals('text/html; charset=utf-8', (string) $headers['Content-Type']);

		# if there is no `type` the string must be empty
		$headers['Content-Type']->type = null;
		$this->assertEquals('', (string) $headers['Content-Type']);

		$headers['Content-Type'] = 'text/plain; charset=iso-8859-1';
		$this->assertEquals('text/plain', $headers['Content-Type']->type);
		$this->assertEquals('iso-8859-1', $headers['Content-Type']->charset);
	}

	public function testCacheControl()
	{
		$headers = new Headers();

		$this->assertInstanceOf('ICanBoogie\HTTP\Headers\CacheControl', $headers['Cache-Control']);

		$headers['Cache-Control']->cacheable = 'public';
		$this->assertEquals('public', (string) $headers['Cache-Control']);

		$headers['Cache-Control']->no_transform = true;
		$this->assertEquals('public, no-transform', (string) $headers['Cache-Control']);

		$headers['Cache-Control']->must_revalidate = false;
		$this->assertEquals('public, no-transform', (string) $headers['Cache-Control']);

		$headers['Cache-Control']->max_age = 3600;
		$this->assertEquals('public, max-age=3600, no-transform', (string) $headers['Cache-Control']);

		$headers['Cache-Control']->cacheable = null;
		$this->assertEquals('max-age=3600, no-transform', (string) $headers['Cache-Control']);
	}
}