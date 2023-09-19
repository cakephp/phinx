<?php
declare(strict_types=1);

namespace Test\Phinx;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TestUtils
{
    /**
     * Recursive rmdir
     *
     * It will delete all files under the directory, and then the directory.
     *
     * @param string $path path to directory to delete
     */
    public static function recursiveRmdir(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (!is_dir($path)) {
            unlink($path);

            return;
        }
        $dir = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        $iter = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iter as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
    }
}
