<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Operation\ActiveRecord;

use ICanBoogie\Module;
use ICanBoogie\Operation;

/**
 * Deletes a record.
 */
class Delete extends Operation
{
	/**
	 * Controls for the operation: permission(manage), record and ownership.
	 *
	 * @see ICanBoogie.Operation::__get_controls()
	 */
	protected function __get_controls()
	{
		return array
		(
			self::CONTROL_PERMISSION => Module::PERMISSION_MANAGE,
			self::CONTROL_RECORD => true,
			self::CONTROL_OWNERSHIP => true
		)

		+ parent::__get_controls();
	}

	protected function validate()
	{
		return true;
	}

	/**
	 * Delete the target record.
	 *
	 * @see ICanBoogie.Operation::process()
	 */
	protected function process()
	{
		$key = $this->key;

		if (!$this->module->model->delete($key))
		{
			wd_log_error('Unable to delete the record %key from %module.', array('%key' => $key, '%module' => (string) $this->module));

			return;
		}

		if ($this->request['#location'])
		{
			$this->location = $this->request['#location'];
		}

		wd_log_done('The record %key has been delete from %module.', array('%key' => $key, '%module' => $this->module->title), 'delete');

		return $key;
	}
}