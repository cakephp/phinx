<?php

namespace Phinx\Db\Adapter;

class BambooHRMysqlAdapter extends \Phinx\Db\Adapter\MysqlAdapter {

	public function hasTable($tableName) {
		$sql = sprintf("DESC %s", $tableName);
		try {
			$exists = $this->query($sql);
		} catch (\PDOException $exc) {
			return false;
		}
		return true;
	}

	protected function getForeignKeys($tableName) {
		throw new \Exception("uh uh - we aren't going to support getting foreign keys");
	}

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

	public function hasDatabase($name) {
		$sql = sprintf("show databases like '%s'",$name);
		$ret = $this->query($sql);
		return $ret->rowCount() > 0 ? true : false;
		
	}
	
	public function describeTable($tableName) {
		throw new \Exception("uh uh - we don't support describe table");
	}

}
