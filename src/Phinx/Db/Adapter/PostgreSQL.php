<?php
require_once dirname(__FILE__) . DS . 'Interface.php';

/**
 * Class DBV_Adapter_PostgreSQL
 * This class create the function "generate_create_statement" in Postgres
 * compensate for the lack of MySQL functions "SHOW CREATE"
 * @author Marcelo Rodovalho <marcelo2208@gmail.com>
 */
class DBV_Adapter_PostgreSQL implements DBV_Adapter_Interface
{
    /**
     * @var PDO
     */
    protected $connection;

    /**
     * @var string
     */
    protected $schema = 'public';

    /**
     * @param bool|false $host
     * @param bool|false $port
     * @param bool|false $username
     * @param bool|false $password
     * @param bool|false $database_name
     * @throws DBV_Exception
     */
    public function connect($host = false, $port = false, $username = false, $password = false, $database_name = false)
    {
        $this->database_name = $database_name;

        try {
            $this->connection = new PDO("pgsql:dbname=$database_name;host=$host", $username, $password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->query('SET search_path TO ' . $this->schema);
        } catch (PDOException $e) {
            throw new DBV_Exception($e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * @param $sql
     * @return PDOStatement
     * @throws DBV_Exception
     */
    public function query($sql)
    {
        try {
            return $this->connection->query($sql);
        } catch (PDOException $e) {
            throw new DBV_Exception($e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * @return array
     */
    public function getSchema()
    {
        return array_merge(
            $this->getSequences(1),
            $this->getTables(2),
            $this->getIndexes(3),
            $this->getViews(4),
            $this->getFunctions(5),
            $this->getTriggers(6)
        );
    }

    /**
     * @param bool|false $prefix
     * @return array
     * @throws DBV_Exception
     */
    public function getSequences($prefix = false)
    {
        $return = array();
        $result = $this->query('SELECT sequence_name FROM information_schema.sequences;');
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $return[] = ($prefix ? "{$prefix}_" : '') . $row[0];
        }
        return $return;
    }

    /**
     * @param bool|false $prefix
     * @return array
     * @throws DBV_Exception
     */
    public function getTables($prefix = false)
    {
        $return = array();

        $result = $this->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = '" . $this->schema . "';");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $return[] = ($prefix ? "{$prefix}_" : '') . $row[0];
        }

        return $return;
    }

    /**
     * @param bool|false $prefix
     * @return array
     * @throws DBV_Exception
     */
    public function getIndexes($prefix = false)
    {
        $return = array();

        $result = $this->query("SELECT indexname FROM pg_indexes WHERE schemaname = '" . $this->schema . "' AND indexname NOT LIKE '%pkey';");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $return[] = ($prefix ? "{$prefix}_" : '') . $row[0];
        }

        return $return;
    }

    /**
     * @param bool|false $prefix
     * @return array
     * @throws DBV_Exception
     */
    public function getViews($prefix = false)
    {
        $return = array();
        $result = $this->query("SELECT viewname FROM pg_catalog.pg_views WHERE schemaname = '" . $this->schema . "';");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $return[] = ($prefix ? "{$prefix}_" : '') . $row[0];
        }
        return $return;
    }

    /**
     * @param bool|false $prefix
     * @return array
     * @throws DBV_Exception
     */
    public function getTriggers($prefix = false)
    {
        $return = array();
        $result = $this->query('SELECT DISTINCT trigger_name from information_schema.triggers;');
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $return[] = ($prefix ? "{$prefix}_" : '') . $row[0];
        }
        return $return;
    }

    /**
     * @param bool|false $prefix
     * @return array
     * @throws DBV_Exception
     */
    public function getFunctions($prefix = false)
    {
        $return = array();
        $result = $this->query("SELECT routine_name FROM information_schema.routines WHERE routine_schema = '" . $this->schema . "' and routine_name <> 'generate_create_statement';");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $return[] = ($prefix ? "{$prefix}_" : '') . $row[0];
        }

        return $return;
    }

    /**
     * @param $schema
     * @param $table
     * @return string
     */
    public function showCreateTable($schema, $table)
    {
        $result = '';
        $query = $this->query(
            "SELECT
                b.nspname as schema_name2,
                b.relname as table_name,
                a.attname as column_name,
                pg_catalog.format_type(a.atttypid, a.atttypmod) as column_type,
                CASE WHEN
                (SELECT substring(pg_catalog.pg_get_expr(d.adbin, d.adrelid) for 128)
                 FROM pg_catalog.pg_attrdef d
                 WHERE d.adrelid = a.attrelid AND d.adnum = a.attnum AND a.atthasdef) IS NOT NULL THEN
                    'DEFAULT '|| (SELECT substring(pg_catalog.pg_get_expr(d.adbin, d.adrelid) for 128)
                          FROM pg_catalog.pg_attrdef d
                          WHERE d.adrelid = a.attrelid AND d.adnum = a.attnum AND a.atthasdef)
                ELSE
                    ''
                END as column_default_value,
                CASE WHEN a.attnotnull = true THEN
                    'NOT NULL'
                ELSE
                    'NULL'
                END as column_not_null,
                a.attnum as attnum,
                e.max_attnum as max_attnum
            FROM
                pg_catalog.pg_attribute a
                INNER JOIN (
                    SELECT c.oid,
                        n.nspname,
                        c.relname
                    FROM pg_catalog.pg_class c
                    LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                    WHERE c.relname ~ ('^('||'$table'||')$')
                    AND pg_catalog.pg_table_is_visible(c.oid)
                    ORDER BY 2, 3) b
                ON a.attrelid = b.oid
                INNER JOIN (
                    SELECT
                        a.attrelid,
                        max(a.attnum) as max_attnum
                    FROM pg_catalog.pg_attribute a
                    WHERE a.attnum > 0
                    AND NOT a.attisdropped
                    GROUP BY a.attrelid) e
                ON a.attrelid=e.attrelid
                WHERE a.attnum > 0
                AND NOT a.attisdropped
                ORDER BY a.attnum;"
        );
        $columns = $query->fetchAll(PDO::FETCH_NAMED);
        foreach ($columns as $column) {
            if ($column['attnum'] === 1) {
                $result .= 'CREATE TABLE ' . $column['schema_name2'] . '.' . $column['table_name'] . '(';
            } else {
                $result .= ',';
            }
            if ($column['attnum'] <= $column['max_attnum']) {
                $result .= "\n    " .
                    $column['column_name'] . ' ' .
                    $column['column_type'] . ' ' .
                    ($column['column_default_value'] ? $column['column_default_value'] . ' ' : '') .
                    $column['column_not_null'];
            }
        }
        $query2 = $this->query(
            "SELECT
                c.conname,
                pg_get_constraintdef(c.oid) as condef,
                c.contype
            FROM   pg_constraint c
            JOIN   pg_namespace n ON n.oid = c.connamespace
            WHERE  contype IN ('f', 'p')
            AND    conrelid = regclass('$table')::oid
            AND    n.nspname = '$schema' -- your schema here
            ORDER  BY conrelid::regclass::text, contype DESC;"
        );
        $constraints = $query2->fetchAll(PDO::FETCH_NAMED);
        foreach ($constraints as $constraint) {
            $result .= ",\n" .
                '    CONSTRAINT ' . $constraint['conname'] . ' ' . $constraint['condef'] .
                ($constraint['contype'] === 'f' ? " MATCH SIMPLE \n         ON UPDATE NO ACTION ON DELETE NO ACTION" : '');
        }
        $result .= ')';

        $query3 = $this->query(
            "SELECT relhasoids
            FROM pg_class,pg_namespace
            WHERE pg_class.relnamespace=pg_namespace.oid
            AND pg_namespace.nspname='$schema'
            AND pg_class.relname='$table'
            AND pg_class.relkind='r';"
        );
        $oid = $query3->fetch(PDO::FETCH_NAMED);
        if ($oid['relhasoids']) {
            $result .= ' WITH (OIDS=FALSE);';
        } else {
            $result .= ' WITHOUT OIDS;';
        }
        return $result;
    }

    /**
     * @param $schema
     * @param $index
     * @return string
     */
    public function showCreateIndex($schema, $index)
    {
        $query = $this->query(
            "SELECT indexdef
            FROM pg_indexes
            WHERE schemaname = '$schema'
            AND indexname NOT LIKE '%pkey'
            AND indexname = '$index';"
        );
        $indexes = $query->fetchAll(PDO::FETCH_NAMED);
        $result = '';
        foreach ($indexes as $idx) {
            $result .= $idx['indexdef'] . ';';
        }
        return $result;
    }

    /**
     * @param $schema
     * @param $sequence
     * @return string
     */
    public function showCreateSequence($schema, $sequence)
    {
        $query = $this->query(
            "SELECT
                a.sequence_name,
                a.increment,
                a.minimum_value,
                a.maximum_value,
                a.start_value,
                a.cycle_option
            FROM information_schema.sequences a
            WHERE a.sequence_schema = '$schema'
            AND a.sequence_name ~ ('^('||'$sequence'||')$');"
        );
        $sequences = $query->fetchAll(PDO::FETCH_NAMED);
        $result = '';
        foreach ($sequences as $seq) {
            $result .= 'CREATE SEQUENCE ' . $seq['sequence_name'] . "\n" .
                'INCREMENT ' . $seq['increment'] . "\n" .
                'MINVALUE ' . $seq['minimum_value'] . "\n" .
                'MAXVALUE ' . $seq['maximum_value'] . "\n" .
                'START ' . $seq['start_value'] . "\n" .
                'CACHE 1' . ($seq['cycle_option'] === 'YES' ? ' CYCLE' : '') . ';';
        }
        return $result;
    }

    /**
     * @param $schema
     * @param $view
     * @return string
     */
    public function showCreateView($schema, $view)
    {
        $query = $this->query(
            "SELECT
                vw.viewname,
                pg_get_viewdef(viewname::regclass, true) as viewdef
            FROM pg_catalog.pg_views vw
            WHERE vw.schemaname = '$schema'
            AND vw.viewname = '$view';"
        );
        $views = $query->fetchAll(PDO::FETCH_NAMED);
        $result = '';
        foreach ($views as $vw) {
            $result .=
                'CREATE OR REPLACE VIEW ' . $vw['viewname'] . " AS\n" .
                $vw['viewdef'] . ';';
        }
        return $result;
    }

    /**
     * @param $schema
     * @param $trigger
     * @return string
     */
    public function showCreateTrigger($schema, $trigger)
    {
        $query = $this->query(
            "SELECT DISTINCT
                CASE WHEN pr.prorettype = 'pg_catalog.trigger'::pg_catalog.regtype THEN
                    pg_get_triggerdef(tr.oid)
                ELSE
                    NULL
                END as trigger_def
            FROM pg_catalog.pg_class as c
            INNER JOIN pg_catalog.pg_attribute as a ON (a.attrelid = c.oid)
            INNER JOIN pg_catalog.pg_type as t ON (t.oid = a.atttypid)
            LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            LEFT JOIN pg_catalog.pg_tablespace ts ON ts.oid = c.reltablespace
            LEFT JOIN pg_trigger tr ON replace(tr.tgrelid::regclass::text, '\"', '') = c.relname
            LEFT JOIN pg_proc pr ON pr.oid = tr.tgfoid
            WHERE a.attnum > 0      -- no system cols
            AND NOT attisdropped    -- no dropped cols
            AND c.relkind = 'r'
            AND tr.tgisinternal is not true
            AND tr.tgname IS NOT NULL
            AND n.nspname = '$schema'
            AND tr.tgname = '$trigger';"
        );
        $triggers = $query->fetchAll(PDO::FETCH_NAMED);
        $result = '';
        foreach ($triggers as $trg) {
            $result .= $trg['trigger_def'] . ';';
        }
        return $result;
    }

    /**
     * @param $schema
     * @param $function
     * @return string
     */
    public function showCreateFunction($schema, $function)
    {
        $query = $this->query(
            "SELECT pg_get_functiondef(f.oid) as text
            FROM pg_catalog.pg_proc f
            INNER JOIN pg_catalog.pg_namespace n ON (f.pronamespace = n.oid)
            WHERE n.nspname = '$schema'
            AND f.proname = '$function';"
        );
        $functions = $query->fetchAll(PDO::FETCH_NAMED);
        $result = '';
        foreach ($functions as $func) {
            $result .= $func['text'] . ';';
        }
        return $result;
    }

    /**
     * @param $name
     * @return mixed
     * @throws DBV_Exception
     */
    public function getSchemaObject($name)
    {
        $name = preg_replace('/^\d_/', '', $name);
        switch ($name) {
            case in_array($name, $this->getSequences(), true):
                $return = $this->showCreateSequence($this->schema, $name);
                break;
            case in_array($name, $this->getTables(), true):
                $return = $this->showCreateTable($this->schema, $name);
                break;
            case in_array($name, $this->getIndexes(), true):
                $return = $this->showCreateIndex($this->schema, $name);
                break;
            case in_array($name, $this->getViews(), true):
                $return = $this->showCreateView($this->schema, $name);
                break;
            case in_array($name, $this->getFunctions(), true):
                $return = $this->showCreateFunction($this->schema, $name);
                break;
            case in_array($name, $this->getTriggers(), true):
                $return = $this->showCreateTrigger($this->schema, $name);
                break;
            default:
                throw new DBV_Exception("<strong>$name</strong> not found in the database");
        }
        return $return;
    }

    public function _getFileContents($file)
    {
        $path = DBV_SCHEMA_PATH . DS . $file . '.sql';
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        return false;
    }
}
