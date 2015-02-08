# [Phinx](https://phinx.org): Simple PHP Database Migrations

[![Build Status](https://travis-ci.org/robmorgan/phinx.png?branch=0.2.x-dev)](https://travis-ci.org/robmorgan/phinx)
[![Build status](https://ci.appveyor.com/api/projects/status/9vag4892hfq6effr)](https://ci.appveyor.com/project/robmorgan/phinx)
[![Code Coverage](https://scrutinizer-ci.com/g/robmorgan/phinx/badges/coverage.png?s=9776e35b967f5adb0f4958bd72b617e0a9519f7d)](https://scrutinizer-ci.com/g/robmorgan/phinx/)
[![Latest Stable Version](https://poser.pugx.org/robmorgan/phinx/version.png)](https://packagist.org/packages/robmorgan/phinx)
[![Total Downloads](https://poser.pugx.org/robmorgan/phinx/d/total.png)](https://packagist.org/packages/robmorgan/phinx)

Phinx makes it ridiculously easy to manage the database migrations for your PHP app. In less than 5 minutes you can install Phinx and create your first database migration. Phinx is just about migrations without all the bloat of a database ORM system or framework.

**Check out http://docs.phinx.org for the comprehensive documentation.**

![phinxterm](https://cloud.githubusercontent.com/assets/178939/3887559/e6b5e524-21f2-11e4-8256-0ba6040725fc.gif)

### Features

* Write database migrations using database agnostic PHP code.
* Migrate up and down.
* Migrate on deployment.
* Get going in less than 5 minutes.
* Stop worrying about the state of your database.
* Take advantage of SCM features such as branching.
* Integrate with any app.

### Supported Adapters

Phinx natively supports the following database adapters:

* MySQL
* PostgreSQL
* SQLite
* Microsoft SQL Server

## Install & Run

### Composer

The fastest way to install Phinx in your project is using Composer (http://getcomposer.org/).

1. Install Composer:

    ```    
    curl -s https://getcomposer.org/installer | php
    ```
    
1. Require Phinx as a dependency using Composer: 

    ```
    php composer.phar require robmorgan/phinx
    ```
    
1. Install Phinx:
    
    ```
    php composer.phar install
    ```
    
1. Execute Phinx:
    
    ```
    php vendor/bin/phinx
    ```

### As a Phar

You can also use the Box application to build Phinx as a Phar archive (http://box-project.org/).

1. Clone Phinx from GitHub

    ```
    git clone git://github.com/robmorgan/phinx.git
    cd phinx
    ```

1. Install Composer

    ```    
    curl -s https://getcomposer.org/installer | php
    ```

1. Install the Phinx dependencies

    ```
    php composer.phar install
    ```

1. Install Box:

    ```
    curl -s http://box-project.org/installer.php | php
    ```
    
1. Create a Phar archive

    ```
    php box.phar build
    ```

## Documentation

Check out http://docs.phinx.org for the comprehensive documentation.

## Contributing

Please read the [CONTRIBUTING](CONTRIBUTING.md) document.

## News & Updates

Follow Rob (@\_rjm\_) on Twitter to stay up to date (http://twitter.com/_rjm_)
  
## Misc

### Version History

**0.4.2.1** (Saturday, 6th Feburary 2015)

* Proper release, updated docs

**0.4.2** (Friday, 6th Feburary 2015)

* Postgres support for `json` columns added
* MySQL support for `enum` and `set` columns added
* Allow setting `identity` option on columns
* Template configuration and generation made more extensible
* Created a base class for `ProxyAdapter` and `TablePrefixAdapter`
* Switched to PSR-4

**0.4.1** (Tuesday, 23rd December 2014)

* MySQL support for reserved words in hasColumn and getColumns methods
* Better MySQL Adapter test coverage and performance fixes
* Updated dependent Symfony components to 2.6.x

**0.4.0** (Sunday, 14th December 2014)

* Adding initial support for running Phinx via a web interface
* Support for table prefixes and suffixes
* Bugfix for foreign key options
* MySQL keeps column default when renaming columns
* MySQL support for tiny/medium and longtext columns added
* Changed SQL Server binary columns to varbinary
* MySQL supports table comments
* Postgres supports column comments
* Empty strings are now supported for default column values
* Booleans are now supported for default column values
* Fixed SQL Server default constraint error when changing column types
* Migration timestamps are now created in UTC
* Locked Symfony Components to 2.5.0
* Support for custom migration base classes
* Cleaned up source code formatting
* Migrations have access to the output stream
* Support for custom PDO connections when a PHP config
* Added support for Postgres UUID type
* Fixed issue with Postgres dropping foreign keys

**0.3.8** (Sunday, 5th October 2014)

* Added new CHAR & Geospatial column types
* Added MySQL unix socket support
* Added precision & scale support for SQL Server
* Several bug fixes for SQLite
* Improved error messages
* Overall code optimizations
* Optimizations to MySQL hasTable method

**0.3.7** (Tuesday, 12th August 2014)

* Smarter configuration file support
* Support for Postgres Schemas
* Fixed charset support for Microsoft SQL Server
* Fix for Unique indexes in all adapters
* Improvements for MySQL foreign key migration syntax 
* Allow MySQL column types with extra info
* Fixed SQLite autoincrement behaviour
* PHPDoc improvements
* Documentation improvements
* Unit test improvements
* Removing primary_key as a type

**0.3.6** (Sunday, 29th June 2014)

* Add custom adapter support
* Fix PHP 5.3 compatibility for SQL Server

**0.3.5** (Saturday, 21st June 2014)

* Added Microsoft SQL Server support
* Removed Primary Key column type
* Cleaned up and optimized many methods
* Updated Symfony dependencies to v2.5.0
* PHPDoc improvements

**0.3.4** (Sunday, 27th April 2014)

* Added support MySQL unsigned integer, biginteger, float and decimal types
* Added JSON output support for the status command
* Fix a bug where Postgres couldnt rollback foreign keys
* Moved Phinx type references to interface constants
* Fixed a bug with SQLite in-memory databases

**0.3.3** (Saturday, 22nd March 2014)

* Added support for JSON configuration
* Named index support for all adapters (thanks @archer308)
* Updated Composer dependencies
* Fix for SQLite Integer Type
* Fix for MySQL port option

**0.3.2** (Monday, 24th February 2014)

* Adding better Postgres type support

**0.3.1** (Sunday, 23rd February 2014)

* Adding MySQL charset support to the YAML config
* Removing trailing spaces

**0.3.0** (Sunday, 2nd February 2014)

* PSR-2 support
* Method to add timestamps easily to tables
* Support for column comments in the Postgres adapter
* Fixes for MySQL driver options
* Fixes for MySQL biginteger type

**0.2.9** (Saturday, 16th November 2013)

* Added SQLite Support
* Improving the unit tests, especially on Windows

**0.2.8** (Sunday, 25th August 2013)

* Added PostgresSQL Support

**0.2.7** (Saturday, 24th August 2013)

* Critical fix for a token parsing bug
* Removed legacy build system
* Improving docs

**0.2.6** (Saturday, 24th August 2013)

* Added support for environment vars in config files
* Added support for environment vars to set the Phinx Env
* Improving docs
* Fixed a bug with column names in indexes
* Changes for developers in regards to the unit tests

**0.2.5** (Sunday, 26th May 2013)

* Added support for Box Phar Archive Packaging
* Added support for MYSQL_ATTR driver options
* Fixed a bug where foreign keys cannot be removed
* Added support for MySQL table collation
* Updated Composer dependencies
* Removed verbosity options, now relies on Symfony instead
* Improved unit tests

**0.2.4** (Saturday, 20th April 2013)

* The Rollback command supports the verbosity parameter
* The Rollback command has more detailed output
* Table::dropForeignKey now returns the table instance

**0.2.3** (Saturday, 6th April 2013)

* Fixed a reporting bug when Phinx couldn't connect to a database
* Added support for the MySQL 'ON UPDATE' function
* Phinx timestamp is now mapped to MySQL timestamp instead of datetime
* Fixed a docs typo for the minimum PHP version
* Added UTF8 support for migrations
* Changed regex to handle migration names differently
* Added support for custom MySQL table engines such as MyISAM
* Added the change method to the migration template

**0.2.2** (Sunday, 3rd March 2013)

* Added a new verbosity parameter to see more output when migrating
* Support for PHP config files

**0.2.1** (Sunday, 3rd March 2013)

* Broken Release. Do not use!
* Unit tests no longer rely on the default phinx.yml file
* Running migrate for the first time does not give php warnings
* `default_migration_table` is now actually supported
* Updated docblocks to 2013.

**0.2.0** (Sunday, 13th January 2013)

* First Birthday Release
* Added Reversible Migrations
* Removed options parameter from AdapterInterface::hasColumn()

**0.1.7** (Tuesday, 8th January 2013)

* Improved documentation on the YAML configuration file
* Removed options parameter from AdapterInterface::dropIndex()

**0.1.6** (Sunday, 9th December 2012)

* Added foreign key support
* Removed PEAR support
* Support for auto_increment on custom id columns
* Bugfix for column default value 0
* Documentation improvements

**0.1.5** (Sunday, 4th November 2012)

* Added a test command
* Added transactions for adapters that support it
* Changing the Table API to use pending column methods
* Fixed a bug when defining multiple indexes on a table

**0.1.4** (Sunday, 21st October 2012)

* Documentation Improvements

**0.1.3** (Saturday, 20th October 2012)

* Fixed broken composer support

**0.1.2** (Saturday, 20th October 2012)

* Added composer support
* Now forces migrations to be in CamelCase format
* Now specifies the database name when migrating
* Creates the internal log table using its API instead of raw SQL

**0.1.1** (Wednesday, 13th June 2012)

* First point release. Ready for limited production use.

**0.1.0** (Friday, 13th January 2012)

* Initial public release.
  
### License

(The MIT license)

Copyright (c) 2014 Rob Morgan

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
