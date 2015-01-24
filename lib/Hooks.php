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

use ICanBoogie\Render\EngineCollection;
use ICanBoogie\Render\Renderer;
use ICanBoogie\Render\TemplateResolver;
use ICanBoogie\Routing\Routes;

class Hooks
{
    /*
     * Events
     */

    /**
     * Decorates the template resolver with an {@link ApplicationTemplateResolver} instance.
     *
     * @param TemplateResolver\AlterEvent $event
     * @param TemplateResolver $target
     */
    static public function alter_template_resolver(TemplateResolver\AlterEvent $event, TemplateResolver $target)
    {
        $event->replace_with(new ApplicationTemplateResolver($event->instance, get_autoconfig()['app-paths']));
    }

    /*
     * Prototypes
     */

    /**
     * Returns the route collection.
     *
     * @param Core $app
     *
     * @return Routes
     */
    static public function get_routes(Core $app)
    {
        $definitions = $app->configs['routes'];

        return new Routes($definitions);
    }

    /**
     * Returns an engine collection.
     *
     * @return EngineCollection
     */
    static public function get_template_engines()
    {
        return Render\get_engines();
    }

    /**
     * Returns a template resolver.
     *
     * @return TemplateResolver
     */
    static public function get_template_resolver()
    {
        return clone Render\get_template_resolver();
    }

    /**
     * @return Renderer
     */
    static public function get_renderer()
    {
        return clone Render\get_renderer();
    }
}
