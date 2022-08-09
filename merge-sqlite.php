<?php
class merge_sqlite
{
	public $new = null;
	public $old = null;
	/**
	 * Constructor
	 */
	public function __construct( $new, $old ) {
		$this->new = $new;
		$this->old = $old;
	}
}
