<?php
/*
Plugin Name: Avada Woo
Plugin URI: https://avada.io
Description: Kết nối Avada vs Woocommerce
Version: 1.0
Author: avada.io
Author URI: https://avada.io
Text Domain: avada-woo
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
if(!function_exists('add_action')) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
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
			public $option_connection;
			public $option_woo_auth;
			/**
			 * Constructor
			 */
			public function __construct() {

				$this->option_connection = get_option('avada_woo_connection');
				$this->option_woo_auth = get_option('avada_woo_auth');

				add_action('admin_menu', [$this, 'add_settings_page']);
				add_action('admin_init', [$this, 'page_avada_woo_connection']);
				add_action('admin_enqueue_scripts', function(){
					wp_register_script('avada-woo-js', AVADA_WOO_PLUGIN_URL . 'js/function.js');

					wp_localize_script('avada-woo-js', 'avada_woo', [
						'url' => admin_url('admin-ajax.php')
					]);
					wp_enqueue_script('avada-woo-js');

					wp_register_style('avada-woo-css', AVADA_WOO_PLUGIN_URL . 'css/style.css');
					wp_enqueue_style('avada-woo-css');
				});

				add_action('wp_enqueue_scripts', function(){
					wp_register_script('avada-woo-js', AVADA_WOO_PLUGIN_URL . 'js/checkout.js', array('jquery'));
					wp_localize_script('avada-woo-js', 'avada_woo', [
						'url' => admin_url('admin-ajax.php')
					]);
					wp_enqueue_script('avada-woo-js');
				});

				register_activation_hook(__FILE__, [$this, 'avada_create_table']);

				add_action('wp_ajax_check_connection', [$this, 'check_connection']);
				add_action('wp_ajax_sync_customer', [$this, 'sync_customer']);
				add_action('wp_ajax_sync_order', [$this, 'sync_order']);
				add_action('wp_ajax_count_order', [$this, 'count_order']);
				add_action('wp_ajax_avada_checkout', [$this, 'avada_checkout']);
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

			public function avada_create_table()
			{
				global $wpdb;
   				$table_name = $wpdb->prefix . "avada_cart_abandonment";
   				$charset_collate = $wpdb->get_charset_collate();

				$sql = "CREATE TABLE $table_name (
					id int(50) NOT NULL AUTO_INCREMENT,
					email varchar(50) NOT NULL,
					cart_content text NOT NULL,
					customer_info text DEFAULT NULL,
					session_id varchar(100) DEFAULT '' NOT NULL,
					link text DEFAULT NULL,
					created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
					PRIMARY KEY (id)
				) $charset_collate;";

				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
				dbDelta( $sql );
			}

			public function check_connection() {
				
				$avada_woo_app_id = isset($_POST['avada_woo_app_id']) ? esc_attr($_POST['avada_woo_app_id']) : null;
				$avada_woo_secret_key = isset($_POST['avada_woo_secret_key']) ? esc_attr($_POST['avada_woo_secret_key']) : null;

				if(!is_null($avada_woo_app_id) && !is_null($avada_woo_secret_key)) {
					$avada_woo_value = ['avada_woo_app_id' => $avada_woo_app_id, 'avada_woo_secret_key' => $avada_woo_secret_key];
					
					$data_store = '{
						"data": {
							"name": "'.get_option('woocommerce_email_from_name').'",
							"phone" : "",
							"countryName": "'.get_option('woocommerce_default_country').'",
							"countryCode": "'.get_option('woocommerce_default_country').'",
							"city": "'.get_option('woocommerce_store_city').'",
							"timezone": "'.get_option('timezone_string').'",
							"zip": "'.get_option('woocommerce_store_postcode').'",
							"currency": "'.get_option('woocommerce_currency').'",
							"address1": "'.get_option('woocommerce_store_address').'",
							"address2": "'.get_option('woocommerce_store_address_2').'",
							"email": "'.get_option('woocommerce_stock_email_recipient').'",
							"source": "woocommerce"
						}
					}';

					$app_id = $avada_woo_value['avada_woo_app_id'];
					$hmac_sha256 = base64_encode(hash_hmac('sha256', $data_store, $avada_woo_secret_key, true));

					$header = [
						"Content-Type: application/json",
						"x-emailmarketing-app-id: {$app_id}",
						"x-emailmarketing-hmac-sha256: {$hmac_sha256}",
						"X-EmailMarketing-Wordpress: true"
					];

					$result = self::curl('https://app.avada.io/app/api/v1/connects', 'POST', $header, $data_store);

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

			private static function curl($url = null, $method = 'GET', $header = [], $data = '') {
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
							foreach($order->get_items() as $item) {
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

			public function avada_checkout()
			{
				$data_customer = isset($_POST['data_customer']) ? $_POST['data_customer'] : null;
				$site_url = isset($_POST['site_url']) ? $_POST['site_url'] : null;

				if(!is_null($data_customer)) {
					
					$customer_info = [
						'avada_billing_email'      => $data_customer['avada_billing_email'],
						'avada_billing_last_name'  => $data_customer['avada_billing_last_name'],
						'avada_billing_first_name' => $data_customer['avada_billing_first_name'],
						'avada_billing_phone'      => $data_customer['avada_billing_phone'],
						'avada_billing_address_1'  => $data_customer['avada_billing_address_1'],
						'avada_billing_city'       => $data_customer['avada_billing_city'],
						'avada_billing_country'    => $data_customer['avada_billing_country']
					];

					$link = $this->avada_insert_table($site_url, $customer_info);

					$order_data = [
						"id"                     => 0,
						"abandoned_checkout_url" => isset($link) ? $link : null,
						"email"                  => $data_customer['avada_billing_email'],
						"created_at"             => date('Y-m-d H:i:s'),
						"updated_at"             => date('Y-m-d H:i:s'),
						"completed_at"           => date('Y-m-d H:i:s'),
						"phone"                  => $data_customer['avada_billing_phone'],
						"customer_locale"        => "",
						"subtotal_price"         => WC()->cart->subtotal,
						"total_tax"              => WC()->cart->get_total_tax(),
						"total_price"            => WC()->cart->subtotal,
						"currency"               => get_woocommerce_currency(),
						"customer" => [
							"id"         => 0,
							"email"      => $data_customer['avada_billing_email'],
							"name"       => $data_customer['avada_billing_first_name'],
							"first_name" => $data_customer['avada_billing_first_name'],
							"last_name"  => $data_customer['avada_billing_last_name']
						],
						"shipping_address" => [
							"name"          => $data_customer['avada_billing_first_name'],
							"last_name"     => $data_customer['avada_billing_last_name'],
							"phone"         => $data_customer['avada_billing_phone'],
							"company"       => "",
							"country_code"  => $data_customer['avada_billing_country'],
							"zip"           => "",
							"address1"      => $data_customer['avada_billing_address_1'],
							"address2"      => "",
							"city"          => $data_customer['avada_billing_city'],
							"province_code" => "",
							"province"      => ""
						]
					];

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

					$order_data['line_items'] = $line_items;
					$order_data = json_encode($order_data);

					$data = '{"data": '.$order_data.'}';

					$hmac_sha256 = base64_encode(hash_hmac('sha256', $data, $this->option_connection['avada_woo_secret_key'], true));
					$app_id = $this->option_connection['avada_woo_app_id'];

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
					wp_send_json_success($response);
				}
			}

			public function avada_insert_table($site_url = '', $customer_info = null)
			{
				global $wpdb;

				$table_name = $wpdb->prefix."avada_cart_abandonment";

			    $cart = serialize(WC()->cart->get_cart());
			    $time = time();
			    $created_at = get_date_from_gmt(date('Y-m-d H:i:s', $time));
			    $session_id = md5($cart . $time);
			    $email = $customer_info['avada_billing_email'];
			    $customer_info = serialize($customer_info);

			    $link = $site_url .'?avada_token_cart='.base64_encode($session_id);

			    $insert_query = "INSERT INTO ".$table_name."(`email`, `cart_content`, `customer_info`, `session_id`, `created_at`, `link`) 
		    					VALUES ('".$email."', '".$cart."', '".$customer_info."', '".$session_id."', '".$created_at."', '".$link."')"; 
				$insertResult = $wpdb->query($insert_query); 

				if($insertResult) return $link;
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

		new Avada_Woo();
		
	}

	require_once('webhook.php');
	require_once('helper.php');
}