.. index::
   single: Phinx via Code

Calling Phinx via Code
===================================

Phinx is primarily a command line tool, however Phinx does support code-based calls.

.. warning::

    Not all terminal commands are available to be used within code yet.

Initial Setup
--------------

.. note::

    For Phinx via Code to work at all, you need to ensure that Phinx is accessible via autoloading,
    the best way to manage this would be to ``require "vendor/autoload.php";`` the composer autoloader.

Phinx will require instantiating, which can be achieved by the following.

.. code-block:: php

    $app = new \Phinx\Console\PhinxApplication($version);
    $phinx = new \Phinx\Wrapper\TextWrapper($app);

Where ``$version`` is the version number of Phinx (eg. '0.4.1').
``$phinx`` now contains our Phinx object which will expose to us the commands.



Custom Options
---------------

Phinx comes with some sensible options set for us, however these are not always appropriate and may need changing.

There are two ways an option value can be set.

The first way is instead of the ``$phinx`` declaration above, we can pass through a second parameter ``$options`` as an array of options.

.. code-block:: php

    $phinx = \Phinx\Wrapper\TextWrapper($app, array(
        'option_name' => 'option_value',
    ));

To set multiple options and values, simply add more key value pairs to the ``$options`` array.

The second way uses the original ``$phinx`` object and uses the ``setOption($option, $value)`` method.

This method can be invoked multiple times, and either chained together or with each invokation done separately.

.. code-block:: php

    $phinx->setOption('option_name', 'option_value');

The three options we can alter are:

1. Environment name
2. Configuration File Location
3. Parser Information

.. note::

    There is no need to define these options if they are the same as the defaults.

Configuring Environment Name
-----------------------------

Setting the Environment Name is done using the option name ``environment``.
The value you assign should match the config file environment declared (eg. ``development``)

.. code-block:: php

    $phinx->setOption('environment', 'development');

The default value if left blank is: ``development``.

Configuring Configuration File Location
----------------------------------------

Setting the Configuration File Location is done using the option name ``configuration``.
The value you assign should match the relative or absolute path to the phinx.yml (or alternative) file.

.. code-block:: php

    $phinx->setOption('configuration', '/var/www/phinx.yml');

.. note::

    The location may be relative to the webroot if you use an MVC framework.
    Using ``__DIR__`` for relative paths or an absolute path is recommended.

The default value if left blank is: ``./phinx.yml``.

Configuring Parser Information
-------------------------------

Setting the Parser Information is done using the option name ``parser``.
The value you assign should match the file format of the Configuration File.

Possible values are:

* yaml (.yml files)
* php (.php files)
* json (.json files)

.. code-block:: php

    $phinx->setOption('parser', 'yaml');

.. note::

    If left blank, the value is assumed based on the Configuration File's file extension as per the list above.

Migrating
----------

This command will run the same command as ``phinx migrate``.

.. code-block:: php

    $phinx->getMigrate($env, $target);

The parameters in this function are both optional. ``$env`` is the environment to use.
Setting this value **will override** the environment set above.
``$target`` allows you to target a specific migration to run. It is the same as setting ``-t`` as an option in the command line.
If you wish to set the ``$target`` but not ``$env``, set ``$env`` to ``null``.

This function will return the same string that the terminal command returns.

Rollback
---------

This command will run the same command as ``phinx rollback``.

.. code-block:: php

    $phinx->getRollback($env, $target);

The parameters in this function are both optional. ``$env`` is the environment to use.
Setting this value **will override** the environment set above.
``$target`` allows you to target a specific migration to run. It is the same as setting ``-t`` as an option in the command line.
If you wish to set the ``$target`` but not ``$env``, set ``$env`` to ``null``.

This function will return the same string that the terminal command returns.

Status
-------

This command will run the same command as ``phinx status``.

.. code-block:: php

    $phinx->getStatus($env);

The parameters in this function are both optional. ``$env`` is the environment to use.
Setting this value **will override** the environment set above.

This function will return the same string that the terminal command returns.

Determining Success
--------------------

Success of a command can be determined by getting the exit code.

.. code-block:: php

    $phinx->getExitCode();

.. note::

    This will return the most recent exit code.

If the exit code is ``0``, the command was successful.

If the exit code is ``> 0``, then the command was unsuccessful.