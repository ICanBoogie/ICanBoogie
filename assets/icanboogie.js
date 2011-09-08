/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

(function() {

	var api_base = $(document.html).get('data-api-base');

	if (!api_base)
	{
		api_base = '';
	}

	api_base += '/api/';

	/**
	 * Extends Request.JSON adding specific support to the ICanBoogie API.
	 */
	Request.API = new Class
	({
		Extends: Request.JSON,

		options:
		{
			link: 'cancel'
		},

		initialize: function(options)
		{
			if (options.url.match(/^\/api\//))
			{
				options.url = options.url.substring(5);
			}

			options.url = api_base + options.url;

			this.parent(options);
		}
	});

	Request.API.encode = function(url, args)
	{
		if (url.match(/^\/api\//))
		{
			url = url.substring(5);
		}

		return url = api_base + url;
	};

}) ();