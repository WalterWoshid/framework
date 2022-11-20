<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Instrument\FileSystem;

use ArrayIterator;
use Closure;
use InvalidArgumentException;
use Iterator;
use LogicException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use UnexpectedValueException;

/**
 * Enumerates files in the concrete directory, applying filtration logic
 */
class Enumerator
{
    /**
     * Enumerator constructor
     *
     * @param string $rootDirectory Path to the root directory
     * @param array  $includePaths  List of additional include paths
     * @param array  $excludePaths  List of additional exclude paths
     */
    public function __construct(
        readonly private string $rootDirectory,
        readonly private array  $includePaths = [],
        readonly private array  $excludePaths = [],
    ) {}

    /**
     * Returns an enumerator for files
     *
     * @return Iterator
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function enumerate(): Iterator
    {
        $finder = new Finder();
        $finder->files()
            ->name('*.php')
            ->in($this->getInPaths());

        foreach ($this->getExcludePaths() as $path) {
            $finder->notPath($path);
        }

        $iterator = $finder->getIterator();

        // on Windows platform the default iterator is unable to rewind, not sure why
        if (str_starts_with(PHP_OS, 'WIN')) {
            $iterator = new ArrayIterator(iterator_to_array($iterator));
        }

        return $iterator;
    }

    /**
     * Returns a filter callback for enumerating files
     */
    public function getFilter(): Closure
    {
        $rootDirectory = $this->rootDirectory;
        $includePaths = $this->includePaths;
        $excludePaths = $this->excludePaths;

        return function (SplFileInfo $file) use ($rootDirectory, $includePaths, $excludePaths) {

            if ($file->getExtension() !== 'php') {
                return false;
            }

            $fullPath = $this->getFileFullPath($file);
            // Do not touch files that not under rootDirectory
            if (!str_starts_with($fullPath, $rootDirectory)) {
                return false;
            }

            if (!empty($includePaths)) {
                $found = false;
                foreach ($includePaths as $includePattern) {
                    if (fnmatch("$includePattern*", $fullPath, FNM_NOESCAPE)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return false;
                }
            }

            foreach ($excludePaths as $excludePattern) {
                if (fnmatch("$excludePattern*", $fullPath, FNM_NOESCAPE)) {
                    return false;
                }
            }

            return true;
        };
    }

    /**
     * Return the real path of the given file
     *
     * This is used for testing purpose with virtual file system.
     * In a vfs the 'realPath' method will always return false.
     * So we have a chance to mock this single function to return different path.
     */
    protected function getFileFullPath(SplFileInfo $file): string
    {
        // dump($file);
        return $file->getRealPath();
    }

    /**
     * Returns collection of directories to look at
     *
     * @throws UnexpectedValueException if directory not under the root
     */
    private function getInPaths(): array
    {
        $inPaths = [];

        foreach ($this->includePaths as $path) {
            if (!str_contains($path, $this->rootDirectory)) {
                throw new UnexpectedValueException(sprintf('Path %s is not in %s', $path, $this->rootDirectory));
            }

            $inPaths[] = $path;
        }

        if (empty($inPaths)) {
            $inPaths[] = $this->rootDirectory;
        }

        return $inPaths;
    }

    /**
     * Returns the list of excluded paths
     */
    private function getExcludePaths(): array
    {
        $excludePaths = [];

        foreach ($this->excludePaths as $path) {
            $path = str_replace('*', '.*', $path);
            $excludePaths[] = '#' . str_replace($this->rootDirectory . '/', '', $path) . '#';
        }

        return $excludePaths;
    }
}
