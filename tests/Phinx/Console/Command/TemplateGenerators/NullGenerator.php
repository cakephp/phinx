<?php
namespace Test\Phinx\Console\Command\TemplateGenerators;

use Phinx\Templates\AbstractTemplateCreation;

class NullGenerator extends AbstractTemplateCreation
{
    /**
     * {@inheritdoc}
     */
    public function getTemplate()
    {
        // TODO: Implement getTemplate() method.
    }

    /**
     * {@inheritdoc}
     */
    public function postTemplateCreation($filename, $className, $baseClassName)
    {
        // TODO: Implement postTemplateCreation() method.
    }
}
