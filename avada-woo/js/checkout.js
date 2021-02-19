(function($) {
	$(document).ready(function(){
		$('#billing_email').focusout(function(){
			var avada_billing_email = $(this).val().trim()
			var avada_billing_last_name = $('#billing_last_name').val().trim()
			var avada_billing_first_name = $('#billing_first_name').val().trim()
			var avada_billing_phone = $('#billing_phone').val().trim()
			var avada_billing_address_1 = $('#billing_address_1').val().trim()
			var avada_billing_city = $('#billing_city').val().trim()
			var avada_billing_country = $('#billing_country').val().trim()

			var data_customer = {
				avada_billing_email : avada_billing_email,
				avada_billing_last_name : avada_billing_last_name,
				avada_billing_first_name : avada_billing_first_name,
				avada_billing_phone : avada_billing_phone,
				avada_billing_address_1 : avada_billing_address_1,
				avada_billing_city : avada_billing_city,
				avada_billing_country : avada_billing_country
			}

			$.ajax({
				url : avada_woo.url,
				type : 'post',
				dataType : 'json',
				data : {
					action : "avada_checkout",
					data_customer : data_customer,
					site_url : location.protocol + '//' + location.host + location.pathname
				},
				success(res) {
					console.log(res)
				},
				error(e) {
					console.log(e)
				}
			})
		})
	})
})(jQuery);