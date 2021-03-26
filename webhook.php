<?php 

// Webhook Sync Order
add_action('woocommerce_thankyou','avada_webhook_sync_order');
function avada_webhook_sync_order($order_id){
	if (!$order_id )
		return;

	$order = wc_get_order($order_id);

	if(isset($order) && !is_null($order) && !empty($order) && $order->get_billing_email() && !empty($order->get_billing_email()) && !is_null($order->get_billing_email()) && strlen($order->get_billing_email()) > 0) {

		$order_data = [
			"id"       => $order->get_id(),
			"email"    => $order->get_billing_email(),
			"status"   => "subcriber",
			"customer" => [
				"email"      => $order->get_billing_email(),
				"first_name" => $order->get_billing_first_name(),
				"last_name"  => $order->get_billing_last_name(),
				"phone"      => $order->get_billing_phone()
			],
			"currency"         => $order->get_currency(),
			"created_at"       => $order->get_date_created()->date('Y-m-d H:i:s'),
			"updated_at"       => $order->get_date_modified()->date('Y-m-d H:i:s'),
			"order_status_url" => "",
			"subtotal_price"   => $order->get_subtotal(),
			"total_price"      => $order->get_total(),
			"total_tax"        => $order->get_total_tax(),
			"total_weight"     => "0",
			"total_discounts"  => "0"
		];

		$line_items = [];
		foreach($order->get_items() as $item_id => $item) {
			$product = $item->get_product();
			$product_id = "";
			$product_sku = "";

			if (is_object($product)) {
				$product_id = $product->get_id();
				$product_sku = $product->get_sku();
			}

			$line_items[] = [
				"type"          => "product",
				"title"         => $item['name'],
				"name"          => $item['name'],
				"price"         => $order->get_item_total($item, false, false),
				"quantity"      => wc_stock_amount($item['qty']),
				"sku"           => $product_sku,
				"product_id"    => (!empty($item->get_variation_id()) && ('product_variation' === $product->post_type )) ? $product->get_parent_id() : $product_id,
				"image"         => wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'thumbnail', TRUE)[0],
				"frontend_link" => get_permalink($product_id),
				"line_price"    => $order->get_item_total($item, false, false),
				"bundle_items"  => [],
				'meta'          => wc_display_item_meta($item, ['echo' => false])
			];
		}

		$order_data['line_items'] = $line_items;

		$order_data = json_encode($order_data);
		$data = '{"data": '.$order_data.'}';

		$option_connection = get_option('avada_woo_connection');

		$app_id = $option_connection['avada_woo_app_id'];
		$hmac_sha256 = base64_encode(hash_hmac('sha256', $data, $option_connection['avada_woo_secret_key'], true));
		
		$url = "https://app.avada.io/app/api/v1/orders";
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"x-emailmarketing-app-id: {$app_id}",
			"x-emailmarketing-hmac-sha256: {$hmac_sha256}",
			"X-EmailMarketing-Wordpress: true"
		));

		$response = curl_exec($ch);

		avada_write_log($order_id .' - '. $response);

		curl_close($ch);
	}
}

// Webhook Sync Customer
add_action('woocommerce_thankyou','avada_webhook_sync_customer');
function avada_webhook_sync_customer($order_id){
	if (!$order_id )
		return;

	global $wpdb; 

	$order_data = wc_get_order($order_id);
	$order_detail = $order_data->get_data();

	if(isset($order_detail['billing']['email']) && strlen($order_detail['billing']['email']) > 0) {

		// order count
		$sql = "SELECT * FROM {$wpdb->prefix}posts p
			INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
			WHERE p.post_type = 'shop_order'
			AND pm.meta_key = '_billing_email'
			AND pm.meta_value = '{$order_detail['billing']['email']}'";

		$list_order = $wpdb->get_results($sql, ARRAY_A);
		
		$email        = isset($order_detail['billing']['email']) ? $order_detail['billing']['email'] : '';
		$first_name   = isset($order_detail['billing']['first_name']) ? $order_detail['billing']['first_name'] : '';
		$last_name    = isset($order_detail['billing']['last_name']) ? $order_detail['billing']['last_name'] : '';
		$phone        = isset($order_detail['billing']['phone']) ? $order_detail['billing']['phone'] : 0;
		$country      = isset($order_detail['billing']['country']) ? $order_detail['billing']['country'] : '';
		$city         = isset($order_detail['billing']['city']) ? $order_detail['billing']['city'] : '';
		$address      = isset($order_detail['billing']['address_1']) ? $order_detail['billing']['address_1'] : '';
		$orders_count = isset($list_order) ? count($list_order) : 0;

		// total spent
		$total_spent = 0;
		if($orders_count > 0) {
			
			$sql = "SELECT SUM(meta_value) as total_spent FROM wp_postmeta WHERE meta_key = '_order_total' AND post_id IN (SELECT post_id FROM wp_postmeta WHERE meta_key = '_billing_email' AND meta_value = '{$order_detail['billing']['email']}' GROUP BY meta_value)";

			$result = $wpdb->get_row($sql);

			$total_spent = $result->total_spent;

		}

		$data_json = 
			'
				{
					"data": {
						"description": "",
						"email": "'.$email.'",
						"firstName": "'.$first_name.'",
						"isSubscriber": true,
						"lastName": "'.$last_name.'",
						"phoneNumber": "'.$phone.'",
						"phoneNumberCountry": "'.$country.'",
						"source": "wordpress",
						"orders_count": '.$orders_count.',
						"total_spent": '.$total_spent.',
						"country": "'.$country.'",
						"city": "'.$city.'",
						"address": "'.$address.'",
						"tags": "WordPress,Woocommerce"
					}
				}
			';
		
		$option_connection = get_option('avada_woo_connection');

		$app_id = $option_connection['avada_woo_app_id'];
		$hmac_sha256 = base64_encode(hash_hmac('sha256', $data_json, $option_connection['avada_woo_secret_key'], true));

		$url = "https://app.avada.io/app/api/v1/customers";
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"x-emailmarketing-app-id: {$app_id}",
			"x-emailmarketing-hmac-sha256: {$hmac_sha256}",
			"X-EmailMarketing-Wordpress: true"
		));

		$response = curl_exec($ch);
		avada_write_log($order_id .' - '. $response);
		curl_close($ch);

	}
}

// Webhook Update Order Completed / Refund
add_action('woocommerce_order_status_changed', 'avada_webhook_update_status_order');
function avada_webhook_update_status_order($order_id) {
	if (!$order_id )
		return;

	$order = wc_get_order($order_id);

	if(isset($order) && !is_null($order) && !empty($order) && ($order->has_status('completed') || $order->has_status('refunded'))) {

		$order_data = [
			"id"       => $order->get_id(),
			"order_id" => $order->get_id(),
			"email"    => $order->get_billing_email(),
			"status"   => "subcriber",
			"customer" => [
				"email"      => $order->get_billing_email(),
				"first_name" => $order->get_billing_first_name(),
				"last_name"  => $order->get_billing_last_name(),
				"phone"      => $order->get_billing_phone()
			],
			"currency"         => $order->get_currency(),
			"created_at"       => $order->get_date_created()->date('Y-m-d H:i:s'),
			"updated_at"       => $order->get_date_modified()->date('Y-m-d H:i:s'),
			"order_status_url" => "",
			"subtotal_price"   => $order->get_subtotal(),
			"total_price"      => $order->get_total(),
			"total_tax"        => $order->get_total_tax(),
			"total_weight"     => "0",
			"total_discounts"  => "0"
		];

		$line_items = [];
		foreach($order->get_items() as $item_id => $item) {
			$product = $item->get_product();
			$product_id = "";
			$product_sku = "";

			if (is_object($product)) {
				$product_id = $product->get_id();
				$product_sku = $product->get_sku();
			}

			$line_items[] = [
				"type"          => "product",
				"title"         => $item['name'],
				"name"          => $item['name'],
				"price"         => $order->get_item_total($item, false, false),
				"quantity"      => wc_stock_amount($item['qty']),
				"sku"           => $product_sku,
				"product_id"    => (!empty($item->get_variation_id()) && ('product_variation' === $product->post_type )) ? $product->get_parent_id() : $product_id,
				"image"         => wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'thumbnail', TRUE)[0],
				"frontend_link" => get_permalink($product_id),
				"line_price"    => $order->get_item_total($item, false, false),
				"bundle_items"  => [],
				'meta'          => wc_display_item_meta($item, ['echo' => false])
			];
		}

		$order_data['line_items'] = $line_items;

		$order_data = json_encode($order_data);
		$data = '{"data": '.$order_data.'}';

		$option_connection = get_option('avada_woo_connection');

		$app_id = $option_connection['avada_woo_app_id'];
		$hmac_sha256 = base64_encode(hash_hmac('sha256', $data, $option_connection['avada_woo_secret_key'], true));
		
		if($order->has_status('completed')) { // Order Completed
			$url = "https://app.avada.io/app/api/v1/orders/complete";
		} else if($order->has_status('refunded')) { // Order Refund
			$url = "https://app.avada.io/app/api/v1/orders/refund";
		}
		
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"x-emailmarketing-app-id: {$app_id}",
			"x-emailmarketing-hmac-sha256: {$hmac_sha256}",
			"X-EmailMarketing-Wordpress: true"
		));

		$response = curl_exec($ch);

		avada_write_log($order_id .' - '. $response);

		curl_close($ch);
	}
}

// Add Script App ID, Popup, Thankyou
add_action('wp_head', 'avada_popup_thankyou');
function avada_popup_thankyou() {
	$avada = new Avada_Woo;
	$avada_woo_connection = $avada->option_connection;
	?>
		<script>
			window.AVADA_EM = window.AVADA_EM || {};
			window.AVADA_EM.shopId = "<?php echo !is_null($avada_woo_connection['avada_woo_app_id']) ? $avada_woo_connection['avada_woo_app_id'] : '' ?>";
		</script>

		<script data-cfasync="false" type="text/javascript">(function(b){var s=document.createElement("script");s.type="text/javascript";s.async=true;s.src=b;var x=document.getElementsByTagName("script")[0];x.parentNode.insertBefore(s,x);})("https://app.avada.io/avada-sdk.min.js");</script>
	<?php
}

// Add Script Thank You Page
add_action('woocommerce_thankyou', 'avada_script_thankyou');
function avada_script_thankyou($order_id) {
	if(!$order_id) return;
	$order = wc_get_order($order_id);

	if(isset($order) && !is_null($order)) {

		global $wpdb;

		$session_id = WC()->session->get('avada_session_id');

		$sql = "SELECT * FROM {$wpdb->prefix}avada_cart_abandonment WHERE session_id = '{$session_id}'";

		$result = $wpdb->get_row($sql);

		if(isset($result) && !is_null($result)) {

			$data_customer = unserialize($result->customer_info);

			$order_data = [
				"id"                     => isset($result->id) ? (int)$result->id : '',
				"abandoned_checkout_url" => isset($result->link) ? $result->link : null,
				"email"                  => isset($data_customer['avada_billing_email']) ? $data_customer['avada_billing_email'] : null,
				"created_at"             => isset($result->created_at) ? $result->created_at : null,
				"updated_at"             => isset($result->updated_at) ? $result->updated_at : null,
				"completed_at"           => get_date_from_gmt(date('Y-m-d H:i:s', time())),
				"timezone"				 => !is_null(get_option('timezone_string')) && !empty(get_option('timezone_string')) ? get_option('timezone_string') : get_option('gmt_offset'),
				"phone"                  => isset($data_customer['avada_billing_phone']) ? $data_customer['avada_billing_phone'] : null,
				"customer_locale"        => "",
				"subtotal_price"         => WC()->cart->subtotal,
				"total_tax"              => WC()->cart->get_total_tax(),
				"total_price"            => WC()->cart->total,
				"currency"               => get_woocommerce_currency(),
				"presentment_currency"	 => get_woocommerce_currency(),
				"customer" => [
					"id"         => 0,
					"email"      => isset($data_customer['avada_billing_email']) ? $data_customer['avada_billing_email'] : null,
					"name"       => isset($data_customer['avada_billing_first_name']) ? $data_customer['avada_billing_first_name'] : null,
					"first_name" => isset($data_customer['avada_billing_first_name']) ? $data_customer['avada_billing_first_name'] : null,
					"last_name"  => isset($data_customer['avada_billing_last_name']) ? $data_customer['avada_billing_last_name'] : null
				],
				"shipping_address" => [
					"name"          => isset($data_customer['avada_billing_first_name']) ? $data_customer['avada_billing_first_name'] : null,
					"last_name"     => isset($data_customer['avada_billing_last_name']) ? $data_customer['avada_billing_last_name'] : null,
					"phone"         => isset($data_customer['avada_billing_phone']) ? $data_customer['avada_billing_phone'] : null,
					"company"       => "",
					"country_code"  => isset($data_customer['avada_billing_country']) ? $data_customer['avada_billing_country'] : null,
					"zip"           => "",
					"address1"      => isset($data_customer['avada_billing_address_1']) ? $data_customer['avada_billing_address_1'] : null,
					"address2"      => "",
					"city"          => isset($data_customer['avada_billing_city']) ? $data_customer['avada_billing_city'] : null,
					"province_code" => "",
					"province"      => ""
				]
			];

			$order_data['line_items'] = json_decode($result->line_items);
			$order_data = json_encode($order_data);

			$data = '{"data": '.$order_data.'}';

			$option_connection = get_option('avada_woo_connection');

			$app_id = $option_connection['avada_woo_app_id'];
			$hmac_sha256 = base64_encode(hash_hmac('sha256', $data, $option_connection['avada_woo_secret_key'], true));

			$url = "https://app.avada.io/app/api/v1/checkouts";
			$ch = curl_init($url);

			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Content-Type: application/json",
				"x-emailmarketing-app-id: {$app_id}",
				"x-emailmarketing-hmac-sha256: {$hmac_sha256}",
				"X-EmailMarketing-Wordpress: true"
			));

			$response = curl_exec($ch);
		}
		
		?>
			<script data-cfasync="false" type="text/javascript">
			var AVADA_EM = {
			shopId: window.AVADA_EM.shopId,
			vendor: "woocommerce",
			checkout: {
				revenue: "<?php echo $order->get_subtotal() ?>",
				currency: "<?php echo $order->get_currency() ?>",
				checkoutId: "<?php echo $_GET['key'] ?>",
				checkoutEmail: "<?php echo $order->get_billing_email() ?>"
				}
			}
			</script>
		<?php

		if(!is_null($session_id)) {
			global $wpdb;
			$table_name = $wpdb->prefix."avada_cart_abandonment";
			$wpdb->delete($table_name, ['session_id' => $session_id]);
			avada_unset_session();
		}

	}

}

add_filter('wp', 'avada_restore_cart_abandonment', 10);
function avada_restore_cart_abandonment() {
	global $wpdb;
	$avada_token_cart = isset($_GET['avada_token_cart']) ? $_GET['avada_token_cart'] : null;
	if(!is_null($avada_token_cart)) {
		$session_id = base64_decode($avada_token_cart);
		$sql = "SELECT id, email, cart_content, customer_info, session_id FROM {$wpdb->prefix}avada_cart_abandonment WHERE session_id = '{$session_id}'";
		$result = $wpdb->get_row($sql);

		if(!is_null($result)) {

			WC()->session->set('avada_session_id', $result->session_id);
			WC()->session->set('avada_cart_aban_id', $result->id);

			$cart_content = unserialize($result->cart_content);
			if($cart_content) {
				WC()->cart->empty_cart();
				wc_clear_notices();
				foreach($cart_content as $cart_item ) {
					$variation_data = [];
					if(isset($cart_item['variation'])) {
						foreach($cart_item['variation'] as $key => $value) {
							$variation_data[$key] = $value;
						}
					}
					WC()->cart->add_to_cart($cart_item['product_id'], $cart_item['quantity'], $cart_item['variation_id'], $variation_data, $cart_item);
				}
			}

			$customer_info = unserialize($result->customer_info);

			if(isset($customer_info['avada_billing_first_name'])) {
				$_POST['billing_first_name'] = sanitize_text_field($customer_info['avada_billing_first_name']);
			}

			if(isset($customer_info['avada_billing_last_name'])) {
				$_POST['billing_last_name'] = sanitize_text_field($customer_info['avada_billing_last_name']);
			}

			if(isset($customer_info['avada_billing_phone'])) {
				$_POST['billing_phone'] = sanitize_text_field($customer_info['avada_billing_phone']);
			}
			
			if(isset($result->email) && !empty($result->email) && !is_null($result->email)) {
				$_POST['billing_email'] = sanitize_email($result->email);
			}

			if(isset($customer_info['avada_billing_city'])) {
				$_POST['billing_city'] = sanitize_text_field($customer_info['avada_billing_city']);
			}

			if(isset($customer_info['avada_billing_address_1'])) {
				$_POST['billing_address_1'] = sanitize_text_field($customer_info['avada_billing_address_1']);
			}

			if(isset($customer_info['avada_billing_country'])) {
				$_POST['billing_country'] = sanitize_text_field($customer_info['avada_billing_country']);
			}
		} else {
			WC()->cart->empty_cart();
			avada_unset_session();
			wc_clear_notices();
		}
	}
}

add_action('woocommerce_add_to_cart', 'avada_create_cart_abandonment');
function avada_create_cart_abandonment() {

	$cart = WC()->cart->get_cart();
	$line_items = [];
	foreach($cart as $item_id => $item) {
		$line_items[] = [
			"type"          => "downloadable",
			"title"         => $item['data']->get_title(),
			"price"         => $item['data']->get_price(),
			"quantity"      => $item['quantity'],
			"sku"           => $item['data']->get_sku(),
			"product_id"    => $item['data']->get_id(),
			"image"         => wp_get_attachment_url($item['data']->get_image_id()),
			"frontend_link" => $item['data']->get_permalink(),
			"line_price"    => $item['data']->get_price(),
			"bundle_items"  => []
		];
	}

	$result = avada_insert_table($line_items);

	$order_data = [
		"id"                     => isset($result['id']) ? (int)$result['id'] : '',
		"abandoned_checkout_url" => isset($result['link']) ? $result['link'] : null,
		"email"                  => isset($data_customer['avada_billing_email']) ? $data_customer['avada_billing_email'] : null,
		"created_at"             => isset($result['created_at']) ? $result['created_at'] : null,
		"updated_at"             => get_date_from_gmt(date('Y-m-d H:i:s', time())),
		"completed_at"           => null,
		"timezone"				 => !is_null(get_option('timezone_string')) && !empty(get_option('timezone_string')) ? get_option('timezone_string') : get_option('gmt_offset'),
		"phone"                  => isset($data_customer['avada_billing_phone']) ? $data_customer['avada_billing_phone'] : null,
		"customer_locale"        => "",
		"subtotal_price"         => WC()->cart->subtotal,
		"total_tax"              => WC()->cart->get_total_tax(),
		"total_price"            => WC()->cart->total,
		"currency"               => get_woocommerce_currency(),
		"presentment_currency"	 => get_woocommerce_currency(),
		"customer" => [
			"id"         => 0,
			"email"      => isset($data_customer['avada_billing_email']) ? $data_customer['avada_billing_email'] : null,
			"name"       => isset($data_customer['avada_billing_first_name']) ? $data_customer['avada_billing_first_name'] : null,
			"first_name" => isset($data_customer['avada_billing_first_name']) ? $data_customer['avada_billing_first_name'] : null,
			"last_name"  => isset($data_customer['avada_billing_last_name']) ? $data_customer['avada_billing_last_name'] : null
		],
		"shipping_address" => [
			"name"          => isset($data_customer['avada_billing_first_name']) ? $data_customer['avada_billing_first_name'] : null,
			"last_name"     => isset($data_customer['avada_billing_last_name']) ? $data_customer['avada_billing_last_name'] : null,
			"phone"         => isset($data_customer['avada_billing_phone']) ? $data_customer['avada_billing_phone'] : null,
			"company"       => "",
			"country_code"  => isset($data_customer['avada_billing_country']) ? $data_customer['avada_billing_country'] : null,
			"zip"           => "",
			"address1"      => isset($data_customer['avada_billing_address_1']) ? $data_customer['avada_billing_address_1'] : null,
			"address2"      => "",
			"city"          => isset($data_customer['avada_billing_city']) ? $data_customer['avada_billing_city'] : null,
			"province_code" => "",
			"province"      => ""
		]
	];

	$order_data['line_items'] = $line_items;
	$order_data = json_encode($order_data);

	$data = '{"data": '.$order_data.'}';

	$option_connection = get_option('avada_woo_connection');

	$app_id = $option_connection['avada_woo_app_id'];
	$hmac_sha256 = base64_encode(hash_hmac('sha256', $data, $option_connection['avada_woo_secret_key'], true));

	$url = "https://app.avada.io/app/api/v1/checkouts";
	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		"Content-Type: application/json",
		"x-emailmarketing-app-id: {$app_id}",
		"x-emailmarketing-hmac-sha256: {$hmac_sha256}",
		"X-EmailMarketing-Wordpress: true"
	));

	$response = curl_exec($ch);
}

// add_action('init', 'avada_unset_session');
function avada_unset_session() {
	WC()->session->__unset('avada_session_id');
	WC()->session->__unset('avada_cart_aban_id');
}

function avada_insert_table($line_items = [])
{
	global $wpdb;

	$table_name    = $wpdb->prefix."avada_cart_abandonment";

	$session_id         = WC()->session->get('avada_session_id');
	$avada_cart_aban_id = WC()->session->get('avada_cart_aban_id');

	$checkout_url = wc_get_checkout_url();

	$cart         = serialize(WC()->cart->get_cart());

	if(!is_null($session_id)) {

		$sql = "SELECT * FROM {$table_name} WHERE session_id = '{$session_id}'";
		$order_current = $wpdb->get_row($sql);

		if(isset($order_current) && !is_null($order_current) && !empty($order_current)) {

			$updated_at  = get_date_from_gmt(date('Y-m-d H:i:s', time()));
			$link        = add_query_arg(['avada_token_cart' => base64_encode($session_id)], $checkout_url);
			$data_update = ['cart_content' => $cart, 'link' => $link, 'line_items' => json_encode($line_items), 'updated_at' => $updated_at];
			$data_where  = ['id' => $avada_cart_aban_id, 'session_id' => $session_id];

			$wpdb->update($table_name , $data_update, $data_where);
			$id = $avada_cart_aban_id;
			$created_at = $order_current->created_at;

		} else {

			$created_at   = get_date_from_gmt(date('Y-m-d H:i:s', time()));
			$updated_at   = get_date_from_gmt(date('Y-m-d H:i:s', time()));
			$session_id   = md5($cart . time());
			$link         = add_query_arg(['avada_token_cart' => base64_encode($session_id)], $checkout_url);
			
			$insert_query = "INSERT IGNORE INTO ".$table_name."(`cart_content`, `session_id`, `link`, `line_items`, `created_at`, `updated_at`) VALUES ('".$cart."', '".$session_id."', '".$link."', '".json_encode($line_items)."', '".$created_at."', '".$updated_at."')"; 
			$insertResult = $wpdb->query($insert_query);
			$id           = $wpdb->insert_id;

		}

	} else {

		$created_at    = get_date_from_gmt(date('Y-m-d H:i:s', time()));
		$updated_at    = get_date_from_gmt(date('Y-m-d H:i:s', time()));
		$session_id    = md5($cart . time());
		$link          = add_query_arg(['avada_token_cart' => base64_encode($session_id)], $checkout_url);

		$insert_query = "INSERT IGNORE INTO ".$table_name."(`cart_content`, `session_id`, `link`, `line_items`, `created_at`, `updated_at`) VALUES ('".$cart."', '".$session_id."', '".$link."', '".json_encode($line_items)."', '".$created_at."', '".$updated_at."')"; 
		$insertResult = $wpdb->query($insert_query);
		$id 		  = $wpdb->insert_id;
		
	}

	WC()->session->set('avada_session_id', $session_id);
	WC()->session->set('avada_cart_aban_id', $id);

	if($id) return ['link' => $link, 'id' => $id, 'created_at' => $created_at];
	
}

?>