(function ($) {
    'use strict';

    var voltio = {
        initialize: function () {
            this.initVoltio();
            return this;
        },
        volt: false,
        access_token: false,
        payment_id: false,
        mode: false,
        can_place_order: false,
        paymentComponent: false,
        paymentContainer: false,
        themeOptions: {},
        // payButton: $('#place_order'),

        initVoltio: function () {
            voltio.mode = $('#volt-payment-component').attr('data-voltio-mode');
            $.ajax({
                url: voltio_obj.ajaxurl,
                method: 'post',
                data: {
                    'action': 'fetch_token',
                    'nonce': voltio_obj.nonce
                },
                success: function (data) {
                    voltio.getDropinPayments(data);

                },
                error: function (errorThrown) {
                    console.log(errorThrown);
                }
            });
        },
        getDropinPayments: function (data) {
            self.access_token = data;
            $.ajax({
                url: voltio_obj.ajaxurl,
                method: 'post',
                data: {
                    'action': 'get_dropin_payments',
                    'access_token': self.access_token,
                    'payer_name': $('#billing_first_name').val() + ' ' + $('#billing_last_name').val(),
                    'nonce': voltio_obj.nonce
                },
                success: function (data) {
                    console.log(data);
                    voltio.initDropinComponent(data);
                    $('.example-voltio').remove();
                },
                error: function (errorThrown) {
                    console.log(errorThrown);
                }
            });
        },
        initDropinComponent: function (data) {
            var result = jQuery.parseJSON(data);
            self.payment_id = result['payment_id'];
            $('input[name="voltio-hash"]').val(result['order_hash']);
            const mode = $('#volt-payment-component').attr('data-voltio-mode');
            self.volt = new window.Volt({mode});
            var payment_data = {
                id: self.payment_id,
                token: self.access_token
            };
            var payment = JSON.parse(JSON.stringify(payment_data));
            voltio.paymentContainer = volt.payment({
                payment,
                language: "en", // optional - ISO 639-1
                country: "EN", // optional - ISO 3166
                // theme: voltio.themeOptions
            });
            voltio.paymentComponent = voltio.paymentContainer.createPayment({
                displayPayButton: false
            });
            const payButton = $('#place_order');
            payButton.addClass('voltio-required');
            // payButton.disabled = true;
            payButton.onclick = function () {
                if($('[name="voltio-selected-bank"]').val() == 1) {
                    // paymentComponent.checkout() // this will trigger bank redirect
                    // var int = setInterval(function(){
                    //     if($('#order-created').length > 0){
                    //         clearInterval(int);
                    //     }
                    // }, 100)
                }
            }
            voltio.paymentComponent.parent.on('change', function (event) {
                console.log(event);
                // this.payButton.disabled = !event.complete;
                if(!event.complete){
                    payButton.addClass('voltio-required');
                    $('[name="voltio-selected-bank"]').val('0');
                }
                else{
                    $('[name="voltio-selected-bank"]').val('1');
                    payButton.removeClass('voltio-required');
                    payButton.addClass('voltio-pass');
                }
            });
            voltio.paymentComponent.mount("#volt-payment-component");
            const termsComponent = voltio.paymentContainer.createTerms();
            termsComponent.mount("#volt-payment-terms"); // the element above pay button
        },
        payButtonListener: function(){
            voltio.payButton.addClass('voltio-required');
        }
    }

    $('body').on('click', '.volt-modal', function(e){
        e.preventDefault();
        if (e.target === this || $(e.target).hasClass('volt-close')) {
            $(this).hide();
        }
    })


    $(document.body).on("click", "#place_order.voltio-pass" ,function(evt) {
        if(!$(this).hasClass('voltio-required') && $('.payment_method_voltio input').prop('checked') === true) {
            evt.preventDefault();
            $.ajax({
                type: 'POST',
                url: wc_checkout_params.ajax_url,
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                enctype: 'multipart/form-data',
                data: {
                    'action': 'ajax_order',
                    'order_hash': $('input[name="voltio-hash"]').val(),
                    'fields': $('form.checkout').serializeArray(),
        },
            success: function (result) {
                console.log(result); // For testing (to be removed)
                $('.woocommerce-NoticeGroup').html('');
                try{
                    var response = jQuery.parseJSON(result);
                    if(response['error']) {
                        if($('form.woocommerce-checkout .woocommerce-notices-wrapper .woocommerce-NoticeGroup').length <= 0){
                            $('form.woocommerce-checkout').append('<div class="woocommerce-notices-wrapper"><div class="woocommerce-NoticeGroup"></div></div>');
                        }
                        $('form.woocommerce-checkout .woocommerce-NoticeGroup').html('<ul class="woocommerce-error"></ul>');
                        $.each(response['error'], function (index, task) {
                            $('.woocommerce-error').append('<li>'+task+'</li>');
                        });
                        $('html, body').animate({ scrollTop: $('.woocommerce-error').offset().top - 50}, 1000);
                    }
                    else if(response['success']){
                        voltio.paymentComponent.checkout();
                    }
                }
                catch(e){

                }


                // $(document.body).trigger('updated_checkout');
                // $('input[name="shipping_method[0]"]').trigger('change');
                if(result > 0){
                    // voltio.paymentComponent.checkout();
                }
            },
            error: function (error) {
                console.log(error); // For testing (to be removed)
            }
        });
        }
    });


    $(document.body).on('updated_checkout', function () {
        if ($('input[name="payment_method"][value="voltio"]').is(':checked')) {
            $('input[name="payment_method"][value="voltio"]').removeAttr('voltio-initialized');
            $('input[name="payment_method"][value="voltio"]').trigger('change');
        }
    });
    $('form.checkout').on('change', 'input[name="payment_method"]', function () {
        $('#place_order').removeAttr('disabled');
        if ($(this).val() == 'voltio' && !$(this).attr('voltio-initialized') && $('#volt-payment-component').html() == '') {
            $(this).attr('voltio-initialized', 1);
            $('#place_order').addClass('voltio-required');
            voltio.initialize();
            // inject_voltio_desc();

        }
        if ($(this).val() == 'voltio'){
            // $('.example-voltio').remove();
        }
    });
    $(document).ready(function (){
        setTimeout(function(){
            inject_voltio_modal();
        }, 4000)
    });

    $('body').on('click', '.show-volt-modal', function(e){
        e.preventDefault();
        $('.volt-modal').css('display', 'flex');
    })
    $('body').on('click', '.volt-modal .close', function(e){
        e.preventDefault();
        $('.volt-modal').hide();
    })

    function inject_voltio_modal(){
        if($('[for="payment_method_voltio"]').length > 0){
            $('<a href="#" class="show-volt-modal"><img src="'+$('.volt-modal').attr('data-volt-icon')+'" /></a>').insertBefore($('[for="payment_method_voltio"]').find('img:not(.volt-modal-icon)'));
            // .prepend('<a href="#" class="show-volt-modal"><img src="'+$('[.volt-modal]').attr('data-volt-volt')+'" /></a>');
        }
    }

    $('body').on('click', '#place_order', function(event) {
        // event.preventDefault();
        // $('form.checkout').trigger('checkout_validation_before_processing');
    });
    $( document.body ).on( 'checkout_error', function(){

    } );




})(jQuery)


jQuery(function ($) {
    var access_token;


    function set_access_token(data) {
        access_token = data;
    }

});