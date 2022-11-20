<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * @author Valentin Wotschel <wotschel.valentin@googlemail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @noinspection PhpInternalEntityUsedInspection
 */
namespace Go\Core\Instrument;

use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Ast\Locator as AstLocator;
use Roave\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;

class ReflectionHelper
{
    use Singleton;

    private ?AstLocator $astLocator = null;

    /**
     * @param string $filePath
     * @return DefaultReflector
     */
    public static function findReflectorByPath(string $filePath): DefaultReflector
    {
        $instance = static::getInstance();
        $astLocator = $instance->getAstLocator();
        $reflector = new DefaultReflector(new SingleFileSourceLocator($filePath, $astLocator));
        return $reflector;
    }

    /**
     * Get ast locator
     *
     * @return AstLocator
     */
    private function getAstLocator(): AstLocator
    {
        if (!$this->astLocator) {
            $this->astLocator = (new BetterReflection)->astLocator();
        }

        return $this->astLocator;
    }

    /**
     * Create reflection from class name
     *
     * @param string $className
     * @return ReflectionClass
     */
    public static function createFromName(string $className): ReflectionClass
    {
        // class_exists is required to load the class into the reflection
        class_exists($className);
        return ReflectionClass::createFromName($className);
    }
}
