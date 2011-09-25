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
		},

		onFailure: function()
		{
			var response = JSON.decode(this.xhr.responseText);

			this.fireEvent('complete').fireEvent('failure', [this.xhr, response]);
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

	Element.Properties.dataset = {

		get: function() {

			var dataset = {};
			var attributes = this.attributes;

			for (var i = 0, y = attributes.length ; i < y ; i++)
			{
				var attr = attributes[i];

				if (!attr.name.match(/^data-/))
				{
					continue;
				}

				var name = attr.name.substring(5).camelCase();

				dataset[name] = attr.value;
			}

			return dataset;
		}
	};

}) ();