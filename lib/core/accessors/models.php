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

use ICanBoogie\ActiveRecord\Connections;

/**
 * Models manager.
 *
 * Extends the ActiveRecord manager to handle module defined models.
 */
class Models extends \ICanBoogie\ActiveRecord\Models
{
	/**
	 * The modules accessor.
	 *
	 * @var Modules
	 */
	protected $modules;

	/**
	 * Initializes the {@link $modules} property.
	 *
	 * @param Connections $connections Connections manager.
	 * @param array $definitions Model definitions.
	 * @param Modules $modules Modules manager.
	 */
	public function __construct(Connections $connections, array $definitions, Modules $modules)
	{
		$this->modules = $modules;

		parent::__construct($connections, $definitions);
	}

	/**
	 * Checks if a model exists by first checking if the module it belongs to is enabled and that
	 * it actually defines the model.
	 *
	 * @param mixed $id
	 *
	 * @return bool
	 */
	public function offsetExists($id)
	{
		list($module_id, $model_id) = explode('/', $id) + array(1 => 'primary');

		if (!isset($this->modules[$module_id]))
		{
			return parent::offsetExists($id);
		}

		$descriptor = $this->modules->descriptors[$module_id];

		return isset($descriptor[Module::T_MODELS][$model_id]);
	}

	/**
	 * Gets the specified model of the specified module.
	 *
	 * The pattern used to request a model is `<module_id>[/<model_id>]` where `<module_id>` is
	 * the identifier of the module and `<model_id>` is the identifier of the module's model. The
	 * `<model_id>` part is optional and defaults to `primary`.
	 *
	 * @param mixed $id Identifier of the model.
	 *
	 * @return ActiveRecord\Model
	 */
	public function offsetGet($id)
	{
		if (isset($this->instances[$id]))
		{
			return $this->instances[$id];
		}

		list($module_id, $model_id) = explode('/', $id) + array(1 => 'primary');

		if (!isset($this->modules[$module_id]))
		{
			return parent::offsetGet($id);
		}

		return $this->instances[$id] = $this->modules[$module_id]->model($model_id);
	}
}