<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * @original_code https://github.com/rails/rails/blob/3-2-stable/activerecord/lib/active_record/migration.rb
 */
namespace ICanBoogie\ActiveRecord;

/**
 * Exception that can be raised to stop migrations from going backwards.
 */
class IrreversibleMigrationException extends ActiveRecordException
{

}

class DuplicateMigrationVersionException extends ActiveRecordException
{
	public function __construct($version, $code=500, \Exception $previous=null)
	{
		parent::__construct("Multiple migrations have the version number {$version}.");
	}
}

class DuplicateMigrationNameException extends ActiveRecordException
{
	public function __construct($name, $code=500, \Exception $previous=null)
	{
		parent::__construct("Multiple migrations have the name {$name}.");
	}
}

class UnknownMigrationVersionException extends ActiveRecordException
{
	public function __construct($version, $code=500, \Exception $previous=null)
	{
		parent::__construct("No migration with version number {$version}.");
	}
}

class IllegalMigrationNameException extends ActiveRecordException
{
	public function __construct($name, $code=500, \Exception $previous=null)
	{
		parent::__construct("Illegal name for migration file: {$name}. (only lower case letters, numbers, and '_' allowed).");
	}
}

class Migration extends \ICanBoogie\Object
{
	protected $name;

	protected $version;

	protected $connection;

	protected $reverting = false;

	public function __construct()
	{
		$this->name = get_class($this);
	}

	public function revert()
	{
		$this->reverting = true;
	}
}