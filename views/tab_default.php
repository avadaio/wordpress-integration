<h3>AVADA API Settings</h3>
<form method="post" action="options.php">
<?php
	settings_fields('avada_woo_connection');   
	do_settings_sections('avada-woo-connection');
	submit_button(); 
?>
</form>
