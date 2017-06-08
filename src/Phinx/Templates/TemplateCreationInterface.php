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
 * @subpackage Phinx\Migration
 */
namespace Phinx\Templates;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Template creation interface
 *
 * @author Richard Quadling <RQuadling@GMail.com>
 */
interface TemplateCreationInterface
{
    /**
     * TemplateCreationInterface constructor.
     *
     * @param InputInterface|null  $input
     * @param OutputInterface|null $output
     */
    public function __construct(InputInterface $input = null, OutputInterface $output = null);

    /**
     * @param InputInterface $input
     *
     * @return TemplateCreationInterface
     */
    public function setInput(InputInterface $input);

    /**
     * @param OutputInterface $output
     *
     * @return TemplateCreationInterface
     */
    public function setOutput(OutputInterface $output);

    /**
     * @return InputInterface
     */
    public function getInput();

    /**
     * @return OutputInterface
     */
    public function getOutput();

    /**
     * Get the template.
     *
     * This will be the content that Phinx will amend to generate the template file.
     *
     * @return string The content of the template for Phinx to amend.
     */
    public function getTemplate();

    /**
     * Post Template Creation.
     *
     * Once the template file has been created, this method will be called, allowing any additional
     * processing, specific to the template to be performed.
     *
     * @param string $filename      The name of the newly created file.
     * @param string $className     The class name.
     * @param string $baseClassName The name of the base class.
     *
     * @return void
     */
    public function postTemplateCreation($filename, $className, $baseClassName);
}
