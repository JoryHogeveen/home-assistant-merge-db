<?php
// Fix float sum calculations.
ini_set('precision', 16);

class merge_sqlite
{
	public $new = null;
	public $old = null;
	public $db  = null;

	public $pdo = null;

	public $merge_table = 'statistics_merge';

	public $messages = array();
	public $done = false;

	public $step = '';
	public $steps = array(
		'init_merge', // Create merge table.
		'init_stats', // Truncate tables and copy old statistics and meta.
		'init_sums', // Load sum information for recalculation.
		'merge_meta', // Merge old stats meta with new stats meta.
		'merge_short_term', // Convert meta ID's from short term records.
		'merge_long_term', // Pull long term stats from new database and convert meta.
	);
	public $steps_done = array();
	public $interval = array();
	public $sums = array();

	/**
	 * Constructor
	 */
	public function __construct( $new = null, $old = null, $db = null ) {
		$this->new = $new;
		$this->old = $old;
		$this->db  = $db;
	}

	/**
	 * Run merge loop.
	 */
	public function run( $steps_done = array(), $interval = null, $sums = null, $steps = null ) {
		$this->steps_done = $steps_done;
		$this->interval   = $interval ?? 1000;
		$this->sums       = $sums ?? array();

		// Prepare DB instance.
		if ( ! $this->pdo instanceof PDO ) {
			$this->init_db();
		}

		// Prepare steps to be made.
		if ( $steps ) {
			$this->steps = $steps;
		}

		foreach ( $this->steps as $step ) {
			if ( is_callable( array( $this, $step ) ) ) {
				$this->step = $step;
				$done = call_user_func( array( $this, $step ) );
				if ( true !== $done ) {
					return; // Continue next iteration.
				}
			} else {
				return $this->return_error( array(
					'step'    => 'run',
					'message' => 'No valid callback for step.',
					'data'    => $step,
					'done'    => false,
				) );
			}
		}

		// END.
		$this->done = true;
	}

	/**
	 * Create (duplicate) DB if not exist.
	 */
	public function init_db() {

		if ( ! $this->db || ! file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $this->db ) ) {
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
		}

		require_once 'db-sqlite.php';
		$this->pdo = new db_sqlite( $this->db, $this );

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

		$done = $this->pdo->table_exists( $this->merge_table );
		if ( $done ) {
			$this->steps_done[ $step ] = true;
			return true;
		}

		$this->pdo->exec( "CREATE TABLE {$this->merge_table} (
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

		$this->pdo->exec( "ATTACH `{$this->old}` as db_old" );

		if ( ! is_numeric( $done ) ) {
			// First step.

			$this->pdo->truncate_table( 'main.statistics' );

			$this->pdo->exec( "INSERT INTO main.statistics SELECT * FROM db_old.statistics" );

			$this->messages[] = array(
				'step'     => $step,
				'message'  => 'Old statistics copied',
				'data'     => $this->old . '.statistics > ' . $this->db . '.statistics',
				'done'     => 1,
			);

			$this->steps_done[ $step ] = 1;
		} else {
			// Last step.

			$this->pdo->truncate_table( 'main.statistics_meta' );

			$this->pdo->exec( "INSERT INTO main.statistics_meta SELECT * FROM db_old.statistics_meta" );

			$this->messages[] = array(
				'step'     => $step,
				'message'  => 'Old statistics metadata copied',
				'data'     => $this->old . '.statistics_meta > ' . $this->db . '.statistics_meta',
				'done'     => true,
			);

			$this->steps_done[ $step ] = true;
		}
	}

	/**
	 * Load sum recalculation metadata.
	 */
	public function init_sums() {
		$step = $this->step;
		$done = $this->steps_done[ $step ] ?? null;
		if ( true === $done ) {
			return true;
		}

		if ( ! $this->sums ) {
			$this->steps_done[ $step ] = true;
			return true;
		}

		$this->pdo->exec( "ATTACH `{$this->new}` as db_new" );

		foreach ( $this->sums as $statistic_id => $data ) {
			unset( $this->sums[ $statistic_id ] );
			$limit = "LIMIT 1";

			// User input.
			if ( is_string( $data ) && ! is_string( $statistic_id ) ) {
				$statistic_id = $data;
			}

			// Get the entity meta from the new database.
			$meta = $this->pdo->query_row( "SELECT * FROM db_new.statistics_meta WHERE statistic_id = '{$statistic_id}'" );

			if ( ! isset( $meta['id'] ) ) {
				$this->messages[] = array(
					'step'     => $step,
					'message'  => 'New entity for sum calculation not found, skip recalculation',
					'data'     => 'db_new.statistics_meta > ' . $statistic_id . var_export( $meta, true ),
					'done'     => null,
				);
				continue;
			}
			if ( empty( $meta['has_sum'] ) ) {
				$this->messages[] = array(
					'step'     => $step,
					'message'  => 'New entity does not support sum',
					'data'     => 'db_new.statistics_meta > ' . $statistic_id,
					'done'     => null,
				);
				continue;
			}

			// Get first value of new database.
			$metadata_id = $meta['id'];
			$where       = "WHERE metadata_id = {$metadata_id}";
			$order       = "ORDER BY created ASC";
			$new         = $this->pdo->query_row( "SELECT * FROM db_new.statistics {$where} {$order} {$limit}" );

			if ( ! isset( $new['sum'] ) ) {
				$this->messages[] = array(
					'step'     => $step,
					'message'  => 'Statistics from new entity for sum calculation not found, skip recalculation',
					'data'     => 'db_new.statistics > ' . $statistic_id,
					'done'     => null,
				);
				continue;
			}

			// Get the entity meta from the original database.
			$meta = $this->pdo->query_row( "SELECT * FROM main.statistics_meta WHERE statistic_id = '{$statistic_id}'" );
			if ( ! isset( $meta['id'] ) ) {
				$this->messages[] = array(
					'step'     => $step,
					'message'  => 'Original entity for sum calculation not found, skip recalculation',
					'data'     => 'main.statistics_meta > ' . $statistic_id,
					'done'     => null,
				);
				continue;
			}
			if ( empty( $meta['has_sum'] ) ) {
				$this->messages[] = array(
					'step'     => $step,
					'message'  => 'Original entity does not support sum',
					'data'     => 'db_new.statistics_meta > ' . $statistic_id,
					'done'     => null,
				);
				continue;
			}

			// Get latest value of original database.
			$metadata_id = $meta['id'];
			$where       = "WHERE metadata_id = {$metadata_id}";
			$order       = "ORDER BY created DESC";
			$org         = $this->pdo->query_row( "SELECT * FROM main.statistics {$where} {$order} {$limit}" );

			if ( ! isset( $org['sum'] ) ) {
				$this->messages[] = array(
					'step'     => $step,
					'message'  => 'Statistics from original entity for sum calculation not found, skip recalculation',
					'data'     => 'main.statistics > ' . $statistic_id,
					'done'     => null,
				);
				continue;
			}

			$data = array(
				'statistic_id' => $statistic_id,
				'meta'         => $meta,
				'org_sum'      => (float) $org['sum'],
				'org_state'    => (float) $org['state'],
				'new_sum'      => (float) $new['sum'] ?? 0,
				'new_state'    => (float) $new['state'],
			);

			// Calculate sum base for recalculation or new entries.
			// It will be the old sum plus the difference between the old state and new state.
			// Also subtract the new sum of the first new statistic value to compensate if it is not 0.
			$data['sum'] = $data['org_sum'] + ( $data['new_state'] - $data['org_state'] ) - $data['new_sum'];

			$this->sums[ $metadata_id ] = $data;
		}

		$this->messages[] = array(
			'step'     => $step,
			'message'  => 'Sums calculated',
			'data'     => implode( ', ', array_keys( $this->sums ) ),
			'done'     => true,
		);

		$this->steps_done[ $step ] = true;
	}

	/**
	 * Merge statistics metadata and update merge table.
	 */
	public function merge_meta() {
		$step = $this->step;
		$done = $this->steps_done[ $step ] ?? null;
		if ( true === $done ) {
			return true;
		}

		$this->pdo->exec( "ATTACH `{$this->new}` as db_new" );

		$meta_table = 'statistics_meta';
		$main_meta_table = 'main.' . $meta_table;

		$current = $this->pdo->query( "SELECT * FROM {$main_meta_table}" );
		$results = $this->pdo->query( "SELECT * FROM db_new.{$meta_table}" );

		$this->pdo->truncate_table( $this->merge_table );

		if ( ! $results ) {
			return $this->return_error( array(
				'step'    => $step,
				'done'    => false,
				'message' => 'Error Loading statistics_meta',
				'data'    => $this->new,
			) );
		}

		if ( ! $current ) {
			return $this->return_error( array(
				'step'    => $step,
				'done'    => false,
				'message' => 'Error Loading statistics_meta',
				'data'    => $this->db,
			) );
		}

		$existing = array();
		foreach ( $current as $row ) {
			$existing[ $row['statistic_id'] ] = $row;
		}

		foreach ( $results as $result ) {
			$org_id       = $result['id'];
			$statistic_id = $result['statistic_id'];

			$row = $result;
			unset( $row[ 'id' ] ); // Should not be used.

			if ( isset( $existing[ $statistic_id ] ) ) {
				// Exists already.
				$id     = $existing[ $statistic_id ]['id'];

				$update = $this->pdo->sql_update( $row );

				$this->pdo->exec( "UPDATE {$main_meta_table} SET {$update} WHERE id = {$id}" );
			} else {
				// New entity.
				$insert = $this->pdo->sql_insert( $row );

				$this->pdo->exec( "INSERT INTO {$main_meta_table} {$insert}" );

				$id = $this->pdo->query_value( "SELECT id FROM {$main_meta_table} WHERE statistic_id = '{$statistic_id}'" );
			}

			$this->pdo->exec( "INSERT INTO {$this->merge_table} VALUES ( {$id}, {$org_id}, '{$statistic_id}' )" );
		}

		$this->messages[] = array(
			'step'    => $step,
			'done'    => true,
			'message' => 'statistics_meta entities merged',
			'data'    => $this->db,
		);

		$this->steps_done[ $step ] = true;
	}

	/**
	 * Copy and convert short term statistics (convert metadata ID).
	 */
	public function merge_short_term() {
		$this->pdo->truncate_table( 'main.statistics_short_term' );
		return $this->merge_records( 'statistics_short_term' );
	}

	/**
	 * Copy and convert short term statistics (convert metadata ID).
	 */
	public function merge_long_term() {
		return $this->merge_records( 'statistics' );
	}

	/**
	 * Copy and convert statistics (convert metadata ID).
	 */
	public function merge_records( $table ) {
		$step = $this->step;
		$done = $this->steps_done[ $step ] ?? null;
		if ( true === $done ) {
			return true;
		}

		$this->pdo->exec( "ATTACH `{$this->new}` as db_new" );

		if ( ! $done ) {
			$done = 0;
		}

		$limit = (int) $this->interval;
		$offset = $done * $limit;

		$results     = $this->pdo->query( "SELECT * FROM db_new.{$table} LIMIT {$limit} OFFSET {$offset}" );
		$num_results = 0;
		foreach ( $results as $row ) {
			$num_results++;
			unset( $row['id'] );
			$row['metadata_id'] = $this->convert_id( $row['metadata_id'] );

			// Recalculate sum.
			if ( ! empty( $this->sums[ $row['metadata_id'] ] ) ) {
				$row['sum'] = $this->sums[ $row['metadata_id'] ]['sum'] + (float) $row['sum'];
			}

			$insert = $this->pdo->sql_insert( $row );

			$this->pdo->exec( "INSERT INTO main.{$table} {$insert}" );
		}

		if ( $num_results < $this->interval ) {
			// Less records than the interval, must be the latest iteration.
			$total = $this->pdo->query_num_rows( 'db_new.' . $table );

			$this->messages[] = array(
				'step'    => $step,
				'done'    => true,
				'message' => $table . ' all ' . $total . ' entities merged',
				'data'    => $this->db,
			);

			$this->steps_done[ $step ] = true;
		} else {
			$done++;

			$entities_done = $offset + $limit;

			$this->messages[] = array(
				'step'    => $step,
				'done'    => $done,
				'message' => "{$table} entities {$offset} to {$entities_done} merged",
				'data'    => $this->db,
			);

			$this->steps_done[ $step ] = $done;
		}
	}

	public function convert_id( $id_org, $context = null ) {
		$sql = "SELECT id FROM main.statistics_merge WHERE id_org = {$id_org}";
		$id  = $this->pdo->query_value( $sql );

		if ( ! $id ) {
			$this->return_error( array(
				'step'    => $this->step,
				'message' => 'Could not convert ID: ' . $id_org,
				'data'    => var_export( $context, true ) . ' | ' . $sql,
				'done'    => false,
			) );

			$id = $id_org;
		}

		return $id;
	}

	public function return_error( $message ) {
		$this->messages[] = $message;
		$this->done = true;
		return false;
	}
}
