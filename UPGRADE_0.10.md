# Upgrading Phinx to 0.10

*This upgrade guide is not complete*

* The up the down methods no longer have empty implementations in AbstractMigration 
you will need to implement these in your migrations if you do not already.

* If using multiple schemas in a PostgreSQL database you will need to fully qualify 
your phinx config default_migration_table. This is because when running a migration may change the 
current schema to one other than public, then when writing the phinxlog it will fail.
