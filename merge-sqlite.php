<?php
class merge_sqlite
{
	public $new = null;
	public $old = null;
	public $db = null;

	public $pdo = null;

	public $merge_table = 'statistics_merge';

	/**
	 * Constructor
	 */
	public function __construct( $new, $old, $db = null ) {
		$this->new = $new;
		$this->old = $old;
		$this->db  = $db;

		if ( ! $this->db || ! file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $this->db ) ) {
			$this->init_db();
		}

		$this->pdo = new PDO( 'sqlite:' . $this->db );

	/**
	 * Create (duplicate) DB if not exist.
	 */
	public function init_db() {
		$name = explode( '.', $this->new );
		$ext = array_pop( $name );
		$end = array_pop( $name );
		$end .= '_new-' . time();
		$name[] = $end;
		$name[] = $ext;
		$this->db = implode( '.', $name );
		copy( $this->new, $this->db );

		$this->db = $this->db;

		$this->messages[] = array(
			'step'    => 'init_db',
			'message' => 'Database copy created',
			'data'    => $this->new . ' > ' . $this->db,
			'done'    => true,
		);

		return true;
	}

	/**
	 * Create merge table.
	 */
	public function init_merge() {
		$step = $this->step;
		$done = $this->steps_done[ $step ] ?? null;
		if ( true === $done ) {
			return true;
		}

		$done = $this->table_exists( $this->merge_table );
		if ( $done ) {
			$this->steps_done[ $step ] = true;
			return true;
		}

		$this->exec( "CREATE TABLE {$this->merge_table} (
			id INTEGER NOT NULL,
			id_org INTEGER NOT NULL,
			statistic_id VARCHAR(255),
			PRIMARY KEY (id)
		)" );

		$this->messages[] = array(
			'step'    => $step,
			'message' => 'Table created',
			'data'    => $this->merge_table,
			'done'    => true,
		);

		$this->steps_done[ $step ] = true;
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
	}
}
