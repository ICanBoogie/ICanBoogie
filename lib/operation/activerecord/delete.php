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

/**
 * Deletes a record.
 */
class DeleteOperation extends Operation
{
	/**
	 * Modifies the following controls:
	 *
	 *     PERMISSION: MANAGE
	 *     RECORD: true
	 *     OWNERSHIP: true
	 *
	 * @see ICanBoogie.Operation::get_controls()
	 */
	protected function get_controls()
	{
		return array
		(
			self::CONTROL_PERMISSION => Module::PERMISSION_MANAGE,
			self::CONTROL_RECORD => true,
			self::CONTROL_OWNERSHIP => true
		)

		+ parent::get_controls();
	}

	protected function validate(Errors $errors)
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
			throw new Exception
			(
				'Unable to delete the record %key from %module.', array
				(
					'key' => $key,
					'module' => $this->module->title
				)
			);
		}

		if ($this->request['#location'])
		{
			$this->response->location = $this->request['#location'];
		}

		$this->response->message = array('The record %key has been delete from %module.', array('key' => $key, 'module' => $this->module->title));

		return $key;
	}
}