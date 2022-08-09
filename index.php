<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
<style>
	select {
		resize: vertical !important;
	}
</style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
	(function ($) {

		var running    = false,
			terminate  = false,
			formdata   = null,
			steps_done = {};

		jQuery(document).ready(function() {

			$('#continue').hide();

			$(document).on( 'click', '#run', function(e) {
				e.preventDefault();
				terminate = false;
				formdata = null; // Reset.
				if ( ! running ) {
					run();
				}
			} );

			$(document).on( 'click', '#continue', function(e) {
				e.preventDefault();
				terminate = false;
				run();
				$('#continue').hide();
			} );

			$(document).on( 'click', '#stop', function(e) {
				e.preventDefault();
				terminate = true;
				$('#continue').show();
			} );

			$(document).on( 'change', '#db_old', function() {
				var data = {
					db: $(this).val(),
					load_sum_entities: true,
				}
				$.ajax( {
					type: "POST",
					url: 'ajax.php',
					data: data,
					dataType: 'json',
					success: function( resp ) {
						if ( resp.hasOwnProperty( 'entities' ) ) {
							var options = '';
							$.each( resp.entities, function( key, value ) {
								options += '<option value="' + value.statistic_id + '">' + value.statistic_id + ' (' + value.unit_of_measurement + ')</option>';
							} );

							$('#sums').replaceWith( '<select class="form-control custom-select" id="sums" name="sums" multiple=1 size=10>' + options + '</select>' );
						}
					}
				} );
			} );

		});

		function run() {
			running = true;
			if ( terminate ) {
				running = false;
				return;
			}
			if ( ! formdata ) {
				formdata = $('#form').serializeObject();
			}

			$.ajax( {
				type: "POST",
				url: 'ajax.php',
				data: formdata,
				dataType: 'json',
				success: function( resp ) {
					if ( resp.hasOwnProperty( 'steps_done' ) ) {
						steps_done = resp.steps_done;
					}

					if ( resp.hasOwnProperty( 'messages' ) && resp.messages ) {
						var table = $('#status tbody');
						$.each( resp.messages, function( key, value ) {

							var classes = '',
								row = '',
								log = '',
								key = Date.now();

							classes += ( value.done ) ? 'table-success' : 'table-warning';

							classes = ' class="' + classes + '"';

							row += '<th scope="row">' + value.step + '</th>';
							row += '<td>' + value.message + '</td>';
							row += '<td>' + value.data + log + '</td>';
							row += '<td' + classes + '>' + value.done + '</td>';

							table.prepend( '<tr>' + row + '</tr>' );

							$( '#toggle-' + key ).on( 'click', function(e) {
								e.preventDefault();
								$( $(this).data('target') ).slideToggle();
							} );
						} );
					}

					if ( resp.hasOwnProperty( 'done' ) ) {
						if ( ! resp.done ) {
							formdata.db_new     = resp.new;
							formdata.db_old     = resp.old;
							formdata.db         = resp.db;
							formdata.sums       = resp.sums;
							formdata.steps_done = steps_done;
							run();
						}
					}
				}
			} ).always( function() {
				running = false;
			} );
		}

	    if ('function' !== typeof $.fn.serializeObject) {
	        $.fn.serializeObject = function () {
	            "use strict";

	            var result = {};
	            var extend = function (i, element) {
	                var node = result[element.name];

	                // If node with same name exists already, need to convert it to an array as it
	                // is a multi-value field (i.e., checkboxes)

	                if ('undefined' !== typeof node && node !== null) {
	                    if ($.isArray(node)) {
	                        node.push(element.value);
	                    } else {
	                        result[element.name] = [node, element.value];
	                    }
	                } else {
	                    result[element.name] = element.value;
	                }
	            };

	            $.each(this.serializeArray(), extend);
	            return result;
	        };
	    }
	})(jQuery);

</script>

<?php
$files = scandir( dirname( __FILE__ ) );
$files = array_filter( $files, function( $item ) {
	return ! is_dir( $item );
} );
?>
<div class="p-3 container">
<form id="form" method="post">
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

	<div class="form-group">
		<label for="interval">Interval:</label>
		<input class="form-control" type="number" name="interval" id="interval" value="1000">
	</div>

	<div class="form-group">
		<label for="sums">Recalculate sums for the following entities (new line per entity):</label>
		<textarea class="form-control" name="sums" id="sums"></textarea>
	</div>

	<?php
	include_once 'merge-sqlite.php';
	$class = new merge_sqlite();
	$steps = $class->steps;
	?>
	<div class="form-group">
		<label for="steps">Select steps to be taken (default: all).</label>
		<select class="form-control custom-select" name="steps" id="steps" multiple=1 size=<?= count( $steps ) ?>>
			<?php foreach ( $steps as $step ) : ?>
				<option value="<?= $step ?>" selected><?= $step ?></option>
			<?php endforeach; ?>
		</select>
	</div>
</form>


<button id="run" type="button" class="btn btn-primary">Run</button>
<button id="stop" type="button" class="btn btn-secondary">Stop</button>
<button id="continue" type="button" class="btn btn-secondary">Continue</button>

<hr>

<table id="status" class="table table-bordered table-hover text-left">
	<thead class="thead-dark">
		<th scope="col">Step</th>
		<th scope="col">Message</th>
		<th scope="col">Data</th>
		<th scope="col">Interval/Done</th>
	</thead>
	<tbody>
	</tbody>
</table>

</div>
