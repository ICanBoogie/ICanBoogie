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

return [

	AppConfig::CACHE_CATALOGS => false,
	AppConfig::CACHE_CONFIGS => false,
	AppConfig::CACHE_MODULES => false,

	AppConfig::STORAGE_FOR_CONFIGS => Hooks::class . '::create_storage_for_configs',
	AppConfig::STORAGE_FOR_VARS => Hooks::class . '::create_storage_for_vars',

	AppConfig::REPOSITORY => getcwd() . DIRECTORY_SEPARATOR . 'repository',
	AppConfig::REPOSITORY_TMP => '{repository}/tmp',
	AppConfig::REPOSITORY_CACHE => '{repository}/cache',
	AppConfig::REPOSITORY_CACHE_CONFIGS => '{repository}/cache/configs',
	AppConfig::REPOSITORY_FILES => '{repository}/files',
	AppConfig::REPOSITORY_VARS => '{repository}/var',

	AppConfig::ERROR_HANDLER => Debug::class. '::error_handler',
	AppConfig::EXCEPTION_HANDLER => Debug::class. '::exception_handler',

	AppConfig::SESSION => [

		SessionOptions::OPTION_NAME => 'ICanBoogie'

	]

];
