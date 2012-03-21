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
 * Accessor for the modules' models.
 */
class Models implements \ArrayAccess
{
	/**
	 * The modules accessor.
	 *
	 * @var Modules
	 */
	protected $modules;

	/**
	 * Loaded models.
	 *
	 * @var array[string]ActiveRecord\Model
	 */
	protected $models = array();

	/**
	 * Constructor.
	 *
	 * @param Modules $modules A modules accessor.
	 */
	public function __construct(Modules $modules)
	{
		$this->modules = $modules;
	}

	/**
	 * Checks if a model exists by first checking if the module it belongs to is enabled and that
	 * it actually defines the model.
	 *
	 * @see ArrayAccess::offsetExists()
	 *
	 * @return `true` if the model exists and is accessible, `false` otherwise.
	 */
	public function offsetExists($offset)
	{
		list($module_id, $model_id) = explode('/', $offset) + array(1 => 'primary');

		if (!isset($this->modules[$module_id]))
		{
			return false;
		}

		$descriptor = $this->modules->descriptors[$module_id];

		return isset($descriptor[Module::T_MODELS][$model_id]);
	}

	/**
	 * @see ArrayAccess::offsetSet()
	 *
	 * @throws Exception\PropertyNotWritable when an offset is set.
	 */
	public function offsetSet($offset, $value)
	{
		throw new Exception\PropertyNotWritable(array($property, $this));
	}

	/**
	 * @see ArrayAccess::offsetUnset()
	 *
	 * @throws Exception\PropertyNotWritable when an offset is unset.
	 */
	public function offsetUnset($offset)
	{
		throw new Exception\PropertyNotWritable(array($property, $this));
	}

	/**
	 * Gets the specified model of the specified module.
	 *
	 * The pattern used to request a model is "<module_id>[/<model_id>]" where "<module_id>" is
	 * the identifier of the module and "<model_id>" is the identifier of the module's model. The
	 * _model_id_ part is optionnal and defaults to "primary".
	 *
	 * @see ArrayAccess::offsetGet()
	 *
	 * @return ActiveRecord\Model The model for the specified offset.
	 */
	public function offsetGet($offset)
	{
		if (empty($this->models[$offset]))
		{
			list($module_id, $model_id) = explode('/', $offset) + array(1 => 'primary');

			$this->models[$offset] = $this->modules[$module_id]->model($model_id);
		}

		return $this->models[$offset];
	}
}