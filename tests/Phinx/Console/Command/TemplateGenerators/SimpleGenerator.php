<?php
namespace Test\Phinx\Console\Command\TemplateGenerators;

use Phinx\Templates\AbstractTemplateCreation;

class SimpleGenerator extends AbstractTemplateCreation
{
    /**
     * {@inheritdoc}
     */

    public function getTemplate()
    {
        return 'useClassName $useClassName / className $className / version $version / baseClassName $baseClassName';
    }

    /**
     * {@inheritdoc}
     */
    public function postTemplateCreation($filename, $className, $baseClassName)
    {
    }
}
