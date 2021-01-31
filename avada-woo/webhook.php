<?php 

// Webhook Sync Order
add_action('woocommerce_thankyou','webhook_sync_order');
function webhook_sync_order($order_id){
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
			"total_price"      => $order->get_subtotal(),
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

		if(!is_null($order_data) && !empty($order_data) && count($order_data) > 0) {
			$data_array[] = $order_data;
		}

		$data_array = json_encode($data_array);
		$data = '{"data": '.$data_array.'}';

		$option_connection = get_option('avada_woo_connection');

		$app_id = $option_connection['avada_woo_app_id'];
		$hmac_sha256 = base64_encode(hash_hmac('sha256', $data, $option_connection['avada_woo_secret_key'], true));
		
		$url = "https://app.avada.io/app/api/v1/orders/bulk";
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

		write_log($order_id .' - '. $response);

		curl_close($ch);
	}
}

// Webhook Sync Customer
add_action('woocommerce_thankyou','webhook_sync_customer');
function webhook_sync_customer($order_id){
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
		
		$email = isset($order_detail['billing']['email']) ? $order_detail['billing']['email'] : '';
		$first_name = isset($order_detail['billing']['first_name']) ? $order_detail['billing']['first_name'] : '';
		$last_name = isset($order_detail['billing']['last_name']) ? $order_detail['billing']['last_name'] : '';
		$phone = isset($order_detail['billing']['phone']) ? $order_detail['billing']['phone'] : 0;
		$country = isset($order_detail['billing']['country']) ? $order_detail['billing']['country'] : '';
		$city = isset($order_detail['billing']['city']) ? $order_detail['billing']['city'] : '';
		$address = isset($order_detail['billing']['address_1']) ? $order_detail['billing']['address_1'] : '';
		$orders_count = isset($list_order) ? count($list_order) : 0;

		// total spent
		$total_spent = 0;
		if($orders_count > 0) {
			
			$sql = "SELECT SUM(meta_value) FROM wp_postmeta WHERE meta_key = '_order_total' AND post_id IN (SELECT post_id FROM wp_postmeta WHERE meta_key = '_billing_email' AND meta_value = '{$order_detail['billing']['email']}' GROUP BY meta_value)";

			$total_spent = $wpdb->get_var($sql);

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
		write_log($order_id .' - '. $response);
		curl_close($ch);

	}
}

?>