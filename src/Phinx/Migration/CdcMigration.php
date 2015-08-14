<?php

use Phinx\Migration\AbstractMigration;

class CdcMigration extends AbstractMigration
{
	private $db_name = '';

	/**
	 * CDC method types.
	 */
	private $methods = array(
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
	public function up()
	{
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
		$this->createCDC(
			array(
				'db_name'         => $this->db_name,
				'ignore_prefixes' => array('cdc_', 'bak', 'phinx'),
				'ignore_tables'   => $existing_cdc_tables,
				'ignore_triggers' => $triggers[$this->db_name]
			)
		);

		$this->execute("USE {$this->db_name}");
	}// end fn

	/**
	 * Migrate Down.
	 */
	public function down()
	{
		// roll back the CDC's...
		$this->removeCDC($this->db_name);

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
	}// end fn

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
	}// end fn

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
			if (!$in_ignore_list) $tables[] = $row[0];
		}// foreach
		return $tables;
	}//end fn

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
		}// foreach
		return $triggers;
	}//end fn

	/**
	 * @param $params
	 */
	private function createCDC($params=array())
	{
		if (!isset($params['db_source'])) {
			return;
		} else {
			$db_source = $params['db_source'];
		}
		isset($params['db_source']) ? $ignore_prefixes = $params['ignore_prefixes'] : $ignore_prefixes = array();
		isset($params['ignore_tables']) ? $ignore_tables = $params['ignore_tables'] : $ignore_tables = array();
		isset($params['ignore_triggers']) ? $ignore_triggers = $params['ignore_triggers'] : $ignore_triggers = array();

		$tables = $this->getTableNames($db_source, $ignore_prefixes);

		if ($this->create_sql_file) {
			if (!file_exists('tmp/')) {
				mkdir('tmp/', 0755);
			}
			file_put_contents($this->sql_filename, '');
		}

		// create the CDC table for each original table in the db...
		foreach ($tables as $table) {
			// gather column information for the table...
			$results = $this->fetchAll("DESCRIBE `$db_source`.`$table`");

			if (!in_array($table, $ignore_tables)) {
				// start assembling the create CDC table query...
				$query = "CREATE TABLE IF NOT EXISTS ";
				$query .= "`$this->cdc_db`.`$table` (\n";

				$query .= "  `{$this->cdc_db}_id` int(11) NOT NULL AUTO_INCREMENT,\n";

				// CDC change data columns...
				$query .= "  `change_type` char(1) DEFAULT NULL,\n";
				$query .= "  `capture_date` datetime DEFAULT NULL,\n";

				// create each CDC table column from original table info...
				foreach ($results as $row) {
					if ($row['Type'] == 'timestamp') {
						$query .= sprintf(
							"  `%s` %s %s,\n",
							$row['Field'], 'datetime',
							($row['Null'] === 'NO' ? 'NOT NULL' :
								($row['Default'] === null ? 'DEFAULT NULL' : '')
							));
					} else {
						$query .= sprintf(
							"  `%s` %s %s,\n",
							$row['Field'], $row['Type'],
							($row['Null'] === 'NO' ? 'NOT NULL' :
								($row['Default'] === null ? 'DEFAULT NULL' : '')
							));
					}
				}// foreach

				$query .= sprintf("  PRIMARY KEY (`%s_id`)", $this->cdc_db);
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
				// now create the INSERT, UPDATE and DELETE triggers for the newly-created CDC tablec...
			}// end if

			foreach ($this->methods as $key => $method) {
				$trigger_name = sprintf("tr_%s_%s", $key, $table);

				if (!in_array($trigger_name, $ignore_triggers)) {
					// default is for handling inserts and updates...
					$direction = 'new';
					if ($key == 'del') $direction = 'old';
					$action_time = 'AFTER';

					$trigger = "USE `$db_source`;\n";
					$trigger .= "DROP TRIGGER IF EXISTS `$trigger_name`;\n";
					// $trigger .= "DELIMITER $$\n";
					$trigger .= "CREATE TRIGGER `$trigger_name`\n  $action_time $method ON `$db_source`.`$table`\n";

					$trigger .= "FOR EACH ROW\n";
					$trigger .= "BEGIN\n";
					$trigger .= "  INSERT INTO `$this->cdc_db`.`$table` (`change_type`, `capture_date`)";

					// each original field...
					foreach ($results as $row) {
						$trigger .= ", `{$row['Field']}`";
					}
					$trigger .= ")\n";
					$trigger .= "  VALUES ('{$method[0]}', now()"; // adds I, D or U for change_type...

					// insert into new CDC field...
					foreach ($results as $row) {
						$trigger .= ", `$direction`.`{$row['Field']}`";
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
				}// end if
			}// end methods foreach
		}// end tables foreach
	}//end fn

	/**
	 * @param $cdc_db_name : the db that's being CDC'd.
	 */
	private function removeCDC($cdc_db_name)
	{
		// gather listing of original tables...
		$cdc_tables = $this->getTableNames($cdc_db_name);

		// get tables that existed before the migration...
		$ignore = $this->getRollbackIgnoreList();

		foreach ($cdc_tables as $table) {
			// delete the INSERT, UPDATE and DELETE triggers for the CDC tables...
			foreach ($this->methods as $key => $method) {
				$trigger = sprintf("tr_%s_%s", $key, $table);

				if (!in_array($trigger, $ignore['triggers'])) {
					$sql = "DROP TRIGGER IF EXISTS `$cdc_db_name`.`$trigger`;\n";
					try {
						$this->execute($sql);
					} catch (PDOException $e) {
						$msg = sprintf("%s -- Trigger `%s`.`tr_%s_%s` could not be dropped.",
							$e->getMessage(), $cdc_db_name, $key, $table);
						error_log($msg, 0);
					}
				}
			}// foreach

			if (!in_array($table, $ignore['tables'])) {
				// drop the table...
				$sql = sprintf("DROP TABLE IF EXISTS `%s`.`%s`\n", $this->cdc_db, $table);

				try {
					$this->execute($sql);
				} catch (PDOException $e) {
					$msg = sprintf("%s -- Table %s.%s could not be dropped.",
						$e->getMessage(), $cdc_db_name, $table);
					error_log($msg, 0);
				}
			}
		}// foreach
	}// fn
}// class
