<?php

namespace Phinx\Db\Adapter;

use Phinx\Db\Table\Table;
use Phinx\Db\Table\Column;

require_once( dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php' );

class RedshiftAdapter extends PostgresAdapter
{
	
	public function createTable(Table $table, array $columns = [], array $indexes = [])
	{
		$options = $table->getOptions();
		$parts = $this->getSchemaName( $table->getName() );
		
		// Add the default primary key
		if ( !isset( $options['id'] ) || ( isset( $options['id'] ) && $options['id'] === true ) ) {
			$column = new Column();
			$column->setName( 'id' )
			       ->setType( 'integer' )
			       ->setIdentity( true );
			
			array_unshift( $columns, $column );
			$options['primary_key'] = 'id';
		} elseif ( isset( $options['id'] ) &&
			is_string( $options['id'] ) ) {// Handle id => "field_name" to support AUTO_INCREMENT
			$column = new Column();
			$column->setName( $options['id'] )
			       ->setType( 'integer' )
			       ->setIdentity( true );
			
			array_unshift( $columns, $column );
			$options['primary_key'] = $options['id'];
		}
		
		// TODO - process table options like collation etc
		$sql = 'CREATE TABLE ';
		$sql .= $this->quoteTableName( $table->getName() ) . ' (';
		
		$this->columnsWithComments = [];
		foreach ( $columns as $column ) {
			$sql .= $this->quoteColumnName( $column->getName() ) . ' ' . $this->getColumnSqlDefinition( $column ) .
				', ';
			
			// set column comments, if needed
			if ( $column->getComment() ) {
				$this->columnsWithComments[] = $column;
			}
		}
		
		// set the primary key(s)
		if ( isset( $options['primary_key'] ) ) {
			$sql = rtrim( $sql );
			$sql .= sprintf( ' CONSTRAINT %s PRIMARY KEY (', $this->quoteColumnName( $parts['table'] . '_pkey' ) );
			if ( is_string( $options['primary_key'] ) ) { // handle primary_key => 'id'
				$sql .= $this->quoteColumnName( $options['primary_key'] );
			} elseif ( is_array( $options['primary_key'] ) ) { // handle primary_key => array('tag_id', 'resource_id')
				$sql .= implode( ',', array_map( [ $this, 'quoteColumnName' ], $options['primary_key'] ) );
			}
			$sql .= ')';
		} else {
			$sql = rtrim( $sql, ', ' ); // no primary keys
		}
		$sql .= ') ';
		
		// process redshift sortkeys & distkey
		$sortKeys = isset( $options['sortkeys'] ) ? $options['sortkeys'] : null;
		
		$distKey = isset( $options['distkey'] ) ? $options['distkey'] : null;
		
		$distStyle = isset( $options['diststyle'] ) ? $options['diststyle'] : null;
		
		$interleavedSortKey = isset( $options['interleaved'] ) ? (bool) $options['interleaved'] : false;
		
		if ( !empty( $distKey ) ) {
			$sql .= ' distkey(' . addslashes( $distKey ) . ')';
		}
		
		if ( !empty( $sortKeys ) ) {
			
			$sortKeyStr = is_array( $sortKeys ) ? addslashes( implode( ',', $sortKeys ) ) : addslashes( $sortKeys );
			
			if ( !$interleavedSortKey ) {
				$sql .= sprintf( ' compound sortkey (%s) ', $sortKeyStr );
			} else {
				$sql .= sprintf( ' interleaved sortkey (%s) ', $sortKeyStr );
			}
		}
		
		if ( !empty( $distStyle ) ) {
			$sql .= sprintf( ' diststyle %s', $distStyle );
		}
		
		$sql .= ';';
		
		// process column comments
		if ( !empty( $this->columnsWithComments ) ) {
			foreach ( $this->columnsWithComments as $column ) {
				$sql .= $this->getColumnCommentSqlDefinition( $column, $table->getName() );
			}
		}
		
		// set the indexes
		if ( !empty( $indexes ) ) {
			foreach ( $indexes as $index ) {
				$sql .= $this->getIndexSqlDefinition( $index, $table->getName() );
			}
		}
		
		// execute the sql
		$this->execute( $sql );
		
		// process table comments
		if ( isset( $options['comment'] ) ) {
			$sql = sprintf( 'COMMENT ON TABLE %s IS %s', $this->quoteTableName( $table->getName() ),
				$this->getConnection()
				     ->quote( $options['comment'] ) );
			$this->execute( $sql );
		}
	}
	
	/**
	 * @param string $tableName Table name
	 *
	 * @return array
	 */
	private function getSchemaName($tableName)
	{
		$schema = $this->getGlobalSchemaName();
		$table = $tableName;
		if ( false !== strpos( $tableName, '.' ) ) {
			list( $schema, $table ) = explode( '.', $tableName );
		}
		
		return [
			'schema' => $schema,
			'table'  => $table,
		];
	}
	
	/**
	 * Gets the schema name.
	 *
	 * @return string
	 */
	private function getGlobalSchemaName()
	{
		$options = $this->getOptions();
		
		return empty( $options['schema'] ) ? 'public' : $options['schema'];
	}
}