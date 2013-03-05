/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

var ICanBoogie = {

	XHR: {

		NOTICE_DELAY: 200,

		showNotice: function()
		{
			window.fireEvent('icanboogie.xhr.shownotice', arguments)
		},

		hideNotice: function()
		{
			window.fireEvent('icanboogie.xhr.hidenotice', arguments)
		}
	}
}

/**
 * The Request.fireEvent() method is patched in order to display a notice when a XHR is taking too
 * long to complete.
 *
 * Usually the user only has to listen to the `icanboogie.xhr.shownotice` and
 * `icanboogie.xhr.hidenotice` to know when to show/hide a notice.
 *
 * XHR can be nested. The `icanboogie.xhr.hidenotice` is fire only when all the requests have
 * completed (or aborted). A new `icanboogie.xhr.shownotice` event can be fired only after a
 * `icanboogie.xhr.hidenotice` was fired.
 *
 * Event: icanboogie.xhr.request
 * -----------------------------
 *
 * The `icanboogie.xhr.request` event is fired when a XHR is issued.
 *
 * Event: icanboogie.xhr.complete
 * ------------------------------
 *
 * The `icanboogie.xhr.complete` event is fired when a XHR completed, successfully or not.
 *
 * Event: icanboogie.xhr.cancel
 * ----------------------------
 *
 * The `icanboogie.xhr.cancel` event is fired when a XHR was canceled.
 *
 * Event: icanboogie.xhr.shownotice
 * --------------------------------
 *
 * The `icanboogie.xhr.shownotice` event is fired when a notice should be displayed to inform the
 * user that a request is taking some time to perform.
 *
 * The event is only fired after some time, so that it can be canceled on a response/abort. This
 * delay is defined by the property `ICanBoogie.XHR.NOTICE_DELAY`.
 *
 * Event: icanboogie.xhr.hidenotice
 * --------------------------------
 *
 * The `icanboogie.xhr.hidenotice` event is fired when the notice should be hidden.
 */
!function() {

	var nativeFireEvent = Request.prototype.fireEvent
	, nesting = 0
	, timeout = null

	function showNoticeInterface()
	{
		timeout = null

		ICanBoogie.XHR.showNotice.apply(null, arguments)
	}

	Request.prototype.fireEvent = function(type) {

		if (type == 'request' || type == 'complete' || type == 'cancel')
		{
			var eventArguments = Array.prototype.slice.call(arguments, 1)

			eventArguments.unshift(this)

			window.fireEvent('icanboogie.xhr.' + type, eventArguments)
		}

		return nativeFireEvent.apply(this, arguments)
	}

	window.addEvent('icanboogie.xhr.request', function() {

		if (++nesting != 1) return

		timeout = showNoticeInterface.delay(ICanBoogie.XHR.NOTICE_DELAY, null, arguments)
	})

	window.addEvent('icanboogie.xhr.cancel', function() {

		if (--nesting != 0) return

		if (timeout)
		{
			clearTimeout(timeout)

			timeout = null
		}
	})

	window.addEvent('icanboogie.xhr.complete', function() {

		if (--nesting != 0) return

		if (timeout)
		{
			clearTimeout(timeout)

			timeout = null

			return
		}

		ICanBoogie.XHR.hideNotice.apply(null, arguments)
	})

} ()

!function() {

	var html = $(document.html)
	, apiBase = html.get('data-api-base')
	, methods = {}

	if (!apiBase)
	{
		apiBase = ''
	}

	apiBase += '/api/'

	;['patch', 'PATCH'].each(function(method) {

		methods[method] = function(data) {

			var object = { method: method }

			if (data != null) object.data = data

			return this.send(object)
		}
	})

	Request.implement(methods)

	/**
	 * Extends Request.JSON adding specific support to the ICanBoogie API.
	 */
	Request.API = new Class({

		Extends: Request.JSON,

		options:
		{
			link: 'cancel'
		},

		initialize: function(options)
		{
			var apiLanguage = html.get('data-user-lang') || html.get('lang')

			if (options.url.match(/^\/api\//))
			{
				options.url = options.url.substring(5)
			}

			options.url = apiBase + options.url

			if (apiLanguage)
			{
				options.url += (options.url.indexOf('?') == -1 ? '?' : '&') + 'hl=' + apiLanguage
			}

			this.parent(options)
		},

		onFailure: function()
		{
			var response = null

			try
			{
				response = JSON.decode(this.xhr.responseText)
			}
			catch (e)
			{
				alert('An error occured: ' + this.xhr.statusText)
			}

			this.fireEvent('complete').fireEvent('failure', [this.xhr, response])
		}
	})

	Request.API.encode = function(url, args) {

		if (url.match(/^\/api\//))
		{
			url = url.substring(5)
		}

		return apiBase + url
	}

} ()

Element.Properties.dataset = {

	get: function() {

		var dataset = {}
		, attributes = this.attributes
		, i
		, y
		, attr
		, name

		for (i = 0, y = attributes.length ; i < y ; i++)
		{
			attr = attributes[i];

			if (!attr.name.match(/^data-/))
			{
				continue;
			}

			name = attr.name.substring(5).camelCase()

			dataset[name] = attr.value
		}

		return dataset
	}
}