<?php
declare(strict_types=1);

/**
 * This file is part of the Phinx package.
 *
 * (c) Rob Morgan <robbym@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phinx\Util\Util;

if (is_file('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
} else {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Ensure default test connection is defined
if (!getenv('SQLITE_DSN') && !getenv('MYSQL_DSN') && !getenv('PGSQL_DSN') && !getenv('SQLSRV_DSN')) {
    putenv('SQLITE_DSN=sqlite:///:memory:');
}

if (getenv('SQLITE_DSN')) {
    define('SQLITE_DB_CONFIG', Util::parseDsn(getenv('SQLITE_DSN')));
    define('DB_CONFIG', SQLITE_DB_CONFIG);
}

if (getenv('MYSQL_DSN')) {
    define('MYSQL_DB_CONFIG', Util::parseDsn(getenv('MYSQL_DSN')));
    define('DB_CONFIG', MYSQL_DB_CONFIG);
}

if (getenv('PGSQL_DSN')) {
    define('PGSQL_DB_CONFIG', Util::parseDsn(getenv('PGSQL_DSN')));
    define('DB_CONFIG', PGSQL_DB_CONFIG);
}

if (getenv('SQLSRV_DSN')) {
    define('SQLSRV_DB_CONFIG', Util::parseDsn(getenv('SQLSRV_DSN')));
    define('DB_CONFIG', SQLSRV_DB_CONFIG);
}
