jQuery(document).ready(function(){
	jQuery('.btn-test-connection').click(function(){

		var avada_woo_app_id = jQuery('#avada_woo_app_id').val().trim()
		var avada_woo_secret_key = jQuery('#avada_woo_secret_key').val().trim()	

		if(avada_woo_app_id.length > 0 && avada_woo_secret_key.length > 0) {
			loading_snipper()
			jQuery.ajax({
				url : avada_woo.url,
				type : 'post',
				dataType : 'json',
				data : {
					action : "check_connection",
					avada_woo_app_id : avada_woo_app_id,
					avada_woo_secret_key : avada_woo_secret_key
				},
				success(res) {
					if(res.data.status) {
						Swal.fire(
							'Success !',
							res.data.message,
							'success'
						)
					} else {
						Swal.fire({
							icon: 'error',
							title: 'Oops...',
							text: res.data.message,
						})
					}
					loading_snipper(false)
					
				},
				error(jqXHR, textStatus, errorThrown){
					console.log( 'The following error occured: ' + textStatus, errorThrown );
					Swal.fire({
						icon: 'error',
						title: 'Oops...',
						text: 'Đã có lỗi xảy ra ! Vui lòng thử lại !',
					})
				}
			})

		} else {
			Swal.fire({
				icon: 'error',
				title: 'Oops...',
				text: 'App ID & Secret Key Require !',
			})
		}
		
	})

	function loading_snipper(type = true) {
		jQuery('.loading_snipper').css('display', type ? 'block' : 'none')
	}

	jQuery('#sync_customer').click(function(){
		
		Swal.fire({
			title: 'Are you sure?',
			text: "You won't be able to revert this!",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: 'Yes, action it!'
		}).then((result) => {
			if (result.isConfirmed) {

				loading_snipper()

				var elem = document.getElementById("avada_bar");
				elem.style.width = "0%";
				elem.innerHTML = "0%";

				jQuery.ajax({
					url : avada_woo.url,
					type : 'post',
					dataType : 'json',
					data : {
						action : "count_order"
					},
				}).done(function(res) {
					count_order = res.data
					sessionStorage.setItem('avada_woo_count_order', count_order)

					sync_customer()

				}).fail(function(jqXHR, textStatus, errorThrown) {
					console.log( 'The following error occured: ' + textStatus, errorThrown );
				})

			}
		})
		
	})

	function sync_customer(offset = 0) {

		jQuery.ajax({
			url : avada_woo.url,
			type : 'post',
			dataType : 'json',
			data : {
				action : "sync_customer",
				offset : offset,
				limit : set_limit(),
				count_order : sessionStorage.getItem('avada_woo_count_order')
			},
		}).done(function(res){

			console.log(res)

			if(!res.data.end){
				sync_customer(parseInt(res.data.offset) + set_limit())
				process_bar(parseInt(res.data.offset))
			} else {
				Swal.fire(
					'Success !',
					res.data.message,
					'success'
				)

				loading_snipper(false)
				process_bar(parseInt(res.data.offset))
				
			}
			
		}).fail(function(jqXHR, textStatus, errorThrown){
			console.log( 'The following error occured: ' + textStatus, errorThrown );
			Swal.fire({
				icon: 'error',
				title: 'Oops...',
				text: 'Đã có lỗi xảy ra ! Vui lòng thử lại !',
			})
		})
	}

	jQuery('#sync_order').click(function(){

		Swal.fire({
			title: 'Are you sure ?',
			text: "You won't be able to revert this!",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: 'Yes, action it!'
		}).then((result) => {
			if (result.isConfirmed) {

				loading_snipper()

				var elem = document.getElementById("avada_bar");
				elem.style.width = "0%";
				elem.innerHTML = "0%";

				jQuery.ajax({
					url : avada_woo.url,
					type : 'post',
					dataType : 'json',
					data : {
						action : "count_order"
					},
				}).done(function(res) {
					count_order = res.data
					sessionStorage.setItem('avada_woo_count_order', count_order)

					sync_order()

				}).fail(function(jqXHR, textStatus, errorThrown) {
					console.log( 'The following error occured: ' + textStatus, errorThrown );
					Swal.fire({
						icon: 'error',
						title: 'Oops...',
						text: 'Đã có lỗi xảy ra ! Vui lòng thử lại !',
					})
				})

			}
		})
	})

	function sync_order(offset = 0) {

		jQuery.ajax({
			url : avada_woo.url,
			type : 'post',
			dataType : 'json',
			data : {
				action : "sync_order",
				offset : offset,
				limit : set_limit(),
				count_order : sessionStorage.getItem('avada_woo_count_order')
			}
		}).done(function(res){
			console.log(res)

			if(!res.data.end){
				sync_order(parseInt(res.data.offset) + set_limit())
				process_bar(parseInt(res.data.offset))
			} else {
				Swal.fire(
					'Success !',
					res.data.message,
					'success'
				)

				loading_snipper(false)
				process_bar(parseInt(res.data.offset))
				
			}
		}).fail(function(jqXHR, textStatus, errorThrown){
			console.log( 'The following error occured: ' + textStatus, errorThrown );
			Swal.fire({
				icon: 'error',
				title: 'Oops...',
				text: 'Đã có lỗi xảy ra ! Vui lòng thử lại !',
			})
		})

	}

	function process_bar(offset = 0) {

		var count_order = sessionStorage.getItem('avada_woo_count_order')

		if(offset == 0) {
			offset = count_order / set_limit()
		}

		if(offset > count_order) {
			offset = count_order
		}

		var width = Math.round((offset / count_order) * 100)

		if(width > 100) {
			width = 100
		}

		var elem = document.getElementById("avada_bar");

		if(width <= 100) {
			elem.style.width = width + "%";
			elem.innerHTML = width + "%";
		}
		
	}

	function set_limit() {
		return 10;
	}

	// jQuery('#avada_woo_enable').change(function() {

	// 	var avada_woo_app_id = jQuery('#avada_woo_app_id').val().trim()
	// 	var avada_woo_secret_key = jQuery('#avada_woo_secret_key').val().trim()	

	// 	if(this.checked) {
	// 		loading_snipper()
	// 		jQuery.ajax({
	// 			url : 'options.php',
	// 			type : 'post',
	// 			dataType : 'json',
	// 			data : {
	// 				option_page: 'avada_woo_connection',
	// 				action: 'update',
	// 				_wpnonce: jQuery('#_wpnonce').val().trim(),
	// 				_wp_http_referer: '/wp-admin/options-general.php?page=avada-woo',
	// 				avada_woo_connection: {
	// 					'avada_woo_enable' : 1,
	// 					'avada_woo_app_id' : avada_woo_app_id,
	// 					'avada_woo_secret_key' : avada_woo_secret_key
	// 				}
	// 			},
	// 			success(res) {
	// 				loading_snipper(false)
	// 				console.log(res)
	// 			},
	// 			error(jqXHR, textStatus, errorThrown){
	// 				console.log( 'The following error occured: ' + textStatus, errorThrown );
	// 			}
	// 		})
	// 	}
	// });
})