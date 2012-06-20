.. index::
   single: Writing Migrations
   
Creating a New Migration
========================

Creating a new migration is really straight forwards.

.. code-block:: bash
    
        phinx create MyNewMigration
		
This will create a new migration in the format
``YYYYMMDDHHMMSS_my_new_migration.php`` where the first 14 characters are
replaced with the current timestamp down to the second level.