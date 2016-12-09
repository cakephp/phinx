<?php

trait CdcMigration
{
	private $db_name = '';

	/**
	 * CDC method types.
	 */
	private $events = array(
		'ins' => 'INSERT',
		'upd' => 'UPDATE',
		'del' => 'DELETE'
	);

	/**
	 * CDC db name.
	 */
	private $cdc_db = 'cdc';

	/**
	 * Whether or not to output script-generated SQL to a file during the migration. (For up() method only.)
	 */
	private $create_sql_file = false;

	/**
	 * Name of the file for SQL output.
	 */
	private $sql_filename = 'tmp/cdc_create_';

	/**
	 * Migrate Up.
	 */
	public function createCDC($params=array())
	{
		if (! isset($params['db_source'])) {
			return;
		} else {
			$db_source = $params['db_source'];
		}
		isset($params['db_source']) ? $ignore_prefixes = $params['ignore_prefixes'] : $ignore_prefixes = array();
		isset($params['ignore_tables']) ? $ignore_tables = $params['ignore_tables'] : $ignore_tables = array();
		isset($params['ignore_triggers']) ? $ignore_triggers = $params['ignore_triggers'] : $ignore_triggers = array();

		// get the list of tables currently in the CDC db and save it for use during rollback...
		$existing_cdc_tables = $this->getTableNames($this->cdc_db);

		// get the triggers from the source dbs...
		$triggers = $this->getTriggers($this->db_name);

		// make a master list of these db objects and their types to save db state prior to migration...
		$existing_db_objs = array();
		foreach ($existing_cdc_tables as $table) {
			$existing_db_objs[$table] = 'table';
		}
		foreach ($triggers as $trigger) {
			$existing_db_objs[$trigger] = 'trigger';
		}

		// save them in db table...
		$this->saveRollbackIgnoreList($existing_db_objs);

		// there are overlapping tables, but if these 2 statements are in this particular order, it shouldn't be a problem...
		$this->createCDCTablesAndTriggers(
			$db_source,
			$ignore_tables,
			$ignore_triggers,
			$ignore_prefixes
		);

		$this->execute("USE {$this->db_name}");
	}

	/**
	 * Migrate Down.
	 */
	public function removeCDC()
	{
		// roll back the CDC's...
		$this->removeCDCTablesAndTriggers($this->db_name);

		// for Phinx...
		$this->execute("USE {$this->db_name}");
	}

	/**
	 * This is intended to prevent a migration rollback from deleting existing data in CDC db;
	 * it basically saves a list of table and trigger names to pass over during a rollback.
	 */
	private function saveRollbackIgnoreList($db_objs=array())
	{
		$ignore_table = "`$this->cdc_db`.`rollback_ignore`";

		// get rid of previous table list; recreate the table...
		$this->execute("DROP TABLE IF EXISTS `$ignore_table`");
		$sql = "CREATE TABLE `$ignore_table` (`name` varchar(50), `obj_type` varchar(50), `date_created` timestamp) ENGINE=InnoDB DEFAULT CHARSET=utf8";
		$this->execute($sql);

		// insert each db object and its type...
		foreach ($db_objs as $key => $value) {
			if ($key !== 'rollback_ignore') {
				$sql = "INSERT INTO `$ignore_table` (`name`, `obj_type`) VALUE('$key', '$value')";
				$this->execute($sql);
			}
		}
	}

	/**
	 * @return array
	 * Retrieves the CDC tables and triggers that existed before this migration was performed.
	 */
	private function getRollbackIgnoreList()
	{
		// list these as singular => plural...
		$obj_types = array(
			'table' => 'tables',
			'trigger' => 'triggers'
		);

		$db_objects = array();

		foreach ($obj_types as $type => $plural) {
			// create an array for each type of db object...
			$db_objects[$plural] = array();

			// get the objects of this type from table rollback_ignore...
			$sql = "SELECT * FROM `$this->cdc_db`.`rollback_ignore` WHERE `obj_type` = '$type';";
			$results = $this->fetchAll($sql);

			// assign the object names to the array that corresponds to their type...
			foreach ($results as $row) {
				$db_objects[$plural][] = $row[0];
			}
		}
		return $db_objects;
	}

	/**
	 * @param $db_from : The database to draw the table names from.
	 * @param array $ignore_prefixes : An array of table prefixes we don't want included in the table list.
	 * @return array : An associative list of tables mapped to the database context.
	 */
	private function getTableNames($db_from, $ignore_prefixes=array())
	{
		$tables = array();

		// get table names from db...
		$sql = "SHOW TABLES FROM `$db_from`";
		$results = $this->fetchAll($sql);

		foreach ($results as $row) {
			// don't add a table that has a prefix we're ignoring...
			$in_ignore_list = false;
			foreach ($ignore_prefixes as $prefix) {
				// match each prefix with the table name; if the prefix matches the beginning of the name, mark to ignore...
				if (strpos($row[0], $prefix) === 0) {
					$in_ignore_list = true;
				}
			}
			if (! $in_ignore_list) $tables[] = $row[0];
		}// foreach
		return $tables;
	}

	/**
	 * @param $db_from : The database to draw the trigger names from.
	 * @return array : An associative list of tables mapped to the database context.
	 */
	private function getTriggers($db_from)
	{
		$prefixes = array(
			'tr_ins_',
			'tr_upd_',
			'tr_del_'
		);
		$triggers = array();

		// get table names from db...
		$sql = "SHOW TRIGGERS FROM `$db_from`";
		$results = $this->fetchAll($sql);

		foreach ($results as $row) {
			$is_cdc_tr = false;
			foreach ($prefixes as $prefix) {
				if (strpos($row[0], $prefix) === 0) {
					$is_cdc_tr = true;
				}
			}
			if ($is_cdc_tr) {
				$triggers[] = $row[0];
			}
		}
		return $triggers;
	}

	/**
	 * @param $db_source
	 * @param $ignore_tables
	 * @param $ignore_triggers
	 * @param $ignore_prefixes
	 */
	private function createCDCTablesAndTriggers($db_source, $ignore_tables, $ignore_triggers, $ignore_prefixes)
	{
		$tables = $this->getTableNames($db_source, $ignore_prefixes);

		if ($this->create_sql_file) {
			if (! file_exists('tmp/')) {
				mkdir('tmp/', 0755);
			}
			file_put_contents($this->sql_filename, '');
		}

		// create the CDC table for each original table in the db...
		foreach ($tables as $table) {
			// gather column information for the table...
			$table_description = $this->fetchAll("DESCRIBE `$db_source`.`$table`");

			if (! in_array($table, $ignore_tables)) {
				// start assembling the create CDC table query...
				$query = "CREATE TABLE IF NOT EXISTS ";
				$query .= "`$this->cdc_db`.`$table` (\n";

				$query .= "  `{$this->cdc_db}_id` int(11) NOT NULL AUTO_INCREMENT,\n";

				// CDC change data columns...
				$query .= "  `change_type` char(1) DEFAULT NULL,\n";
				$query .= "  `capture_date` datetime DEFAULT NULL,\n";

				// create each CDC table column from original table info...
				foreach ($table_description as $column) {
					if ($column['Type'] == 'timestamp') {
						$query .= sprintf(
							"  `%s` %s %s,\n",
							$column['Field'], 'datetime',
							($column['Null'] === 'NO' ? 'NOT NULL' :
								($column['Default'] === null ? 'DEFAULT NULL' : '')
							));
					} else {
						$query .= sprintf(
							"  `%s` %s %s,\n",
							$column['Field'], $column['Type'],
							($column['Null'] === 'NO' ? 'NOT NULL' :
								($column['Default'] === null ? 'DEFAULT NULL' : '')
							));
					}
				}

				$query .= "  PRIMARY KEY (`{$this->cdc_db}_id`)";
				$query .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8;\n";
				$query .= "\n";

				if ($this->create_sql_file) {
					file_put_contents($this->sql_filename, $query, FILE_APPEND);
				}
				// run the create table query...
				try {
					$this->execute($query);
				} catch (PDOException $e) {
					$msg = sprintf("%s -- Table %s.%s could not be created.",
						$e->getMessage(), $db_source, $table);
					error_log($msg, 0);
				}
			}

			foreach ($this->events as $key => $event) {
				$trigger_name = "tr_{$key}_{$table}";

				if (! in_array($trigger_name, $ignore_triggers)) {
					// default is for handling inserts and updates...
					$key == 'del' ? $direction = 'old' : $direction = 'new';
					$action_time = 'AFTER';

					$trigger = "USE `$db_source`;\n";
					$trigger .= "DROP TRIGGER IF EXISTS `$trigger_name`;\n";
					// $trigger .= "DELIMITER $$\n";
					$trigger .= "CREATE TRIGGER `$trigger_name`\n  $action_time $event ON `$db_source`.`$table`\n";

					$trigger .= "FOR EACH ROW\n";
					$trigger .= "BEGIN\n";
					$trigger .= "  INSERT INTO `$this->cdc_db`.`$table` (`change_type`, `capture_date`)";

					// each original field...
					foreach ($table_description as $column) {
						$trigger .= ", `{$column['Field']}`";
					}
					$trigger .= ")\n";
					$trigger .= "  VALUES ('{$event[0]}', now()"; // adds I, D or U for change_type...

					// insert into new CDC field...
					foreach ($table_description as $column) {
						$trigger .= ", `$direction`.`{$column['Field']}`";
					}
					$trigger .= ");\n";
					$trigger .= "END";
					//$trigger .= " $$\n";
					$trigger .= "\n";
					//$trigger .= "DELIMITER ;\n";
					$trigger .= "\n";

					if ($this->create_sql_file) {
						file_put_contents($this->sql_filename, $trigger, FILE_APPEND);
					}
					// run the create trigger query...
					try {
						$this->execute($trigger);
					} catch (PDOException $e) {
						$msg = sprintf("%s -- Trigger %s could not be created.",
							$e->getMessage(), $trigger_name);
						error_log($msg, 0);
					}
				}
			}
		}
	}

	/**
	 * @param $cdc_db_name : the db that's being CDC'd.
	 */
	private function removeCDCTablesAndTriggers($cdc_db_name)
	{
		// gather listing of original tables...
		$cdc_tables = $this->getTableNames($cdc_db_name);

		// get tables that existed before the migration...
		$ignore = $this->getRollbackIgnoreList();

		foreach ($cdc_tables as $table) {
			// delete the INSERT, UPDATE and DELETE triggers for the CDC tables...
			foreach ($this->events as $key => $event) {
				$trigger = "tr_{$key}_{$table}";

				if (! in_array($trigger, $ignore['triggers'])) {
					$sql = "DROP TRIGGER IF EXISTS `$cdc_db_name`.`$trigger`;\n";
					try {
						$this->execute($sql);
					} catch (PDOException $e) {
						$msg = sprintf("%s -- Trigger `%s`.`tr_%s_%s` could not be dropped.",
							$e->getMessage(), $cdc_db_name, $key, $table);
						error_log($msg, 0);
					}
				}
			}

			if (! in_array($table, $ignore['tables'])) {
				// drop the table...
				$sql = "DROP TABLE IF EXISTS `{$this->cdc_db}`.`{$table}`\n";

				try {
					$this->execute($sql);
				} catch (PDOException $e) {
					$msg = sprintf("%s -- Table %s.%s could not be dropped.",
						$e->getMessage(), $cdc_db_name, $table);
					error_log($msg, 0);
				}
			}
		}
	}
}
