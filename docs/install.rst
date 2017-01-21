.. index::
   single: Installation

Installation
============

Phinx should be installed using Composer, which is a tool for dependency
management in PHP. Please visit the `Composer <https://getcomposer.org/>`_ 
website for more information.

.. note::

    Phinx requires at least PHP 5.4 (or later).

To install Phinx, simply require it using Composer:

.. code-block:: bash

    php composer.phar require robmorgan/phinx

Create a folder in your project directory called ``migrations`` with adequate permissions.
It is where your migration files will live and should be writable.

Phinx can now be executed from within your project:

.. code-block:: bash

    vendor/bin/phinx init
