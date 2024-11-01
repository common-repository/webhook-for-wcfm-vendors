jQuery(document).ready(function(){
	jQuery(".active_webhook-active_webhook_wrapper").on('change', function(){
		let value = jQuery(this).find('#active_webhook-active_webhook').val();
		if(value == "yes"){
			jQuery('.wcfm-webhook-active').val('no');
		} else {
			jQuery('.wcfm-webhook-active').val('yes');
		}
	});
	jQuery('.send-test-webhook').on('click', function(e){
		e.preventDefault();
		jQuery('.send-test-webhook-loader').removeClass('d-none');
		let webhook_url = jQuery('#webhook_url').val();
		let vendor_id = jQuery(this).data('vendor_id');
		let data = {
			action       	  : 'wcfm_ajax_test_webhook',
			url     	  	  : webhook_url,
			vendor_id 		  : vendor_id,
			webhook_nounce 	  : etsWebhookVendor.test_webhook_nounce
		}

		jQuery.ajax({
			type : 'POST',
			dataType: 'JSON',
			url	 : etsWebhookVendor.ajaxurl,
			data : data,
			success:function(response) {
				jQuery('.send-test-webhook-loader').addClass('d-none');
				console.log(response, response.status, response.message);	
				let html = '';
				if(response.status){
					html = "<div class='send-test-webhook-msg-success send-test-webhook-msg w-100 p-2 mt-2'><b>"+response.message+"</b> <span class='text-right text-white'></span></div>";
				} else {
					html = "<div class='send-test-webhook-msg-error send-test-webhook-msg w-100 p-2 mt-2'><b>"+response.error+"</b> <span class='text-right text-white'></span></div>";
				}
				jQuery('#wcfm_settings_form_vendor_invoice_expander').append(html);
			}
		});
	});
});


jQuery(document).on('submit', '#wcfm_vendor_manage_webhook_form', function(e){
	e.preventDefault();
	jQuery('#wcfm_vendor_manage_coverage_areas_form').css('cursor', 'progress');
	jQuery('#wcfm_vendor_manage_webhook_form').css('cursor', 'progress');
	jQuery(this).find('.wcfm-message').css('display', 'none');
	jQuery('#wcfm_webhook_save_button').prop('disabled', true);
	jQuery.ajax({
		type : 'POST',
		dataType: 'JSON',
		url	 : etsWebhookVendor.ajaxurl,
		data : jQuery(this).serialize(),
		success:function(response) {
			jQuery('#wcfm_webhook_save_button').prop('disabled', false);
			jQuery('#wcfm_vendor_manage_webhook_form').css('cursor', 'pointer');
			jQuery('.wcfm-message').css('display', 'block');
			jQuery('#wcfm_vendor_manage_webhook_form').find('.wcfm-message').removeClass('wcfm-success');
			if(response.status){
				let html = '<span class="wcicon-status-completed"></span>'+response.msg;
				jQuery('#wcfm_vendor_manage_webhook_form').find('.wcfm-message').addClass('wcfm-success').html(html);
				 
			} else {
				let html = '<span class="wcicon-status-cancelled"></span>'+response.msg;
				jQuery('#wcfm_vendor_manage_webhook_form').find('.wcfm-message').addClass('wcfm-error');
				jQuery('#wcfm_vendor_manage_webhook_form').find('.wcfm-message').html(html);
			}
		}
	});
});