(function($) {
	$(document).ready(function(){
		$('#billing_email, #billing_last_name, #billing_first_name, #billing_phone, #billing_address_1, #billing_city, #billing_country').focusout(function(){
			
			var avada_billing_email = $('#billing_email').val().trim()
			if(validateEmail(avada_billing_email) || avada_billing_email == "") {
				var avada_billing_last_name  = $('#billing_last_name').val().trim()
				var avada_billing_first_name = $('#billing_first_name').val().trim()
				var avada_billing_phone      = $('#billing_phone').val().trim()
				var avada_billing_address_1  = $('#billing_address_1').val().trim()
				var avada_billing_city       = $('#billing_city').val().trim()
				var avada_billing_country    = $('#billing_country').val().trim()

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
						data_customer : data_customer
					},
					success(res) {
						console.log(res)
					},
					error(e) {
						console.log(e)
					}
				})

			}
		})
	})

	function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
})(jQuery);