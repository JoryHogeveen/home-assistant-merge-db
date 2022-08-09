<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
	(function ($) {

		var running    = false,
			formdata   = null,
			steps_done = {};

		jQuery(document).ready(function() {
			$(document).on( 'click', '#run', function(e) {
				e.preventDefault();
				formdata = null; // Reset.
				if ( ! running ) {
					run();
				}
			} );
		});

		function run() {
			if ( ! formdata ) {
				formdata = $('#form').serializeObject();
			}

			$.ajax( {
				type: "POST",
				url: '/ajax.php',
				data: formdata,
				dataType: 'json',
				success: function( resp ) {
					if ( resp.hasOwnProperty( 'steps_done' ) ) {
						steps_done = resp.steps_done;
					}


					if ( resp.hasOwnProperty( 'done' ) ) {
						if ( ! resp.done ) {
							formdata.db_new     = resp.new;
							formdata.db_old     = resp.old;
							formdata.db         = resp.db;
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
