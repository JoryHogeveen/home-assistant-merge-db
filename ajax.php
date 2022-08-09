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

	if ( ! is_array( $sums ) ) {
		$sums = array_filter( array_map( 'trim', preg_split( '/\r\n|[\r\n]|,/', $sums ) ) );
	}

	require_once 'merge-sqlite.php';

	$merge = new merge_sqlite( $new, $old, $db );
	$merge->run( $steps_done, $interval, $sums );

	$return = get_object_vars( $merge );

	// Prevent recurse.
	foreach ( $return as $key => $val ) {
		if ( is_object( $val ) ) {
			unset( $return[ $key ] );
		}
	}

	echo json_encode( $return );
	die;
}

$obj = new stdClass();
$obj->messages = array();
$obj->messages[] = array(
	'step'    => 'ajax',
	'message' => 'Please provide parameters',
	'data'    => var_export( $_POST, true ),
	'done'    => false,
);
echo json_encode( $obj );
die;
