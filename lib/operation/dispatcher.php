<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;

/**
 * Dispatches operation requests.
 */
class OperationDispatcher implements \ICanBoogie\HTTP\IDispatcher
{
	/**
	 * Tries to create an {@link Operation} instance from the specified request. The operation
	 * is then executed and its response returned.
	 *
	 * If the operation returns an error response (client error or server error) and the resquest
	 * is not an XHR nor an API request, `null` is returned instead of the reponse to allow another
	 * controller to display an error message.
	 *
	 * If there is no response but the request is an API request, a 404 response is returned.
	 *
	 * @see ICanBoogie\HTTP.Controller::__invoke()
	 */
	public function __invoke(Request $request)
	{
		$operation = Operation::from($request);

		if (!$operation)
		{
			return;
		}

		$response = $operation($request);
		$is_api_operation = strpos(Route::decontextualize($request->path), '/api/') === 0;

		if ($response)
		{
			if (($response->is_client_error || $response->is_server_error) && !$request->is_xhr && !$is_api_operation)
			{
				return;
			}
		}
		else if ($is_api_operation)
		{
			return new Response(null, 404);
		}

		return $response;
	}

	public function rescue(\Exception $exception)
	{
		throw $exception;
	}
}