<?php

if ( ! empty( $_POST[ 'db_new' ] ) && ! empty( $_POST[ 'db_old' ] ) ) {
	$new        = $_POST[ 'db_new' ];
	$old        = $_POST[ 'db_old' ];
	$db         = $_POST[ 'db' ] ?? null;

	include 'merge-sqlite.php';

	$status = new merge_sqlite( $new, $old, $db );

	echo json_encode( $status );
	die;
}
