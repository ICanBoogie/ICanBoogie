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

use ICanBoogie\ActiveRecord\Model;

class ActiveRecord extends Object
{
	/**
	 * @var Model The model associated with the ActiveRecord.
	 */
	protected $_model;

	public static function resolve_class_name($module_id, $model_id='primary')
	{
		$class = __CLASS__ . '\\' . normalize_namespace_part($module_id);

		if ($model_id != 'primary')
		{
			$class .= '\\' . normalize_namespace_part($model_id);
		}

		$class = singularize($class);

		return $class;
	}

	/**
	 * Constructor.
	 *
	 * The constructor function is required when retrieving rows as objects.
	 */
	public function __construct(Model $model)
	{
		$this->_model = $model;
	}

	/**
	 * Saves the activerecord to the database using the activerecord model.
	 *
	 * @return int|bool the primary key value of the record, or false if the record could not be
	 * saved.
	 */
	public function save()
	{
		$model = $this->_model;
		$primary = $model->primary;

		$properties = get_object_vars($this);
		$key = null;

		if (isset($properties[$primary]))
		{
			$key = $properties[$primary];

			unset($properties[$primary]);
		}

		/*
		 * We discart null values so that we don't have to define every properties before saving
		 * our active record.
		 *
		 * FIXME-20110904: we should check if the schema allows the column value to be null
		 */

		foreach ($properties as $identifier => $value)
		{
			if ($value !== null)
			{
				continue;
			}

			unset($properties[$identifier]);
		}

		return $model->save($properties, $key);
	}

	/**
	 * Deletes the activerecord from the database.
	 */
	public function delete()
	{
		$model = $this->_model;
		$primary = $model->primary;

		return $model->delete($this->$primary);
	}
}