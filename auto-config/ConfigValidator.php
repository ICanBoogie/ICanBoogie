<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\AutoConfig;

use Composer\Json\JsonFile;
use Composer\Json\JsonValidationException;
use JsonSchema\Validator;

/**
 * Validates data against the "icanboogie" schema.
 */
class ConfigValidator
{
	protected $schema;
	protected $validator;

	public function __construct()
	{
		$this->schema = json_decode(file_get_contents(__DIR__ . '/icanboogie-schema.json'));

		if (!$this->schema)
		{
			throw \Exception('Unable to load icanboogie schema.');
		}

		$this->validator = new Validator;
	}

	public function validate($pathname)
	{
		$json = file_get_contents($pathname);

		if (!$json)
		{
			throw new \Exception("Unable to read JSON from $pathname.");
		}

		JsonFile::parseJson($json, $pathname);

		$data = json_decode($json);

		$validator = $this->validator;
		$validator->check($data, $this->schema);

		if (!$validator->isValid())
		{
			$errors = '';

			foreach ((array) $validator->getErrors() as $error)
			{
				$errors .= "\n- " . ($error['property'] ? $error['property'] . ': ' : '') . $error['message'];
			}

			throw new \Exception("$pathname does not match the expected JSON schema:\n$errors");
		}

		return true;
	}
}