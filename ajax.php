<?php

if ( ! empty( $_POST[ 'load_sum_entities' ] ) && ! empty( $_POST[ 'db' ] ) ) {
	require_once 'db-sqlite.php';

	$db = new db_sqlite( $_POST[ 'db' ] );
	$results = $db->query( 'SELECT statistic_id FROM statistics_meta WHERE has_sum = 1' );

	$entities = array();
	foreach ( $results as $row ) {
		$entities[] = $row;
	}

	echo json_encode( array( 'entities' => $entities ) );
	die;
}

if ( ! empty( $_POST[ 'db_new' ] ) && ! empty( $_POST[ 'db_old' ] ) ) {
	$new        = $_POST[ 'db_new' ];
	$old        = $_POST[ 'db_old' ];
	$db         = $_POST[ 'db' ] ?? null;
	$interval   = $_POST[ 'interval' ] ?? null;
	$sums       = $_POST[ 'sums' ] ?? array();
	$steps      = $_POST[ 'steps' ] ?? array();
	$steps_done = $_POST[ 'steps_done' ] ?? array();

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
	$merge->run( $steps_done, $interval, $sums, $steps );

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
