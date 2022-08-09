<?php
class merge_sqlite
{
	public $new = null;
	public $old = null;
	public $db = null;

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
	}
}
