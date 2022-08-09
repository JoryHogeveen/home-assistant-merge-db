<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

<?php
$files = scandir( dirname( __FILE__ ) );
$files = array_filter( $files, function( $item ) {
	return ! is_dir( $item );
} );
?>
<div class="p-3 container">
<form id="form">
  	<div class="form-group">
		<label for="db_old">DB OLD:</label>
		<select class="form-control" name="db_old" id="db_old">
			<option value=""> - select - </option>
			<?php foreach ( $files as $file ) : ?>
				<option value="<?= $file ?>"><?= $file ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="form-group">
		<label for="db_new">DB NEW:</label>
		<select class="form-control" name="db_new" id="db_new">
			<option value=""> - select - </option>
			<?php foreach ( $files as $file ) : ?>
				<option value="<?= $file ?>"><?= $file ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="form-group">
		<label for="db">DB (CONTINUE):</label>
		<select class="form-control" name="db" id="db">
			<option value=""> - only select if you want to continue existing merge - </option>
			<?php foreach ( $files as $file ) : ?>
				<option value="<?= $file ?>"><?= $file ?></option>
			<?php endforeach; ?>
		</select>
	</div>
</form>


<button id="run" type="button" class="btn btn-primary">Run</button>

</div>
