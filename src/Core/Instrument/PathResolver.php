<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Instrument;

use function array_map;
use function array_pop;
use function explode;
use function file_exists;
use function getcwd;
use function implode;
use function is_array;
use function stream_resolve_include_path;
use function str_replace;

/**
 * Special class for resolving path for different file systems, wrappers, etc
 *
 * @see http://stackoverflow.com/questions/4049856/replace-phps-realpath/4050444
 * @see http://bugs.php.net/bug.php?id=52769
 */
class PathResolver
{
    /**
     * Custom replacement for realpath() and stream_resolve_include_path()
     *
     * @param string|array $somePath Path without normalization or array of paths
     * @param bool $shouldCheckExistence Flag for checking existence of resolved filename
     *
     * @return string|array|bool
     */
    public static function realpath(string|array &$somePath, bool $shouldCheckExistence = false): string|array|bool
    {
        // Do not resolve empty string/false/arrays into the current path
        if (!$somePath) {
            return $somePath;
        }

        if (is_array($somePath)) {
            // Resolve each path in array
            return $somePath = array_map(
                function ($path) use ($shouldCheckExistence) {
                    return self::realpath($path, $shouldCheckExistence);
                },
                $somePath
            );
        }
        // Trick to get scheme name and path in one action. If no scheme, then there will be only one part
        $components = explode('://', $somePath, 2);
        [$pathScheme, $path] = isset($components[1]) ? $components : [null, $components[0]];

        // Optimization to bypass complex logic for simple paths (eg. not in phar archives)
        if (!$pathScheme && ($fastPath = stream_resolve_include_path($somePath))) {
            return $somePath = $fastPath;
        }

        $isRelative = !$pathScheme && ($path[0] !== '/') && ($path[1] !== ':');
        if ($isRelative) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
        }

        // resolve path parts (single dot, double dot and double delimiters)
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (str_contains($path, '.')) {
            $parts     = explode(DIRECTORY_SEPARATOR, $path);
            $absolutes = [];
            foreach ($parts as $part) {
                if ('.' === $part) {
                    continue;
                }
                if ('..' === $part) {
                    array_pop($absolutes);
                } else {
                    $absolutes[] = $part;
                }
            }
            $path = implode(DIRECTORY_SEPARATOR, $absolutes);
        }

        if ($pathScheme) {
            $path = "$pathScheme://$path";
        }

        if ($shouldCheckExistence && !file_exists($path)) {
            return false;
        }

        return $somePath = $path;
    }
}
