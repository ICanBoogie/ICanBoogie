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
	protected $_model_id;

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
	 *
	 * @param string|Model $model The model used to store the active record. A model
	 * object can be provided as well as a model id. If a model id is provied, the model object is
	 * resolved when the `_model` magic property is accessed.
	 */
	public function __construct($model)
	{
		if (is_string($model))
		{
			unset($this->_model);
			$this->_model_id = $model;
		}
		else
		{
			$this->_model = $model;
			$this->_model_id = $model->id;
		}
	}

	/**
	 * Removes the '_model' property before serialization.
	 *
	 * @return array
	 */
	public function __sleep()
	{
		$keys = parent::__sleep();

		unset($keys['_model']);

		return $keys;
	}

	/**
	 * Returns the model for the active record.
	 *
	 * This getter is used when the model has been provided as a string during construct.
	 *
	 * @return Model
	 */
	protected function __get__model()
	{
		global $core;

		return $core->models[$this->_model_id];
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