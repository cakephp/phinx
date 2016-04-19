.. index::
   single: Installation

Installation
============

Phinx should be installed using Composer. Composer is a tool for dependency
management in PHP. Please visit the `Composer <https://getcomposer.org/>`_ 
website for more information.

.. note::

    Phinx requires at least PHP 5.3.2 (or later).

To install Phinx, simply require it using Composer:

.. code-block:: bash

    php composer.phar require robmorgan/phinx

Then run Composer:

.. code-block:: bash

    php composer.phar install --no-dev

Now publish the application ``config`` file

.. code-block:: bash

    php vendor/bin/phinx init

Open the file and enter your ``database credentials``

.. code-block:: bash

    vim phinx.yml

Publish the ``migrations`` and ``seeds`` directories

.. code-block:: bash

    php vendor/bin/phinx setup

You are now ready to create migrations so let's get started.