<?php
/*
 * This file is part of the Phinx package.
 *
 * (c) Rob Morgan <robbym@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Use the notation:
 *
 * defined(...) || define(...);
 *
 * This ensures that, when a test is marked to run in a separate process,
 * PHP will not complain of a constant already being defined.
 */

/**
 * Phinx_Db_Adapter_MysqlAdapter
 */
defined('TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED') || define('TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED', getenv('TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED'));
defined('TESTS_PHINX_DB_ADAPTER_MYSQL_HOST') || define('TESTS_PHINX_DB_ADAPTER_MYSQL_HOST', getenv('TESTS_PHINX_DB_ADAPTER_MYSQL_HOST'));
defined('TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME') || define('TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME', getenv('TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME'));
defined('TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD') || define('TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD', getenv('TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD'));
defined('TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE') || define('TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE', getenv('TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE'));
defined('TESTS_PHINX_DB_ADAPTER_MYSQL_PORT') || define('TESTS_PHINX_DB_ADAPTER_MYSQL_PORT', getenv('TESTS_PHINX_DB_ADAPTER_MYSQL_PORT'));