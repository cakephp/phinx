# Upgrading Phinx to 0.8

* Phinx 0.8 allows for `phinx rollback` and `phinx status` to operate on migrations in reverse execution order,
rather than reverse creation order. To achieve this new ordering, you will need to add a new entry in your
`phinx.yml` file (or equivalent).  
  
  The setting is called `version_order` and supports 2 values:
  * `creation` - this is the default value and matches the standard behaviour of executing rollbacks in the
  reverse order based upon the creation datetime (also known as `version`).
  * `execution` - this is the new value and will execute rollbacks in the reverse order in which they were
  applied.

  This feature will be of most importance when development of migrations takes place in different branches
  within a codebase and are merged in to master for deployment. It will no longer matter when the migrations
  were created if it becomes necessary to rollback the migrations.

* Using an older version of Phinx on a pre 5.6 MySQL installation could lead to a case of an invalid table definition
for the `default_migration_table` (e.g. `phinxlog`) when the database was upgraded to 5.6. On upgrading, if you used
phinx on a pre 5.6 MySQL installation, it is suggested to do the following:

```
ALTER TABLE phinxlog MODIFY start_time timestamp NULL DEFAULT NULL;
ALTER TABLE phinxlog MODIFY end_time timestamp NULL DEFAULT NULL;
UPDATE phinxlog SET start_time = NULL WHERE start_time = '0000-00-00 00:00:00';
UPDATE phinxlog SET end_time = NULL WHERE end_time = '0000-00-00 00:00:00';
```
