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

    $phinx = new Phinx($options);

``$phinx`` now contains our Phinx object which will expose to us the commands. 

``$options`` is optional.



Custom Options
---------------

Phinx comes with some sensible options set for us, however these are not always appropriate and may need changing.

When instantiating our Phinx object above, we can pass through 

.. code-block:: php

    $phinx = Phinx(array(
        'option_name' => 'option_value'
    ));

To set multiple options and values, simply add more key value pairs to the ``$options`` array.

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

    $phinx = Phinx(array(
        'environment' => 'development'
    ));

The default value if left blank is: ``development``.

Configuring Configuration File Location
----------------------------------------

Setting the Configuration File Location is done using the option name ``configuration``.
The value you assign should match the relative or absolute path to the phinx.yml (or alternative) file.

.. code-block:: php

    $phinx = Phinx(array(
        'configuration' => '/var/www/phinx.yml'
    ));

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

    $phinx = Phinx(array(
        'parser' => 'yaml'
    ));

.. note::

    If left blank, the value is assumed based on the Configuration File's file extension as per the list above.

Migrating
----------

This command will run the same command as ``phinx migrate``.

.. code-block:: php

    $phinx->migrate($target);

The parameter in this function is optional. 
``$target`` allows you to target a specific migration to run. It is the same as setting ``-t`` as an option in the command line.

This function will return a boolean based on its success.

Rollback
---------

This command will run the same command as ``phinx rollback``.

.. code-block:: php

    $phinx->rollback($target);

The parameter in this function is optional. 
``$target`` allows you to target a specific migration to run. It is the same as setting ``-t`` as an option in the command line.

This function will return a boolean based on its success.

Getting Command Output
-----------------------

This command will get the string output, for the last command ran, as you would by running the terminal commands.

.. code-block:: php

    $phinx->getOutput();

This function will return the same string that the terminal command returns.
