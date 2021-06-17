<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Migration;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Migration interface
 *
 * @author Richard Quadling <RQuadling@GMail.com>
 */
interface CreationInterface
{
    /**
     * @param \Symfony\Component\Console\Input\InputInterface|null $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output Output
     */
    public function __construct(?InputInterface $input = null, ?OutputInterface $output = null);

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @return \Phinx\Migration\CreationInterface
     */
    public function setInput(InputInterface $input);

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @return \Phinx\Migration\CreationInterface
     */
    public function setOutput(OutputInterface $output);

    /**
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput();

    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput();

    /**
     * Get the migration template.
     *
     * This will be the content that Phinx will amend to generate the migration file.
     *
     * @return string The content of the template for Phinx to amend.
     */
    public function getMigrationTemplate();

    /**
     * Post Migration Creation.
     *
     * Once the migration file has been created, this method will be called, allowing any additional
     * processing, specific to the template to be performed.
     *
     * @param string $migrationFilename The name of the newly created migration.
     * @param string $className The class name.
     * @param string $baseClassName The name of the base class.
     * @return void
     */
    public function postMigrationCreation($migrationFilename, $className, $baseClassName);
}
