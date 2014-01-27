<?php

namespace Phinx\Console\Command;

use Symfony\Component\Filesystem\Filesystem;

class AbstractSchemaCommand extends AbstractCommand
{
    protected function loadSchemaFilePath($migrationPath)
    {
        $schemaPath = $migrationPath.DIRECTORY_SEPARATOR.'schema';

        $fs = new Filesystem();
        if (!$fs->exists($schemaPath)) {
            if (!is_writeable($migrationPath)) {
                throw new \InvalidArgumentException(
                    sprintf('The directory "%s" is not writeable', $migrationPath)
                );
            }
            $fs->mkdir($schemaPath);
        }

        if (!is_writeable($schemaPath)) {
            throw new \InvalidArgumentException(
                sprintf('The directory "%s" is not writeable', $schemaPath)
            );
        }
        $schemaPath = realpath($schemaPath);
        $fileName = 'schema.php';

        return $schemaPath . DIRECTORY_SEPARATOR . $fileName;
    }
} 