<?php

if (!defined('ABSPATH')) {
	exit;
}

class Ets_Wcfm_Webhook_Send {
	private static $instance = null;

	/**
	 * Yes, a blank constructor, to implement singleton,
	 * to keep the real essence of WordPress project, 
	 * so that someone can easily unhook any of our method
	 * 
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Singleton pattern
	 * Method explicity for creating the object
	 * of this class
	 * 
	 * @return object, an object of this class
	 */
	public static function get_instance()
	{
		if (self::$instance == null)
		{
		  self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the WP hooks
	 * 
	 * @return void
	 */
	public static function register()
	{
		$webhook = self::get_instance();

		// wc order status change after hook
		add_action('woocommerce_order_status_changed', array($webhook, 'send_webhook_order'), 10, 3);

		// send test webhook.
		add_action('wp_ajax_wcfm_ajax_test_webhook', array($webhook, 'wcfm_ajax_test_webhook'));
	}

	/**
	 * Description : This method is send text webhook 
	 */
	public function wcfm_ajax_test_webhook(){
		$post = $_POST;
		if ( !wp_verify_nonce($post['webhook_nounce'], 'ets_test_webhook_nounce') ) {
			$res = [];
			$res['status'] = 0;
			$res['error'] = __("unauthorized access", "ets_wcfm_wfv");
			echo json_encode($res);
			wp_die();
		}
		
		$json_payload = file_get_contents (ETS_WCFM_WEBHOOK_VENDOR_PLUGIN_PATH."order.json");

		$user_id = isset($post['vendor_id']) ? sanitize_text_field($post['vendor_id']) : '';

		$url = get_user_meta($user_id, "wcfm_webhook_set_url",true);
		
		if($url){

			$args = array(
				'method' => 'POST',
				'body' => $json_payload,
				"headers" => array(
					'Content-Type' => 'application/json;charset=UTF-8'
				),
			);

			$response = wp_remote_post( esc_url_raw($post['url']), $args );
			if ( is_wp_error( $response ) ) {
			    $error_message = $response->get_error_message();
			    $res['status'] = 0;
				$res['error'] = __("Something went wrong: ", "ets_wcfm_wfv").$error_message;	
			} else {
				$res['status'] = 1;
				$res['message'] = __("Test webhook sent successfully!", "ets_wcfm_wfv");	
			}
		} else {
			$res['status'] = 0;
			$res['error'] = __("Please enter and save a webhook URL first.", "ets_wcfm_wfv");
		}

		echo wp_send_json($res);
		wp_die();
	}

	/**
	 * This method is get item commission
	 */
	public function order_item_payout($item, $order_id, $vendor_id)
	{ 
		global $WCFM, $wpdb, $WCFMmp;
		if(!wcfm_is_vendor($vendor_id))
			return false;

		if( !wcfm_vendor_has_capability( $vendor_id, 'view_commission' ) ) return;
		$admin_fee_mode = apply_filters( 'wcfm_is_admin_fee_mode', false );
		$qty = ( isset( $item->get_qty ) ? esc_html( $item['qty'] ) : '1' );
		if ( $WCFMmp->wcfmmp_vendor->is_vendor_deduct_discount( $vendor_id, $order_id ) ) {
			$line_total = $item->get_total();
		} else {
			$line_total = $item->get_subtotal();
		}

		if( $item->get_product_id() ) {
			$product_id = $item->get_product_id();
			$variation_id = $item->get_variation_id();
		} else {
			$product_id = wc_get_order_item_meta( $item->get_id(), '_product_id', true );
			$variation_id = wc_get_order_item_meta( $item->get_id(), '_variation_id', true );
		}

		$order_line_due = $wpdb->get_results( 
			"SELECT item_id, is_refunded, commission_amount AS line_total, 
				shipping AS total_shipping, tax, shipping_tax_amount 
			FROM {$wpdb->prefix}wcfm_marketplace_orders
			WHERE (product_id = ".$product_id." OR variation_id = ".$variation_id . ")
			AND   order_id    = " . $order_id . "
			AND   item_id     = " . $item->get_id() . "
			AND   `vendor_id` = " . $vendor_id
		);

		if( !empty( $order_line_due ) && !$order_line_due[0]->is_refunded ) {
			if ($WCFMmp->wcfmmp_vendor->is_vendor_get_tax($vendor_id)) {
				$order_line_due[0]->line_total += $order_line_due[0]->tax;
			} 
			return number_format($order_line_due[0]->line_total, '2');
		} else {
			return number_format(0, '2');
		}
	}

	/**
	 * Send order information to webhook url
	 */
	public function send_webhook_order ($order_id, $old_status, $new_status)
	{
		global $WCFM, $wpdb;
		$wc_order = wc_get_order($order_id);
		$wc_items = $wc_order->get_items();
		
		// Get WC order detail from api
		WC()->api->includes();
		WC()->api->register_resources( new WC_API_Server( '/' ) );
		$payload = WC()->api->WC_API_Orders->get_order($order_id, null, []);

		$sql = "SELECT vendor_url.`meta_value` as webhook_url, `webhook_status`.`meta_value` as status,`wcfm_orders`.`vendor_id`, `wcfm_orders`.`item_id`
			FROM {$wpdb->prefix}wcfm_marketplace_orders wcfm_orders
			
			JOIN $wpdb->usermeta webhook_active
         	ON webhook_active.user_id = `wcfm_orders`.`vendor_id` 
            AND webhook_active.meta_key = 'wcfm_webhook_is_active'
            AND webhook_active.meta_value = 'yes'

            JOIN $wpdb->usermeta webhook_status
         	ON webhook_status.user_id = `wcfm_orders`.`vendor_id` 
            AND webhook_status.meta_key = 'wcfm_webhook_status'
            
            JOIN $wpdb->usermeta vendor_url
         	ON vendor_url.user_id = `wcfm_orders`.`vendor_id` 
            AND vendor_url.meta_key = 'wcfm_webhook_set_url'
			
			WHERE order_id = {$order_id}";

		$order_items = $wpdb->get_results($sql);

		if($order_items){
			$vendor_line_items = [];
			$vendors_url = [];

			foreach ($order_items as $value) {
				$order_detail = (Array) $value;

				$vendor_id = $order_detail['vendor_id'];
				$webhook_url = $order_detail['webhook_url'];
				$item_id = $order_detail['item_id'];
				 
				if ($order_detail['status'] == "wc-".$new_status) {
					$key = array_search($item_id, array_column($payload['order']['line_items'], 'id'));
					$net_earning =  Ets_Wcfm_Webhook_Send::order_item_payout($wc_items[$item_id], $order_id, $vendor_id);

					// admin fee
					$total = $wc_items[$item_id]->get_total();
					$admin_fee = floatval($total) - filter_var($net_earning, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

					$payload['order']['line_items'][$key]['admin_fee'] = $admin_fee;
					$payload['order']['view_order_url'] =  get_wcfm_url().'orders-details/'.$order_id;
					$vendor_line_items[$vendor_id][] = $payload['order']['line_items'][$key];
					$vendors_url[$vendor_id]['url'] = $webhook_url;

					// Get total net earning.
					if (isset($vendors_url[$vendor_id]['net_earning'])) {
						$vendors_url[$vendor_id]['net_earning'] = filter_var($vendors_url[$vendor_id]['net_earning'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) 
							+
						   	filter_var($net_earning, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
 
					} else {
						$vendors_url[$vendor_id]['net_earning'] = $net_earning;
					}

					// Get total Admin fee.
					if (isset($vendors_url[$vendor_id]['admin_fee'])) {
						$vendors_url[$vendor_id]['admin_fee'] = filter_var($vendors_url[$vendor_id]['admin_fee'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) 
							+
						   	filter_var($admin_fee, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
 
					} else {
						$vendors_url[$vendor_id]['admin_fee'] = $admin_fee;
					}
				}

			} 
			if(!empty($vendor_line_items)){

				// Create a new cURL resource
				foreach ($vendor_line_items as $vendor_id => $line_items) {
					$url = $vendors_url[$vendor_id]['url'];
					$wcfm_new_order = $payload['order'];
					
					$webhook_type = array('webhook_type' => __('live', 'ets_wcfm_wfv'));
					$wcfm_new_order = array_merge($webhook_type, $wcfm_new_order);
					
					$wcfm_new_order['net_earning'] = number_format($vendors_url[$vendor_id]['net_earning'], "2");
					$wcfm_new_order['net_admin_fee'] = number_format($vendors_url[$vendor_id]['admin_fee'], "2");

					$wcfm_new_order['line_items'] = $line_items;

					// Setup request to send json via POST
					$json_payload = json_encode($wcfm_new_order);
					$args = array(
						'body' => $json_payload,
						"headers" => array(
							'Content-Type' => 'application/json;charset=UTF-8'
						),
					);

					$response  = wp_remote_post( esc_url_raw($url), $args );
					if ( is_wp_error( $response ) ) {
					    $error_message = $response->get_error_message();
					    echo "Something went wrong: $error_message";
					}
				}
			}
		}
	}
}

Ets_Wcfm_Webhook_Send::register();