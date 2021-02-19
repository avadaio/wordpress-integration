<?php 

if(!function_exists('avada_write_log')) {

	function avada_write_log($log) {
		if (true === WP_DEBUG) {
			if (is_array($log) || is_object($log)) {
				error_log(print_r($log, true));
			} else {
				error_log($log);
			}
		}
	}

}

if(!function_exists('pre')) {

	function pre($list = '', $exit = true) {

		echo '<pre>';

		print_r($list);

		if($exit) die();
		
	}
}

?>