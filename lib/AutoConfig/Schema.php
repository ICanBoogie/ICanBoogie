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
use JsonSchema\Validator;

/**
 * A JSON schema.
 *
 * Used to validate other JSON files.
 */
class Schema
{
	static public function read_json($pathname)
	{
		$json = file_get_contents($pathname);

		JsonFile::parseJson($json, $pathname);

		return json_decode($json);
	}

	/**
	 * Schema.
	 *
	 * @var mixed
	 */
	protected $schema;

	/**
	 * Validator.
	 *
	 * @var Validator
	 */
	protected $validator;

	/**
	 * Initialize the {@link schema} and {@link validator} properties.
	 *
	 * @param string $pathname The pathname to the schema file.
	 */
	public function __construct($pathname)
	{
		$this->schema = self::read_json($pathname);
		$this->validator = new Validator;
	}

	/**
	 * Validate some data against the schema.
	 *
	 * @param mixed $data Data to validate.
	 * @param string $pathname The pathname to the file where the data is defined.
	 *
	 * @throws \Exception when the data is not valid.
	 *
	 * @return boolean `true` if the data is valid.
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

			throw new \Exception("$pathname does not match the expected JSON schema:\n$errors");
		}

		return true;
	}

	/**
	 * Validate a JSON file against the schema.
	 *
	 * @param string $pathname The pathname to the JSON file to validate.
	 *
	 * @see validate()
	 */
	public function validate_file($pathname)
	{
		$data = self::read_json($pathname);

		return $this->validate($data, $pathname);
	}
}
