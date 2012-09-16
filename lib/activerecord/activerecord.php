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

/**
 * Active Record faciliates the creation and use of business objects whose data require persistent
 * storage via database.
 *
 * @property Model $_model The model managing the active record.
 */
class ActiveRecord extends Object
{
	/**
	 * @var Model The model associated with the ActiveRecord.
	 */
	protected $_model;
	protected $_model_id;

	static public function resolve_class_name($module_id, $model_id='primary')
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
	 * Initialize the {@link $_model} and {@link $_model_id} properties.
	 *
	 * @param string|Model $model The model used to store the active record. A model
	 * object can be provided or a model id. If a model id is provided, the model object
	 * is resolved when the {@link $_model} property is accessed.
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
	 * Returns the model for the active record.
	 *
	 * This getter is used when the model has been provided as a string during construct.
	 *
	 * @return Model
	 */
	protected function get__model()
	{
		return \ICanBoogie\ActiveRecord\get_model($this->_model_id);
	}

	/**
	 * Saves the active record to the database using the active record model.
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
		 * We discard null values so that we don't have to define every properties before saving
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
	 * Deletes the active record from the database.
	 */
	public function delete()
	{
		$model = $this->_model;
		$primary = $model->primary;

		return $model->delete($this->$primary);
	}
}

namespace ICanBoogie\ActiveRecord;

/**
 * Returns the requested model.
 *
 * @param string $id Model identifier.
 *
 * @return Model
 */
function get_model($id)
{
	global $core;

	return $core->models[$id];
}

/**
 * Generic Active Record exception class.
 */
class ActiveRecordException extends \Exception
{

}