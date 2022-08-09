<?php
class db_sqlite
{
	public $db = null;
	public $pdo = null;
	public $main = null;

	/**
	 * Constructor
	 */
	public function __construct( $db, $main = null ) {
		$this->db    = $db;
		$this->pdo   = new PDO( 'sqlite:' . $this->db );
		$this->main = $main;
	}

	public function sql_update( $row ) {
		$row = $this->pdo->sql_parse_row( $row );

		$update = array();
		foreach ( $row as $key => $value ) {
			$update[] = $key . ' = ' . $value;
		}
		$update  = implode( ', ', $update );

		return $update;
	}

	public function sql_insert( $row ) {
		$row     = $this->pdo->sql_parse_row( $row );
		$columns = array_keys( $row );

		$columns = implode( ',', $columns );
		$values  = implode( ',', $row );

		return "({$columns}) VALUES ({$values})";
	}

	public function sql_parse_row( $row ) {
		foreach ( $row as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$value = (float) $value;
				if ( $value == (int) $value ) {
					$value = (int) $value;
				}
				$row[ $key ] = $value;
			} else {
				if ( $value ) {
					$row[ $key ] = '"' . $value . '"';
				} else {
					$row[ $key ] = 'NULL';
				}
			}
		}
		return $row;
	}

	public function table_exists( $table ) {
		$tables = $this->list_tables();
		return in_array( $table, $tables, true );
	}

	public function list_tables() {
		$results = $this->pdo->query( "SELECT name FROM sqlite_master WHERE type='table'" );
		$return = array();

		if ( $results ) {
			foreach ( $results as $row ) {
				$return[] = $row['name'];
			}
		}
		return $return;
	}

	public function truncate_table( $table ) {
		// "TRUNCATE TABLE {$table}"
		$this->pdo->exec( "DELETE FROM {$table}" );
	}

	public function exec( $sql ) {
		try {
			return $this->pdo->exec( $sql );
		} catch ( Exception $e ) {
			if ( isset( $this->main ) ) {
				return $this->main->return_error( array(
					'step'    => $this->step,
					'message' => 'SQL Exec: ' . $e->getMessage(),
					'data'    => $sql,
					'done'    => false,
				) );
			}
			return $e->getMessage();
		}
	}

	public function query( $sql, $fetchMode = PDO::FETCH_ASSOC ) {
		try {
			return $this->pdo->query( $sql, $fetchMode );
		} catch ( Exception $e ) {
			if ( isset( $this->main ) ) {
				return $this->main->return_error( array(
					'step'    => $this->step,
					'message' => 'SQL Query: ' . $e->getMessage(),
					'data'    => $sql,
					'done'    => false,
				) );
			}
			return $e->getMessage();
		}
	}

	public function query_row( $sql ) {
		$rows = $this->query( $sql );
		if ( $rows ) {
			return reset( $rows );
		}
		return null;
	}

	public function query_value( $sql ) {
		$rows = $this->query( $sql );
		if ( $rows ) {
			foreach ( $rows as $row ) {
				return reset( $row );
			}
		}
		return null;
	}

	public function query_num_rows( $table ) {
		$results = $this->pdo->query( "SELECT COUNT(*) FROM {$table}", PDO::FETCH_NUM )->fetch();
		return reset( $results );
	}
}
