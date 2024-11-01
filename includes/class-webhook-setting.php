<?php

if (!defined('ABSPATH')) {
	exit;
}


class Ets_Wcfm_Webhook {
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

		 
		// update webhook setting
		add_action( 'wcfm_vendor_settings_update', array($webhook, "ets_vendor_settings_update"), 500, 2 );

		// webhook setting form
		add_action( 'end_wcfm_vendor_settings', array($webhook, "webhook_setting_form"), 600);

		add_action( 'begin_wcfm_vendors_new_form', array($webhook, 'wcfmmp_vendor_manage_webhook_setting'));
		
		add_action( 'end_wcfm_vendors_manage_form', array($webhook, 'wcfmmp_vendor_manage_webhook_setting'));

		add_action('wp_ajax_save_admin_vendor_webhook_setting', array($webhook, 'save_admin_vendor_webhook_setting'));
	}


	public function save_admin_vendor_webhook_setting(){

		$post = $_POST;
		
		if ( !wp_verify_nonce($post['_nounce'], 'ets_webhook_nounce') ) {
			$res = [];
			$res['status'] = 0;
			$res['msg'] = __("unauthorized access", "ets_wcfm_wfv");
			echo json_encode($res);
			wp_die();
		}

		$user_id = isset($post['vendor_id']) ? sanitize_text_field($post['vendor_id']) : '';
		
		if($user_id) {
			$url = isset($post['webhook_url']) ? esc_url_raw($post['webhook_url']) : '';
			$active = isset($post['active_webhook']) ? sanitize_text_field($post['active_webhook']) : "no";
			$webhook_status = isset($post['webhook_status']) ? sanitize_text_field($post['webhook_status']) : "";

			update_user_meta($user_id, 'wcfm_webhook_set_url', $url);
			update_user_meta($user_id, 'wcfm_webhook_is_active', $active);
			update_user_meta($user_id, 'wcfm_webhook_status', $webhook_status);

			$res['status'] = 1;
			$res['msg'] = __("Setting update successfully.", "ets_wcfm_wfv");
		} else {
			$res['status'] = 0;
			$res['msg'] = __("Invalid user.", "ets_wcfm_wfv");
		}

		echo json_encode($res);
		wp_die();
	}


	/**
	 * Vendor Coverage Areas Setting.
	 */
	public function wcfmmp_vendor_manage_webhook_setting( $vendor_id ) {
		global $WCFM, $WCFMmp;
		if( !$vendor_id ) return;

		$webhook_url = get_user_meta($vendor_id, 'wcfm_webhook_set_url', true);
		$webhook_active = get_user_meta($vendor_id, 'wcfm_webhook_is_active', true);
		$webhook_url = $webhook_url ? esc_url($webhook_url) : '';
		$webhook_status = get_user_meta($vendor_id, 'wcfm_webhook_status', true);
		 
		$all_status = wc_get_order_statuses();

		printf('<div class="page_collapsible vendor_manage_store_setting" id="wcfm_vendor_manage_form_store_settings_head"><label class="wcfmfa fa-paper-plane"></label>%s<span></span></div>',
			__("Webhook", "ets_wcfm_wfv")
		);

		?>
		<!-- collapsible -->
		<div class="wcfm-container">
			<div id="wcfm_vendor_manage_form_webhook_expander" class="wcfm-content"> 
			<form id="wcfm_vendor_manage_webhook_form" class="wcfm">
				<div id="wcfm_settings_form_vendor_invoice_expander" class="wcfm-content">
					
					<?php
			    	$WCFM->wcfm_fields->wcfm_generate_form_field(
			    		array(
			    			'vendor_id' => array(
			    				'type' => 'hidden',
			    				'name' => 'vendor_id',
			    				'value' => $vendor_id,
			    			),
			    			'action' => array(
			    				'type' => 'hidden',
			    				'name' => 'action',
			    				'value' => 'save_admin_vendor_webhook_setting',
			    			),
			    			'hidden_webhook_active' => array(
			    				'type' => 'hidden',
			    				'name' => 'hidden_webhook_active',
			    				'value' => $webhook_active,
			    				'class' => 'wcfm-webhook-active',
			    			),
			    		)
			    	); 

			    	printf('<p class="active_webhook-active_webhook wcfm_title checkbox_title wcfm_half_ele_title"><strong>%s</strong></p>
					', __('Active', 'ets_wcfm_wfv'));

					printf('<label class="screen-reader-text" for="active_webhook-active_webhook">%s</label>',__('Active', 'ets_wcfm_wfv') );
			    	
					printf('<div id="active_webhook-active_webhook"  class="wcfm-text wcfm_ele wcfm_half_ele ets-webhook_active-switch">
				    	<div class="ets-active-switch">
							<div class="toggle-group">
							    <input type="checkbox" name="active_webhook"  id="on-off-switch"  tabindex="1" class="ets-active-switch" value="yes" %s >
							    <label for="on-off-switch">
							    </label>
							    <div class="ets-activ-onoff-switch " aria-hidden="true">
							        <div class="ets-activ-onoff-switch-label">
							            <div class="ets-activ-onoff-switch-inner"></div>
							            <div class="ets-activ-onoff-switch-switch"></div>
							        </div>
							    </div>
							</div>
						</div>
					</div>', $webhook_active == "yes" ? "checked" : '');

			    	$WCFM->wcfm_fields->wcfm_generate_form_field(
			    		array(
			    			"webhook_url" 		=> array(
								'label' 		=> __('URL', 'ets_wcfm_wfv'),
								'label_class' 	=> 'wcfm_title checkbox_title',
								'type' 			=> 'text', 
								'class' 		=> 'wcfm-text wcfm_ele wcfm_half_ele',
								'value' 		=> $webhook_url ? $webhook_url : '',
								'label_class' 	=> 'wcfm_title wcfm_ele wcfm_half_ele_title',
							),
							'webhook_status' 	=>	array( 
								'label'			=> 	__('Trigger on Status', 'ets_wcfm_wfv') ,
								'type'			=> 	'select',
								'options' 		=> 	$all_status,
								'class' 		=> 	'wcfm-select wcfm_ele redq_rental',
								'label_class' 	=> 	'wcfm_title redq_rental',
								'value' 		=> 	$webhook_status,
								'hints' 		=> 	__('When the order moves to this status webhook will be triggered.', 'ets_wcfm_wfv')
							),
							"webhook_send_test" => array(
								'name'			=> '',
								'label' 		=> __('Send Test Webhook', 'ets_wcfm_wfv'),
								'label_class' 	=> 'wcfm_title',
								'type' 			=> 'html',
								'hints'			=> __("A test webhook with <code>webhook_type:'test'</code> will be sent to the above URL (do remember to save it before sending).", 'ets_wcfm_wfv'),
								'value'			=> '<div id="send-test-webhook"><a href="" class="send-test-webhook btn btn-info" data-vendor_id="'.$vendor_id.'">'.__('Send', 'ets_wcfm_wfv').'</a> &nbsp;<img class="d-none send-test-webhook-loader" width="23" src="'.ETS_WCFM_WEBHOOK_VENDOR_PLUGIN_URL.'asset/images/loader.gif'.'" />
									</div>', 
								'class' 		=> 'wcfm-text wcfm_ele wcfm_half_ele',
							),
			      		)
			      	);
			      ?>
		      	</div>
		      	<div class="wcfm-message m-2" tabindex="-1" style="display: none;"></div>
				 
				<div class="wcfm_messages_submit">
					<input type="submit" name="save-data" value="<?php _e( 'Update', 'wc-frontend-manager' ); ?>" id="wcfm_webhook_save_button" class="wcfm_submit_button" />
				</div>
				<?php wp_nonce_field('ets_webhook_nounce', '_nounce'); ?>
				<div class="wcfm-clearfix"></div>
				 
			</form>
					
			</div>
		</div>
		<div class="wcfm_clearfix"></div>
		<br />
		<!-- end collapsible -->
		<?php
	}

	/**
	 * Update webhook setting information
	 */
	public function ets_vendor_settings_update($vendor_id, $wcfm_settings_form){
		global $WCFM, $WCFMmp;

	  	$user_id = get_current_user_id();
		
		$url = isset($wcfm_settings_form['webhook_url']) ? esc_url_raw($wcfm_settings_form['webhook_url']) : '';
 
		$active = isset($wcfm_settings_form['hidden_webhook_active']) ? sanitize_text_field($wcfm_settings_form['hidden_webhook_active']) : "yes";

		$webhook_status = isset($wcfm_settings_form['webhook_status']) ? sanitize_text_field($wcfm_settings_form['webhook_status']) : "";
		update_user_meta($user_id, 'wcfm_webhook_status', $webhook_status);

		update_user_meta($user_id, 'wcfm_webhook_set_url', $url);
		update_user_meta($user_id, 'wcfm_webhook_is_active', $active);
	}

	/**
	 * Create webhook setting form in settng tab
	 */
	public function webhook_setting_form( $vendor_id ) {
		global $WCFM, $WCFMmp;
		$webhook_url = get_user_meta( $vendor_id, 'wcfm_webhook_set_url', true );
		$webhook_active = get_user_meta( $vendor_id, 'wcfm_webhook_is_active', true);
		$webhook_status = get_user_meta( $vendor_id, 'wcfm_webhook_status', true);
		$webhook_url = $webhook_url ? esc_url($webhook_url) : '';
		$webhook_active = $webhook_active ? $webhook_active : 'no';

		 
		// get all status
	  	$all_status = wc_get_order_statuses();

  		printf('<div class="page_collapsible" id="wcfm_settings_form_min_order_amount_head">
	    	<label class="wcfmfa fa-paper-plane"></label>%s<span></span></div>',
			__("Webhook", "ets_wcfm_wfv")
		);
		?>

	  	<!-- collapsible -->
	    <div class="wcfm-container">
			<div id="wcfm_settings_form_vendor_invoice_expander" class="wcfm-content">
			
			<?php
			printf('<input type="hidden" class="wcfm-webhook-active" name="hidden_webhook_active" value="%s" />',
				$webhook_active
			);

	    	$WCFM->wcfm_fields->wcfm_generate_form_field(
	    		array(
	    			"active_webhook" 	=> array(
	    				'label' 		=> __('Active', 'ets_wcfm_wfv'),
	    				'name' 			=> 'active_webhook',
	    				'type'			=> 'checkboxoffon',
	    				'class' 		=> 'wcfm-checkbox wcfm_ele wcfm_half_ele',
	    				'label_class' 	=> 'wcfm_title checkbox_title wcfm_half_ele_title',
	    				'dfvalue' 		=> 'no',
	    				'value' 		=> $webhook_active,
	    			),

	    			"webhook_url" 		=> array(
						'label' 		=> __('URL', 'ets_wcfm_wfv'),
						'label_class' 	=> 'wcfm_title checkbox_title',
						'type' 			=> 'text', 
						'class' 		=> 'wcfm-text wcfm_ele wcfm_half_ele', 
						'value' 		=> $webhook_url ? $webhook_url : '',
						'label_class' 	=> 'wcfm_title wcfm_ele wcfm_half_ele_title',
					),

					'webhook_status' 	=>	array( 
						'label'			=> 	__('Trigger on Status', 'ets_wcfm_wfv') ,
						'type'			=> 	'select',
						'options' 		=> 	$all_status,
						'class' 		=> 	'wcfm-select wcfm_ele redq_rental',
						'label_class' 	=> 	'wcfm_title redq_rental',
						'value' 		=> 	$webhook_status,
						'hints' 		=> 	__('When the order moves to this status webhook will be triggered.', 'ets_wcfm_wfv')
					),

					"webhook_send_test" =>	array(
						'name'			=> 	'',
						'label' 		=> 	__('Send Test Webhook', 'ets_wcfm_wfv'),
						'label_class' 	=> 	'wcfm_title',
						'type' 			=> 	'html',
						'hints'			=> 	__("A test webhook with <code>webhook_type:'test'</code> will be sent to the above URL (do remember to save it before sending).", 'ets_wcfm_wfv'),
						'value'			=> 	'<div id="send-test-webhook"><a href="" class="send-test-webhook btn btn-info"  data-vendor_id="'.$vendor_id.'">Send Test Webhook</a> &nbsp;<img class="d-none send-test-webhook-loader" width="23" src="'.ETS_WCFM_WEBHOOK_VENDOR_PLUGIN_URL.'asset/images/loader.gif'.'" />
							</div>', 
						'class' 		=> 	'wcfm-text wcfm_ele wcfm_half_ele',
					),
	      		)
	      	);
	      ?>
	      </div>
	  </div>
	  <div class="wcfm_clearfix"></div>
	  <!-- end collapsible -->
	<?php
	} 
}

Ets_Wcfm_Webhook::register();