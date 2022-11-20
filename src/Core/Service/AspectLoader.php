<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Service;

use Go\Aop\Advisor;
use Go\Aop\Pointcut;
use Go\Core\Instrument\Singleton;
use Go\Core\KernelOptions;
use Go\Core\Loader\AspectLoaderExtension;
use Go\Core\Loader\GeneralAspectLoaderExtension;
use Go\Core\Loader\IntroductionAspectExtension;
use ReflectionClass;

use Symfony\Contracts\Cache\ItemInterface;

use function get_class;

/**
 * Loader of aspects into the container
 */
class AspectLoader
{
    use Singleton;

    /**
     * List of aspect loaders
     *
     * @var AspectLoaderExtension[]
     */
    protected array $loaders = [];

    /**
     * List of aspect class names that have been loaded
     *
     * @var string[]
     */
    protected array $loadedAspects = [];

    /**
     * Register AspectLoader
     *
     * @return void
     */
    public static function register(): void
    {
        $cacheAdapter = KernelOptions::getCacheAdapter();

        $instance = self::getInstance();
        $instance->registerLoaderExtension(new GeneralAspectLoaderExtension($cacheAdapter));
        $instance->registerLoaderExtension(new IntroductionAspectExtension($cacheAdapter));

        $instance->setInitialized();
    }

    /**
     * Register an aspect loader extension
     *
     * This method allows to extend the logic of aspect loading by registering an extension for loader.
     */
    protected function registerLoaderExtension(AspectLoaderExtension $loader): void
    {
        $this->loaders[] = $loader;
    }

    /**
     * Loads an aspect with the help of aspect loaders, but don't register it in the container
     *
     * @see loadAndRegister() method for registration
     *
     * @return Pointcut[]|Advisor[]
     */
    public function load(object $aspect): array
    {
        $cacheAdapter = KernelOptions::getCacheAdapter();
        $refAspect    = new ReflectionClass($aspect);

        // Load from cache or create
        $cacheAdapter->clear(); // todo: remove this line
        $loadedItems = $cacheAdapter->get(
            "aspect:{$refAspect->getName()}",
            function (ItemInterface $item) use ($aspect, $refAspect) {
                $item->tag('aspect');

                $items = [];

                foreach ($this->loaders as $loader) {
                    $items += $loader->load($aspect, $refAspect);
                }

                return $items;
            }
        );

        return $loadedItems;
    }

    /**
     * Loads and register all items of aspect in the container
     */
    public static function loadAndRegister(object $aspect): void
    {
        static $instance;
        if (!$instance) {
            $instance = self::getInitializedInstance();
        }

        $loadedItems = $instance->load($aspect);
        foreach ($loadedItems as $itemId => $item) {
            if ($item instanceof Pointcut) {
                $instance->registerPointcut($item, $itemId);
            }
            if ($item instanceof Advisor) {
                $instance->registerAdvisor($item, $itemId);
            }
        }
        $aspectClass = get_class($aspect);

        $instance->loadedAspects[$aspectClass] = $aspectClass;
    }

    /**
     * Returns list of unloaded aspects in the container
     *
     * @return object[]
     */
    public static function getUnloadedAspects(): array
    {
        static $instance;
        if (!$instance) {
            $instance = self::getInitializedInstance();
        }

        $unloadedAspects = [];

        foreach (GoAspectContainer::getAspects() as $aspect) {
            if (!isset($instance->loadedAspects[$aspect])) {
                $unloadedAspects[] = new $aspect;
            }
        }

        return $unloadedAspects;
    }
}
