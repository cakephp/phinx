<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
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
 * @author  Andrey Filippov <afi.work@gmail.com>
 */

namespace Phinx\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * FOR MySQL ONLY!!!
 * This command displays all SQL queries which occured since the last migration.
 * The command reads a binary log and shows SQL queries, so you have to set 'binlog' option in 'paths' section
 * in your configuration file. The 'binlog' is a path to yor binary log file.
 *
 * for example:
 * paths:
 *     migrations: %%PHINX_CONFIG_DIR%%/migrations
 *     binlog: "/var/log/mysql"
 *
 * Ensure you have enabled binary logging on your MySQL server.
 * See http://dev.mysql.com/doc/refman/5.0/en/binary-log.html for details
 *
 * The command allows to display uniquie queries only as well. You should rut it
 * $phinx log --u
 *
 * You may need to clean existing binary log, so just run it with an option --d
 * $phinx log --d
 * Ensure your MySQL user has proper permissions.
 *
 * @package Phinx\Console\Command
 */
class Log extends AbstractCommand
{
	protected function configure()
	{
		parent::configure();

		$this->addOption('--environment', '-e', InputOption::VALUE_OPTIONAL, 'The target environment');
		$this->addOption('u', null, InputOption::VALUE_NONE, 'Get unique SQL queries only');
		$this->addOption('d', null, InputOption::VALUE_NONE, 'Clear binary log');

		$this->setName('log')
				->setDescription('FOR MySQL ONLY!!! Displays SQL queries which occured since the last migration')
				->setHelp("This command displays all SQL queries which occured since the last migration.");
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->bootstrap($input, $output);

		$environment = $input->getOption('environment');
		$unique = $input->getOption('u');
		$delete =  $input->getOption('d');

		if (null === $environment) {
			$environment = $this->getConfig()->getDefaultEnvironment();
			$output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
		} else {
			$output->writeln('<info>using environment</info> ' . $environment);
		}

		$envOptions = $this->getConfig()->getEnvironment($environment);
		$output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
		$output->writeln('<info>using database</info> ' . $envOptions['name']);

		if ("mysql" !== $envOptions['adapter'])
			throw new \InvalidArgumentException("The command delta can be used with MySQL only");

		if ($delete)
		{
			$this->manager->getEnvironment($environment)->getAdapter()->query("RESET MASTER");
			$output->writeln('<comment>All Done. You have cleaned bin log.</comment>');
			return;
		}

		// we need to set a moment which will be the beginning of our log
		// lets get it from the last migration timestamp

		$vers = $this->manager->getMigrations();
		$v = array_map(function($v){
			return $v->getVersion();
		}, $vers);

		if (count($vers) == 0)
		{
			$output->writeln('<fg=red>You have no any migrations yet.</fg=red>');
			return;
		}

		$v = array_keys($v);
		$v = max($v);
		$ts = strtotime($v);

		$resTs = $this->manager->getEnvironment($environment)->getAdapter()->query("SELECT FROM_UNIXTIME({$ts})");
		$ts = $resTs->fetch();
		$ts = $ts[0];

		$res = $this->manager->getEnvironment($environment)->getAdapter()->query("SHOW BINARY LOGS");
		$logFiles = $res->fetchAll();
		if (!count($logFiles))
		{
			$output->writeln('<comment>All Done. You have no any records in binlog</comment>');
			return;
		}

		$cnf = $this->manager->getConfig();
		$logPath = null;
		if (isset($cnf["paths"]["binlog"]))
			$logPath = $cnf["paths"]["binlog"];
		else
			throw new \InvalidArgumentException("You have to define paths:binlog option.");

		$logs = "";

		foreach ($logFiles as $log)
			$logs .= $logPath . DIRECTORY_SEPARATOR . $log['Log_name'] . " ";

		$dbName = $envOptions["name"];
		$command = "mysqlbinlog -s -d {$dbName} --start-datetime=\"{$ts}\" -t {$logs}" . "\n";
		exec($command, $q);
		$out = implode("\n", $q);

		preg_match("/DELIMITER\s(.*?)\n/is", $out, $result);

		$delimeter = $result[1];
		$queries = explode($delimeter, $out);
		$queries = $this->filterQueries($queries);

		if ($unique)
			$queries = $this->getUniqueQueries($queries);

		$strQueries = implode($delimeter . PHP_EOL, $queries);

		$output->writeln('<fg=green>=================================================================</fg=green>');
		$output->writeln('<fg=green>SQL LOG</fg=green>');
		$output->writeln('<fg=green>=================================================================</fg=green>');

		$output->writeln("<fg=green>{$strQueries}</fg=green>");
		$output->writeln('<fg=green>=================================================================</fg=green>');
		$output->writeln('<comment>All Done.</comment>');
	}

	public function filterQueries($queries)
	{
		$patters = array(
				"{/\*!.+?\*/;}is"    => "", // директивы вида /*!40019 SET ....*/;
				"/^\s*SET.*/is"      => "", // запросы SET ....*/;
				"/^\s*use.*/is"      => "", // запросы use...
				"{/\*!\\\C.+?\*/}is" => "", // запросы /*!\C utf8 */
				"/^\n*/is"           => "", // пустые строки в начале запроса
				"/\n*$/is"           => ""  // пустые строки в конце запроса
		);
		$res = preg_replace(array_keys($patters), array_values($patters), $queries);
		$res = array_filter($res);

		return $res;
	}

	public function getUniqueQueries($queries)
	{
		$qArray  = array();
		foreach ($queries as $q)
			$qArray[] = $q;

		$patters = array(
				"/\t+/is" => " ",
				"/\n+/is" => " ",
				"/\s+/is" => " "
		);
		$res = preg_replace(array_keys($patters), array_values($patters), $qArray);
		$res = array_map('strtolower', $res);
		$res = array_unique($res);

		$uniqueQueries = array();
		foreach ($res as $k => $v)
			$uniqueQueries[] = $qArray[$k];

		return $uniqueQueries;
	}
}

