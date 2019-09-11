<?php


namespace Ling\Light_Initializer\Util;


use Ling\Light\Core\Light;
use Ling\Light\Http\HttpRequestInterface;
use Ling\Light_Initializer\Exception\LightInitializerException;
use Ling\Light_Initializer\Initializer\LightInitializerInterface;
use Ling\ParentChild\ParentChildItem;

/**
 * The LightInitializerUtil class.
 *
 * Read the @page(initializer conception notes) for more details.
 */
class LightInitializerUtil
{


    /**
     * This property holds the initializer items.
     * It's an array of slotName => items.
     * And each item:
     *
     * - 0: LightInitializerInterface instance
     * - 1: parent
     *
     *
     * @var array
     */
    protected $initializers;


    /**
     *
     * This property holds the installTree for this instance.
     * It's an array of name => [ParentChildItem, LightInitializerInterface]
     * @var array
     */
    protected $installTree;


    /**
     * Builds the LightInitializer instance.
     */
    public function __construct()
    {
        $this->initializers = [];
        $this->installTree = [];
    }


    /**
     * Registers an initializer to this instance.
     *
     *
     * The slot should be either:
     * - install
     * - (null)
     *
     *
     * For more information about the slot and parent parameters, please read the @page(initializer conception notes).
     *
     *
     * @param LightInitializerInterface $initializer
     * @param string|null $slot
     * @param string|null $parent
     * The name of the parent plugin.
     *
     */
    public function registerInitializer(LightInitializerInterface $initializer, string $slot = null, string $parent = null)
    {
        if ('install' === $slot) {
            $name = $this->getPluginName($initializer);
            $item = new ParentChildItem();
            $item->setName($name);
            $this->installTree[$name] = [$item, $initializer];
        }


        //
        if (null === $slot) {
            $slot = 'default';
        }
        if (false === array_key_exists($slot, $this->initializers)) {
            $this->initializers[$slot] = [];
        }
        $this->initializers[$slot][] = [
            $initializer,
            $parent,
        ];


    }


    /**
     * Triggers the initialize method on all registered initializers.
     *
     * @param Light $light
     * @param HttpRequestInterface $httpRequest
     * @throws \Exception
     */
    public function initialize(Light $light, HttpRequestInterface $httpRequest)
    {
        $installItems = $this->initializers['install'] ?? [];
        $defaultItems = $this->initializers['default'] ?? [];



        /**
         * Preparing the dependency resolution
         */
        if ($installItems) {
            $tree = [];
            foreach ($installItems as $item) {
                list($initializer, $parent) = $item;
                $name = $this->getPluginName($initializer);
                /**
                 * @var $childItem ParentChildItem
                 */
                $childItem = $this->installTree[$name][0];
                if (null === $parent) {
                    $tree[] = $childItem;
                } else {
                    if (array_key_exists($parent, $this->installTree)) {
                        /**
                         * @var $parentItem ParentChildItem
                         */
                        $parentItem = $this->installTree[$parent][0];
                        $parentItem->addChild($childItem);

                    } else {
                        throw new LightInitializerException("Plugin dependency couldn't be resolved (plugin $name depending on $parent).");
                    }
                }
            }

            //--------------------------------------------
            // EXECUTING INSTALL SLOT
            //--------------------------------------------
            foreach ($tree as $item) {
                /**
                 * @var $item ParentChildItem
                 */
                $this->initializeItemRecursive($item, $light, $httpRequest);

            }
        }

        //--------------------------------------------
        // EXECUTING DEFAULT SLOT
        //--------------------------------------------
        $this->processItems($defaultItems, $light, $httpRequest);
    }

    /**
     * Process the given items.
     *
     * @param array $items
     * @param Light $light
     * @param HttpRequestInterface $httpRequest
     */
    protected function processItems(array $items, Light $light, HttpRequestInterface $httpRequest)
    {
        foreach ($items as $item) {
            list($initializer, $parent) = $item;
            /**
             * @var $initializer LightInitializerInterface
             */
            $initializer->initialize($light, $httpRequest);
        }
    }




    //--------------------------------------------
    //
    //--------------------------------------------
    /**
     * Returns a unique name for the given initializer.
     *
     * @param LightInitializerInterface $initializer
     * @return string
     */
    protected function getPluginName(LightInitializerInterface $initializer): string
    {
        $s = get_class($initializer);
        /**
         * The plugin should be named after the universe naming convention:
         *
         * - $galaxy\$planet\$whatever...
         *
         * With $planet = the plugin name
         *
         *
         */
        return explode('\\', $s)[1];
    }


    /**
     * Initializes all the children of an item recursively, then initializes the item.
     *
     * @param ParentChildItem $item
     * @param Light $light
     * @param HttpRequestInterface $httpRequest
     */
    protected function initializeItemRecursive(ParentChildItem $item, Light $light, HttpRequestInterface $httpRequest)
    {
        $children = $item->getChildren();
        if ($children) {
            foreach ($children as $child) {
                $this->initializeItemRecursive($child, $light, $httpRequest);
            }
        }

        /**
         * @var $initializer LightInitializerInterface
         */
        $initializer = $this->installTree[$item->getName()][1];
        $initializer->initialize($light, $httpRequest);
    }
}
