<?php

if ( ! empty( $_POST[ 'db_new' ] ) && ! empty( $_POST[ 'db_old' ] ) ) {
	$new        = $_POST[ 'db_new' ];
	$old        = $_POST[ 'db_old' ];
	$db         = $_POST[ 'db' ] ?? null;
	$interval   = $_POST[ 'interval' ] ?? null;
	$steps_done = $_POST[ 'steps_done' ] ?? array();
	$sums       = $_POST[ 'sums' ] ?? array();

	if ( ! is_array( $steps_done ) ) {
		$steps_done = json_decode( $_POST[ 'steps_done' ] );
	}

	foreach ( $steps_done as $key => $value ) {
		if ( $value && ! is_numeric( $value ) ) {
			$steps_done[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		}
	}

	require_once 'merge-sqlite.php';

	$merge  = new merge_sqlite( $new, $old, $db );
	$status = $merge->run( $steps_done, $interval );

	echo json_encode( $status );
	die;
}
