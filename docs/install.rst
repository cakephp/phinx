.. index::
   single: Installation
   
Installation
============

Phinx should be installed using the PEAR installer. PEAR provides a
distribution system for PHP packages. Please visit the PEAR website for more
information.

.. note::

    Phinx requires at least PHP 5.2.1 (or later).

The following commands (which you may have to run as root) are all thats
required to install Phinx using the PEAR installer:

.. code-block:: bash

    pear channel-discover pear.symfony.com
	pear channel-discover pear.phinx.org
	pear install channel://pear.phinx.org/phinx-0.1.1

After the installation you can find the Phinx source files inside your local
PEAR directory; the path is usually /usr/lib/php/Phinx. Phinx will be
available under the ``phinx`` command.