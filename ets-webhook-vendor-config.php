<?php
/**
 * Plugin Name: Webhook For WCFM Vendors
 * Plugin URI:  https://www.expresstechsoftwares.com/
 * Description: Webhook for WCFM vendors sends order webhook to individual vendors
 * Version: 1.0.1
 * Author: ExpressTech Softwares Solutions
 * Requires at least: 5.4
 * Text Domain: ets_wcfm_wfv
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ETS_WCFM_WEBHOOK_VENDOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ETS_WCFM_WEBHOOK_VENDOR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

class Ets_Wcfm_Webhook_Vendor_config {

	private static $instance = null;

	/**
	 * Yes, a blank constructor, to implement singleton,
	 * to keep the real essence of WordPress project,
	 * so that someone can easily unhook any of our method
	 *
	 * @return void
	 */
	private function __construct() {
	}

	/**
	 * Singleton pattern
	 * Method explicity for creating the object
	 * of this class
	 *
	 * @return object, an object of this class
	 */
	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the WP hooks
	 *
	 * @return void
	 */
	public static function register() {

		require_once ETS_WCFM_WEBHOOK_VENDOR_PLUGIN_PATH . 'includes/class-webhook-setting.php';
		require_once ETS_WCFM_WEBHOOK_VENDOR_PLUGIN_PATH . 'includes/class-send-webhook.php';
		$plugin = self::get_instance();

		// Load script and style
		add_action( 'wp_enqueue_scripts', array( $plugin, 'ets_enqueue_scripts' ), 100 );

		add_action( 'admin_init', array( $plugin, 'webhook_plugin_has_wcfm_plugin' ) );

	}

	/**
	 * Deactivate plugin when WCFM Marketplace and WCFM not activated
	 */
	public function webhook_plugin_has_wcfm_plugin() {
		$plugin_obj = self::get_instance();

		if ( is_admin() && current_user_can( 'activate_plugins' ) && ( ! is_plugin_active( 'wc-multivendor-marketplace/wc-multivendor-marketplace.php' ) || ! is_plugin_active( 'wc-frontend-manager/wc_frontend_manager.php' ) ) ) {

			add_action( 'admin_notices', array( $plugin_obj, 'webhook_plugin_notice' ) );

			deactivate_plugins( plugin_basename( __FILE__ ) );
		}

		if ( ! function_exists( 'curl_version' ) ) {
			add_action( 'admin_notices', array( $plugin_obj, 'ets_curl_notice' ) );
		}
	}

	/**
	 * CURL installation error Admin note
	 */
	public function ets_curl_notice() {
		printf(
			'<div class="error"><p>%s</p></div>',
			__( 'CURL is not installed, Webhook for WCFM vendor plugin needs CURL to work.', 'ets_wcfm_wfv' )
		);
	}

	/**
	 * Admin note
	 */
	public function webhook_plugin_notice() {
		printf(
			'<div class="error"><p>%s</p></div>',
			__( 'Webhook for WCFM vendor plugin needs WCFM Marketplace and WCFM – Frontend Manager plugins to work.', 'ets_wcfm_wfv' )
		);
	}

	/**
	 * This method enqueues front end and admin scripts
	 */
	public function ets_enqueue_scripts() {
		wp_enqueue_style(
			'ets_webhook_vendor_css',
			plugin_dir_url( __FILE__ ) . 'asset/css/style.css',
			array(),
			'1.0'
		);

		wp_enqueue_script(
			'ets_webhook_vendor_js',
			plugin_dir_url( __FILE__ ) . 'asset/js/script.js',
			array( 'jquery' ),
			'1.1',
			false
		);
		wp_localize_script(
			'ets_webhook_vendor_js',
			'etsWebhookVendor',
			array(
				'ajaxurl'             => admin_url( 'admin-ajax.php' ),
				'test_webhook_nounce' => wp_create_nonce( 'ets_test_webhook_nounce' ),
			)
		);
	}
}

Ets_Wcfm_Webhook_Vendor_config::register();
