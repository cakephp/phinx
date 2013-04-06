.. index::
   single: Installation
   
Installation
============

Phinx should be installed using Composer. Composer is a tool for dependency
management in PHP. Please visit the `Composer <http://getcomposer.org/>`_ 
website for more information.

.. note::

    Phinx requires at least PHP 5.3.2 (or later).

To install Phinx simply add it as a dependency to your project's 
``composer.json`` file:

.. code-block:: javascript

    {
        "require": {
            "robmorgan/phinx": "*"
        }
    }

Then run Composer to update your packages:

.. code-block:: bash

    php composer.phar update

Phinx can now be executed from within your project:

.. code-block:: bash

    php vendor/bin/phinx