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
	 * @var Modules The modules accessor.
	 */
	protected $modules;

	/**
	 * @var array[string]ActiveRecord\Model Loaded models.
	 */
	protected $models = array();

	/**
	 * Constructor.
	 *
	 * @param Accessor\Modules $modules
	 */
	public function __construct(Modules $modules)
	{
		$this->modules = $modules;
	}

	/**
	 * Checks if a models exists by first checking if the module it belongs to is enabled and that
	 * the module actually defines the model.
	 *
	 * @see ArrayAccess::offsetExists()
	 * @return true if the model exists and is accessible, false otherwise.
	 */
	public function offsetExists($offset)
	{
		list($module_id, $model_id) = explode('/', $offset) + array(1 => 'primary');

		if (empty($this->modules[$module_id]))
		{
			return false;
		}

		$descriptor = $this->modules->descriptors[$module_id];

		return isset($descriptor[Module::T_MODELS][$model_id]);
	}

	/**
	 * The method is not implemented.
	 *
	 * @see ArrayAccess::offsetSet()
	 *
	 * @throws Exception if an offset is set.
	 */
	public function offsetSet($offset, $value)
	{
		throw new Exception\PropertyNotWritable(array($property, $this));
	}

	/**
	 * The method is not implemented.
	 *
	 * @see ArrayAccess::offsetUnset()
	 *
	 * @throws Exception if an offset is unset.
	 */
	public function offsetUnset($offset)
	{
		throw new Exception\PropertyNotWritable(array($property, $this));
	}

	/**
	 * Gets the specified model of the specified module.
	 *
	 * The pattern used to request a model is "<module_id>[/<model_id>]" where "<module_id>" is
	 * the identifier for the module and "<model_id>" is the identifier of the module's model. The
	 * _model_ part is optionnal, if it's not defined it defaults to "primary".
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