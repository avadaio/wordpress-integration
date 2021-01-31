<?php
/*
Plugin Name: Avada Woo
Plugin URI: https://jupitermedia.vn
Description: Kết nối Avada vs Woocommerce
Version: 1.0
Author: jupitermedia
Author URI: https://jupitermedia.vn
Text Domain: avada-woo
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
if(!function_exists('add_action')) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define('AVADA_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AVADA_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
/* ----------------------------------------------------------------------------
 * Create WordPress settings page For custom options
 * ------------------------------------------------------------------------- */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	if(!class_exists('Avada_Woo')) {

		class Avada_Woo {
			/**
			 * Array of custom settings/options
			**/
			private $option_connection;
			private $option_woo_auth;
			/**
			 * Constructor
			 */
			public function __construct() {

				$this->option_connection = get_option('avada_woo_connection');
				$this->option_woo_auth = get_option('avada_woo_auth');

				add_action('admin_menu', [$this, 'add_settings_page']);
				add_action('admin_init', [$this, 'page_avada_woo_connection']);
				add_action('admin_init', [$this, 'page_avada_woo_auth']);
				add_action('admin_enqueue_scripts', function(){
					wp_register_script('avada-woo-js', AVADA_WOO_PLUGIN_URL . 'js/function.js');

					wp_localize_script('avada-woo-js', 'avada_woo', [
						'url' => admin_url('admin-ajax.php')
					]);
					wp_enqueue_script('avada-woo-js');

					wp_register_style('avada-woo-css', AVADA_WOO_PLUGIN_URL . 'css/style.css');
					wp_enqueue_style('avada-woo-css');
				});

				add_action('wp_ajax_check_connection', [$this, 'check_connection']);
				add_action('wp_ajax_sync_customer', [$this, 'sync_customer']);
				add_action('wp_ajax_sync_order', [$this, 'sync_order']);
				add_action('wp_ajax_count_order', [$this, 'count_order']);
			}

			/**
			 * Add settings page
			 * The page will appear in Admin menu
			 */
			public function add_settings_page() {
				add_options_page(
					'Avada Woo', // Page title
					'Avada Woo', // Title
					'manage_options', // Capability
					'avada-woo', // Url slug
					array( $this, 'create_admin_page' ) // Callback
				);
			}

			/**
			 * Options page callback
			 */
			public function create_admin_page() {

				if(!current_user_can('manage_options')) return;

				//Get the active tab from the $_GET param
				$default_tab = null;
				$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

				?>
				<div class="wrap">

					<script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/10.13.3/sweetalert2.min.js"></script>
					<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/10.13.3/sweetalert2.min.css"/>

					<div class="loading_snipper">
						<div class='uil-ring-css'>
							<div></div>
						</div>
					</div>	

					<h2>AVADA WOOCOMMERCE</h2>

					<nav class="nav-tab-wrapper">
						<a href="?page=avada-woo" class="nav-tab <?php if($tab === null): ?> nav-tab-active <?php endif; ?>">Connect</a>
						<?php if(isset($this->option_connection['avada_woo_enable'])): ?>
						<a href="?page=avada-woo&tab=woocommerce" class="nav-tab <?php if($tab === 'woocommerce'): ?> nav-tab-active <?php endif; ?>">Woocommerce</a>
						<?php endif; ?>
					</nav>

					<div class="tab-content">
						<?php switch($tab) :
							case 'woocommerce':
							
								if(isset($this->option_connection['avada_woo_enable'])):
									require(AVADA_WOO_PLUGIN_DIR . 'views/tab_woo.php');
								endif;

								break;
							default:
								require(AVADA_WOO_PLUGIN_DIR . 'views/tab_default.php');
								break;
						endswitch; ?>
					</div>

				</div>
			<?php
			}

			/**
			 * Register and add settings
			 */
			public function page_avada_woo_connection() {
				register_setting(
					'avada_woo_connection', // Option group
					'avada_woo_connection', // Option name
					array( $this, 'sanitize' ) // Sanitize
				);

				add_settings_section(
					'avada_woo_connection', // ID
					'', // Title
					'', // Callback
					'avada-woo-connection' // Page
				);

				add_settings_field(
					'avada_woo_enable', 
					'Enable', 
					array( $this, 'enable_html' ), 
					'avada-woo-connection',
					'avada_woo_connection'
				);

				add_settings_field(
					'avada_woo_app_id', // ID
					'App ID', // Title 
					array( $this, 'app_id_html' ), // Callback
					'avada-woo-connection', // Page         
					'avada_woo_connection'
				);

				add_settings_field(
					'avada_woo_secret_key', 
					'Secret Key', 
					array( $this, 'secret_key_html' ), 
					'avada-woo-connection',
					'avada_woo_connection'
				);

				add_settings_field(
					'avada_woo_check_connection', 
					'Check Connection', 
					array( $this, 'check_connection_html' ), 
					'avada-woo-connection',
					'avada_woo_connection'
				);

			}

			/**
			 * Sanitize POST data from custom settings form
			 *
			 * @param array $input Contains custom settings which are passed when saving the form
			 */
			public function sanitize( $input ) {
				$sanitized_input = array();
				if( isset( $input['avada_woo_app_id'] ) )
					$sanitized_input['avada_woo_app_id'] = sanitize_text_field( $input['avada_woo_app_id'] );

				if( isset( $input['avada_woo_secret_key'] ) )
					$sanitized_input['avada_woo_secret_key'] = sanitize_text_field( $input['avada_woo_secret_key'] );

				if( isset( $input['avada_woo_enable'] ) )
					$sanitized_input['avada_woo_enable'] = sanitize_text_field( $input['avada_woo_enable'] );

				if( isset( $input['avada_woo_username'] ) )
					$sanitized_input['avada_woo_username'] = sanitize_text_field( $input['avada_woo_username'] );

				if( isset( $input['avada_woo_password'] ) )
					$sanitized_input['avada_woo_password'] = sanitize_text_field( $input['avada_woo_password'] );

				return $sanitized_input;
			}

			/** 
			 * Custom settings section text
			 */
			public function avada_woo_setting_section() {
				print('Some text');
			}

			/** 
			 * HTML for Enable input
			 */
			public function enable_html() {
				echo '<input type="checkbox" id="avada_woo_enable" name="avada_woo_connection[avada_woo_enable]" value="1" '. checked(1, isset($this->option_connection['avada_woo_enable']) ? 1 : 0, false ) .' />';
			}

			/** 
			 * HTML for Avada Woo App ID input
			 */
			public function app_id_html() {
				printf(
					'<input type="text" style="width:300px" id="avada_woo_app_id" name="avada_woo_connection[avada_woo_app_id]" value="%s" placeholder="App ID" />',
					isset( $this->option_connection['avada_woo_app_id'] ) ? esc_attr( $this->option_connection['avada_woo_app_id']) : ''
				);
			}

			/** 
			 * HTML for Secret Key input
			 */
			public function secret_key_html() {
				printf(
					'<input type="password" style="width:300px" id="avada_woo_secret_key" name="avada_woo_connection[avada_woo_secret_key]" value="%s" placeholder="Secret Key" />',
					isset( $this->option_connection['avada_woo_secret_key'] ) ? esc_attr( $this->option_connection['avada_woo_secret_key']) : ''
				);
			}

			/** 
			 * HTML for Check Connection input
			 */
			public function check_connection_html() {
				echo "<button type='button' class='button button-info btn-test-connection'>Test Connection</button>";
			}

			public function page_avada_woo_auth() {
				register_setting(
					'avada_woo_auth', // Option group
					'avada_woo_auth', // Option name
					array( $this, 'sanitize' ) // Sanitize
				);

				add_settings_section(
					'avada_woo_auth', // ID
					'', // Title
					'', // Callback
					'avada-woo-auth' // Page
				);

				add_settings_field(
					'avada_woo_username', // ID
					'Username', // Title 
					array( $this, 'username_woo_html' ), // Callback
					'avada-woo-auth', // Page         
					'avada_woo_auth'
				);

				add_settings_field(
					'avada_woo_password', 
					'Password', 
					array( $this, 'password_woo_html' ), 
					'avada-woo-auth',
					'avada_woo_auth'
				);

			}

			/** 
			 * HTML for Username Woo input
			 */
			public function username_woo_html() {
				printf(
					'<input type="text" style="width:300px" id="avada_woo_username" name="avada_woo_auth[avada_woo_username]" value="%s" placeholder="Username Woo API" />',
					isset( $this->option_woo_auth['avada_woo_username'] ) ? esc_attr( $this->option_woo_auth['avada_woo_username']) : ''
				);
			}

			/** 
			 * HTML for Password Woo input
			 */
			public function password_woo_html() {
				printf(
					'<input type="text" style="width:300px" id="avada_woo_password" name="avada_woo_auth[avada_woo_password]" value="%s" placeholder="Password Woo API" />',
					isset( $this->option_woo_auth['avada_woo_password'] ) ? esc_attr( $this->option_woo_auth['avada_woo_password']) : ''
				);
			}

			public function check_connection() {
				
				$avada_woo_app_id = isset($_POST['avada_woo_app_id']) ? esc_attr($_POST['avada_woo_app_id']) : null;
				$avada_woo_secret_key = isset($_POST['avada_woo_secret_key']) ? esc_attr($_POST['avada_woo_secret_key']) : null;

				if(!is_null($avada_woo_app_id) && !is_null($avada_woo_secret_key)) {
					$avada_woo_value = ['avada_woo_app_id' => $avada_woo_app_id, 'avada_woo_secret_key' => $avada_woo_secret_key];
					$app_id = $avada_woo_value['avada_woo_app_id'];
					$hmac_sha256 = base64_encode(hash_hmac('sha256', "{}", $avada_woo_value['avada_woo_secret_key'], true));

					$header = [
						"Content-Type: application/json",
						"x-emailmarketing-app-id: {$app_id}",
						"X-EmailMarketing-Connection-Test: true",
						"x-emailmarketing-hmac-sha256: {$hmac_sha256}"
					];

					$result = self::curl('https://app.avada.io/app/api/v1/customers', 'POST', $header, [], '{}');

					if(isset($result['success']) && $result['success'] == 1) {
						wp_send_json_success([
							'status' => true,
							'message' => 'Kết nối với Avada thành công'
						]);
					} else {
						wp_send_json_success([
							'status' => false,
							'message' => 'Kết nối với Avada không thành công ! Vui lòng kiểm tra lại APP ID & SECRECT KEY !'
						]);
					}
				}

			}

			private static function curl($url = null, $method = 'GET', $header = [], $auth = [], $data = '') {
				$curl = curl_init();

				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
				curl_setopt($curl, CURLOPT_HEADER, false);
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_VERBOSE, true);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($curl, CURLOPT_ENCODING, "");
				curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
				curl_setopt($curl, CURLOPT_TIMEOUT, 0);
				curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

				if(count($auth) > 0) {
					curl_setopt($curl, CURLOPT_USERPWD, $auth['username'] . ":" . $auth['password']);
				}
				
				$response = curl_exec($curl);
				$response = json_decode($response, true);
				curl_close($curl);

				return $response;

			}

			public function sync_customer() {
				
				global $wpdb; 

				$limit = isset($_POST['limit']) ? esc_attr($_POST['limit']) : 10;
				$offset = isset($_POST['offset']) ? esc_attr($_POST['offset']) : 0;
				$count_order = isset($_POST['count_order']) ? esc_attr($_POST['count_order']) : 0;
				
				if($offset <= $count_order) {

					$url = "https://app.avada.io/app/api/v1/customers";
					$ch = curl_init($url);

					$response = '';
					
					$orders = self::get_all_orders($limit, $offset);

					foreach($orders as $order_id) {

						$order_data = wc_get_order($order_id['ID']);
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

							$app_id = $this->option_connection['avada_woo_app_id'];
							$hmac_sha256 = base64_encode(hash_hmac('sha256', $data_json, $this->option_connection['avada_woo_secret_key'], true));

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
							$response = json_decode($response);

						}
						
					}

					wp_send_json_success([
						'data'     => $data_json,
						'hmac'     => $hmac_sha256,
						'response' => $response,
						'offset'   => $offset,
						'end'      => false
					]);

					curl_close($ch);

				} else {

					wp_send_json_success([
						'data'    => $data_json,
						'hmac'    => $hmac_sha256,
						'message' => 'Avada Sync Customer Woocommerce Success !',
						'offset'  => $offset,
						'end'     => true
					]);

					curl_close($ch);
				}
				
			}

			public function sync_order() {

				$limit = isset($_POST['limit']) ? esc_attr($_POST['limit']) : 10;
				$offset = isset($_POST['offset']) ? esc_attr($_POST['offset']) : 0;
				$count_order = isset($_POST['count_order']) ? esc_attr($_POST['count_order']) : 0;

				if($offset <= $count_order) {

					$orders = self::get_all_orders($limit, $offset);

					$data_array = [];
					foreach($orders as $order_id) {

						$order = wc_get_order($order_id['ID']);

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
						}

					}

					$data_array = json_encode($data_array);
					$data = '{"data": '.$data_array.'}';

					$hmac_sha256 = base64_encode(hash_hmac('sha256', $data, $this->option_connection['avada_woo_secret_key'], true));
					$app_id = $this->option_connection['avada_woo_app_id'];
					
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
					$response = json_decode($response);

					wp_send_json_success([
						'data' => $data,
						'hmac' => $hmac_sha256,
						'response' => $response,
						'offset' => $offset,
						'end' => false
					]);

					curl_close($ch);

				} else {

					wp_send_json_success([
						'data'    => $data,
						'hmac'    => $hmac_sha256,
						'message' => 'Avada Sync Order Woocommerce Success !',
						'offset'  => $offset,
						'end'     => true
					]);

					curl_close($ch);
				}

				
			}

			public function count_order()
			{
				global $wpdb;

				$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}posts p JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND pm.meta_key = '_billing_email'";

				$sum_order = $wpdb->get_var($sql);

				wp_send_json_success($sum_order);
			}

			private static function get_all_orders($limit = 10, $offset = 0)
			{
				global $wpdb;

				$sql = "SELECT p.ID FROM {$wpdb->prefix}posts p JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND pm.meta_key = '_billing_email' LIMIT $offset, $limit";

				$orders = $wpdb->get_results($sql, ARRAY_A);

				return $orders;
			}

		}

		if(is_admin()) {
			new Avada_Woo();
		}

	}
	
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

	if(!function_exists('write_log')) {

		function write_log($log) {
			if (true === WP_DEBUG) {
				if (is_array($log) || is_object($log)) {
					error_log(print_r($log, true));
				} else {
					error_log($log);
				}
			}
		}

	}
}