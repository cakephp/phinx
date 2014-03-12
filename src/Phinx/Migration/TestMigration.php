<?php
/**
 * 
 *
 *
 *
 */

namespace Phinx\Migration;


class TestMigration extends AbstractMigration {
    protected function insertTestData($table, array $data) {
        $rows = array();
        foreach ($data as $row) {
            $rows[] = implode('","',$row);
        }
        $rows = implode('"),("',$rows);
        $this->execute('INSERT INTO `'.$table.'` VALUES ("'.$rows.'")');
    }
} 