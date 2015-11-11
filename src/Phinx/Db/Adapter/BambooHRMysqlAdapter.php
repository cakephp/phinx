<?php

namespace Phinx\Db\Adapter;

class BambooHRMysqlAdapter extends \Phinx\Db\Adapter\MysqlAdapter {

	/**
	 * 
	 * @param type $tableName table name
	 * @return boolean
	 */
	public function hasTable($tableName) {
		$sql = sprintf("DESC `%s`", $tableName);
		try {
			$this->query($sql);
		} catch (\PDOException $exc) {
			return false;
		}
		return true;
	}

	/**
	 * 
	 * @param type $tableName tablename
	 * 
	 */
	protected function getForeignKeys($tableName) {
		$sql = sprintf("SHOW CREATE TABLE `%s`", $tableName);
		$results = $this->fetchAll($sql);
		$foreignKeyMatch = '/CONSTRAINT\s+\`([a-z0-9A-Z\_]*)\`\s+FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
		preg_match_all($foreignKeyMatch, $results[0]['Create Table'], $rows);
		
		unset($rows[0]);
		$count = count($rows[1]) - 1;
		$tick = 0;
		$foreignKeys = [];
		while ($tick <= $count) {
		    $foreignKeys[$rows[1][$tick]]['table'] = $tableName;
		    $foreignKeys[$rows[1][$tick]]['columns'][] = str_replace('`', '', $rows[2][$tick]);
		    $foreignKeys[$rows[1][$tick]]['referenced_table'] = str_replace('`', '', $rows[3][$tick]);
		    $foreignKeys[$rows[1][$tick]]['referenced_column'] = str_replace('`', '', $rows[4][$tick]);
		    $tick++;
		}
		return $foreignKeys;
	}

	/**
	 * 
	 * @param type $tableName
	 * @param type $columns
	 * @param type $constraint
	 * @return type
	 * @throws \Exception
	 */
	public function dropForeignKey($tableName, $columns, $constraint = null) {
		$this->startCommandTimer();
		if (is_string($columns)) {
			$columns = array($columns); // str to array
		}

		$this->writeCommand('dropForeignKey', array($tableName, $columns));

		if ($constraint) {
			$this->execute(
				sprintf(
					'ALTER TABLE %s DROP FOREIGN KEY %s', $this->quoteTableName($tableName), $constraint
				)
			);
			$this->endCommandTimer();
			return;
		} else {
			throw new \Exception("uh uh - don't drop a foreign key without a constraint");
		}
		$this->endCommandTimer();
	}

	/**
	 * 
	 * @param type $name
	 * @return type
	 */
	public function hasDatabase($name) {
		$sql = sprintf("show databases like '%s'",$name);
		$ret = $this->query($sql);
		return $ret->rowCount() > 0 ? true : false;
		
	}
	
	/**
	 * 
	 * @param type $tableName
	 * @throws \Exception
	 */
	public function describeTable($tableName) {
		throw new \Exception("uh uh - we don't support describe table");
	}

}
