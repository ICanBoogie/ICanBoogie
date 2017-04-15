<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Autoconfig;

use Composer\Json\JsonFile;
use JsonSchema\Validator;

/**
 * A JSON schema.
 *
 * Used to validate other JSON files.
 *
 * @codeCoverageIgnore
 */
class Schema
{
	/**
	 * Read schema data from a JSON file.
	 *
	 * @param string $pathname
	 *
	 * @return mixed
	 */
	static public function read($pathname)
	{
		$json = file_get_contents($pathname);

		JsonFile::parseJson($json, $pathname);

		return json_decode($json);
	}

	/**
	 * @param mixed $data
	 *
	 * @return mixed
	 */
	static public function normalize_data($data)
	{
		if ($data && is_array($data))
		{
			array_walk($data, function (&$data) {
				$data = self::normalize_data($data);
			});

			reset($data);
			$key = key($data);

			if (is_scalar($key) && !is_numeric($key))
			{
				$data = (object) $data;
			}
		}

		return $data;
	}

	/**
	 * Schema.
	 *
	 * @var mixed
	 */
	private $schema;

	/**
	 * Validator.
	 *
	 * @var Validator
	 */
	private $validator;

	/**
	 * @param mixed $data Schema data as returned by {@link read()}.
	 */
	public function __construct($data)
	{
		$this->schema = $data;
		$this->validator = new Validator;
	}

	/**
	 * Validate some data against the schema.
	 *
	 * @param mixed $data Data to validate.
	 * @param string $pathname The pathname to the file where the data is defined.
	 *
	 * @throws \Exception when the data is not valid.
	 */
	public function validate($data, $pathname)
	{
		$validator = $this->validator;

		$validator->check($data, $this->schema);

		if (!$validator->isValid())
		{
			$errors = '';

			foreach ((array) $validator->getErrors() as $error)
			{
				$errors .= "\n- " . ($error['property'] ? $error['property'] . ': ' : '') . $error['message'];
			}
var_dump($data);
			throw new \Exception("`$pathname` does not match the expected JSON schema:\n$errors");
		}
	}

	/**
	 * Validate a JSON file against the schema.
	 *
	 * @param string $pathname The pathname to the JSON file to validate.
	 *
	 * @throws \Exception when the data is not valid.
	 *
	 * @see validate()
	 */
	public function validate_file($pathname)
	{
		$data = self::read($pathname);

		$this->validate($data, $pathname);
	}
}
