<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Data\Blueprints;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Helpers\Exif;
use Grav\Common\Page\Medium\AbstractMedia;
use Grav\Common\Iterator;
use Composer\Autoload\ClassLoader;

use Grav\Plugin\Schema\Article as ArticleSchema;
use Grav\Plugin\Schema\Event as EventSchema;
use Grav\Plugin\Schema\MusicAlbum as MusicAlbumSchema;
use Grav\Plugin\Schema\MusicEvent as MusicEventSchema;
use Grav\Plugin\Schema\Organization as OrganizationSchema;
use Grav\Plugin\Schema\Person as PersonSchema;
use Grav\Plugin\Schema\Product as ProductSchema;
use Grav\Plugin\Schema\Restaurant as RestaurantSchema;

class SchemaPlugin extends Plugin
{
    /**
     * Get subscribed events.
     * 
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100001],
                ['onPluginsInitialized', 1001]
            ],
            'onPageInitialized'    => ['onPageInitialized', 0],
        ];
    }

    /**
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    public function array_filter_recursive(array $array, callable $callback = null )
    {
        $array = is_callable($callback )? array_filter($array, $callback) : array_filter($array);

        foreach ($array as &$value)
        {
            if (is_array($value))
            {
                $value = $this->array_filter_recursive($value);
            }
        }

        return $array;
    }

    /**
     * Listen to event hooks.
     * 
     * @return void
     */
    public function onPluginsInitialized()
    {
        if (!$this->isAdmin())
        {
            $events = [
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            ];
        }
        else
        {
            $this->active = false;

            $events = [
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
                'onBlueprintCreated' => ['onBlueprintCreated', 0],
            ];
        }

        $this->enable($events);
    }

    /**
     * [onPageInitialized:0]
     *
     * @return void
     */
    public function onPageInitialized()
    {
        $page = $this->grav['page'];
        $header = $page->header();

        if (!property_exists($header, 'schema')) return;

        $schema = $header->schema;
        $schemaType = $schema['type'] ?? '0';

        if (!$schemaType) return;

        $className = 'Grav\\Plugin\\Schema\\' . $schemaType;

        if (!class_exists($className)) return;

        $schemaType = new $className();
        $data = $schemaType->generateStructuredData();

        $jsonScript = PHP_EOL . '<script type="application/ld+json">' . PHP_EOL . json_encode($data, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT ) . PHP_EOL . '</script>' . PHP_EOL;
        $outputJson = '</script>' . $jsonScript . '<script>';
        $this->grav['assets']->addInlineJs($outputJson, 100);
    }
    /**
     * [onBlueprintCreated:0]
     *
     * @param Event $event
     */
    public function onBlueprintCreated(Event $event)
    {
        if (strpos($event['type'], 'modular/') === 0)
        {
            return;
        }

        $blueprint = $event['blueprint'];

        if ($blueprint->get('form/fields/tabs', null, '/'))
        {
            $blueprints = new Blueprints(__DIR__ . '/blueprints/');
            $extends = $blueprints->get($this->name);
            $blueprint->extend($extends, true);
        }
    }

    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }
}
