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
use InvalidArgumentException;
use JsonSchema\Validator;
use RuntimeException;
use Throwable;

use function array_walk;
use function file_get_contents;
use function is_array;
use function is_numeric;
use function is_scalar;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function key;
use function reset;

/**
 * A JSON schema.
 *
 * Used to validate other JSON files.
 *
 * @codeCoverageIgnore
 */
final class Schema
{
	/**
	 * Read schema data from a JSON file.
	 */
	static public function read(string $pathname): object
	{
		$json = file_get_contents($pathname);

		assert(is_string($json));

		JsonFile::parseJson($json, $pathname);

		$decoded = json_decode($json);

		if (json_last_error()) {
			throw new RuntimeException(json_last_error_msg());
		}

		return $decoded;
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
	 * @param object $schema Schema data as returned by {@link read()}.
	 */
	public function __construct(object $schema)
	{
		$this->schema = $schema;
		$this->validator = new Validator;
	}

	/**
	 * Validate some data against the schema.
	 *
	 * @param mixed $data Data to validate.
	 * @param string $pathname The pathname to the file where the data is defined.
	 *
	 * @throws Throwable when the data is not valid.
	 */
	public function validate($data, string $pathname): void
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

			throw new InvalidArgumentException("`$pathname` does not match the expected JSON schema:\n$errors");
		}
	}

	/**
	 * Validate a JSON file against the schema.
	 *
	 * @param string $pathname The pathname to the JSON file to validate.
	 *
	 * @throws Throwable when the data is not valid.
	 *
	 * @see validate()
	 */
	public function validate_file(string $pathname): void
	{
		$data = self::read($pathname);

		$this->validate($data, $pathname);
	}
}
