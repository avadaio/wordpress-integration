<h3>Woocommerce Sync Avada API</h3>
<input type="button" id="sync_customer" class="button button-primary" value="Đồng bộ khách hàng">
&nbsp;&nbsp;
<input type="button" id="sync_order" class="button button-success" value="Đồng bộ đơn hàng">

<br>

<div id="myProgress">
	<div id="myBar"></div>
</div>

<style>
	#myProgress {
		margin-top: 10px;
	}

	#myBar {
		width: 0%;
		height: 30px;
		background-color: #007cba;
		text-align: center; /* To center it horizontally (if you want) */
		line-height: 30px; /* To center it vertically */
		color: white;
	}
</style>