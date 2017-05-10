<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Console
 */

namespace Phinx\Console\Command;

use Symfony\Component\Console\Input\InputOption;

/**
 * Abstract create command, used for creating migrations, repeatable migrations and seeds.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class AbstractCreateCommand extends AbstractCommand
{
    /**
     * The name of the interface that any external template creation class is required to implement.
     */
    const CREATION_INTERFACE = 'Phinx\Templates\TemplateCreationInterface';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();

        // An alternative template.
        $this->addOption(
            'template',
            't',
            InputOption::VALUE_REQUIRED,
            'Use an alternative template'
        );

        // A classname to be used to gain access to the template content as well as the ability to
        // have a callback once the migration file has been created.
        $this->addOption(
            'class',
            'l',
            InputOption::VALUE_REQUIRED,
            'Use a class implementing "'.self::CREATION_INTERFACE.'" to generate the template'
        );

        // Allow the migration path to be chosen non-interactively.
        $this->addOption(
            'path',
            null,
            InputOption::VALUE_REQUIRED,
            sprintf(
                'Specify the path in which to %s',
                lcfirst($this->getDescription())
            )
        );

    }
}
