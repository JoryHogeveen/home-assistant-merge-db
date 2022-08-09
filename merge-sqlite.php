<?php
class merge_sqlite
{
	public $new = null;
	public $old = null;
	public $db = null;

	public $pdo = null;

	public $merge_table = 'statistics_merge';

	public $messages = array();
	public $steps_done = array();
	public $interval = array();
	public $step = '';
	public $done = false;

	/**
	 * Constructor
	 */
	public function __construct( $new, $old, $db = null, $steps_done = array(), $interval = null ) {
		$this->steps_done = $steps_done;
		$this->interval   = $interval ?? 1000;

		$this->new = $new;
		$this->old = $old;
		$this->db  = $db;

		if ( ! $this->db || ! file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $this->db ) ) {
			$this->init_db();
		}

		$this->pdo = new PDO( 'sqlite:' . $this->db );

		if ( ! $steps ) {
			$steps = array(
				'init_merge', // Create merge table.
				'init_stats', // Truncate tables and cCopy old statistics and meta.
			);
		}

		foreach ( $steps as $step ) {
			if ( is_callable( array( $this, $step ) ) ) {
				$this->step = $step;
				$done = call_user_func( array( $this, $step ) );
				if ( true !== $done ) {
					return;
				}
			}
		}

		// END.
		$this->done = true;
		return;
	}

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

	/**
	 *  Trucate tables and copy statistics from old database.
	 */
	public function init_stats() {
		$step = $this->step;
		$done = $this->steps_done[ $step ] ?? null;

		if ( true === $done ) {
			return true;
		}

		$this->exec( "ATTACH `{$this->old}` as db_old" );

		if ( ! is_numeric( $done ) ) {
			// First step.

			$this->truncate_statistics();

			$this->exec( "INSERT INTO main.statistics SELECT * FROM db_old.statistics" );

			$this->messages[] = array(
				'step'     => $step,
				'message'  => 'Old statistics copied',
				'data'     => $this->old . '.statistics > ' . $this->db . '.statistics',
				'done'     => 1,
			);

			$this->steps_done[ $step ] = 1;
		} else {
			// Last step.

			$this->exec( "INSERT INTO main.statistics_meta SELECT * FROM db_old.statistics_meta" );

			$this->messages[] = array(
				'step'     => $step,
				'message'  => 'Old statistics metadata copied',
				'data'     => $this->old . '.statistics_meta > ' . $this->db . '.statistics_meta',
				'done'     => true,
			);

			$this->steps_done[ $step ] = true;
		}
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

	public function truncate_statistics() {
		$tables = array(
			'statistics',
			'statistics_meta',
			'statistics_short_term', // Needs ID merge.
			//'statistics_runs', // No need.
		);

		foreach ( $tables as $table ) {
			$this->truncate_table( $table );
		}

		$this->messages[] = array(
			'step'    => $this->step,
			'message' => 'Tables truncated',
			'data'    => implode( ', ', $tables ),
			'done'    => 0,
		);
		return true;
	}

	public function truncate_table( $table ) {
		// "TRUNCATE TABLE {$table}"
		$this->pdo->exec( "DELETE FROM {$table}" );
	}
	}
}
