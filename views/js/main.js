(function ($) {
    'use strict';

    var voltio = (function(){
        var access_token = false,
            volt = false,
            payment_id = false,
            mode = false,
            can_place_order = false,
            paymentc = false,
            paymentComponent = false,
            paymentContainer = false,
            themeOptions = {};

        function initialize(){
            initVoltio();
            return this;
        }

        function initVoltio(){
            voltBlockUI();
            mode = $('#volt-payment-component').attr('data-voltio-mode');
            $.ajax({
                url: voltio_obj.ajaxurl,
                method: 'post',
                data: {
                    'action': 'fetch_token',
                    'nonce': voltio_obj.nonce
                },
                success: function (data) {
                    getDropinPayments(data);

                },
                error: function (errorThrown) {
                    console.log(errorThrown);
                }
            });
        }

        function getDropinPayments(data){
            access_token = data;
            $.ajax({
                url: voltio_obj.ajaxurl,
                method: 'post',
                data: {
                    'action': 'get_dropin_payments',
                    'access_token': access_token,
                    'first_name': $('#billing_first_name').val(),
                    'last_name': $('#billing_last_name').val(),
                    'nonce': voltio_obj.nonce
                },
                success: function (data) {
                    initDropinComponent(data);
                    $('.example-voltio').remove();
                },
                error: function (errorThrown) {
                    console.log(errorThrown);
                }
            });
        }

        function initDropinComponent(data){
            var result = jQuery.parseJSON(data);
            payment_id = result['payment_id'];
            $('input[name="voltio-hash"]').val(result['order_hash']);
            const mode = $('#volt-payment-component').attr('data-voltio-mode');
            volt = new window.Volt({mode});
            var payment_data = {
                id: payment_id,
                token: access_token
            };
            var payment = JSON.parse(JSON.stringify(payment_data));
            paymentContainer = volt.payment({
                payment,
                language: "en", // optional - ISO 639-1
                country: "EN", // optional - ISO 3166
            });
            paymentComponent = paymentContainer.createPayment({
                displayPayButton: false
            });
            const payButton = $('#place_order');
            payButton.addClass('voltio-required');

            // payButton.disabled = true;
            payButton.onclick = function () {

            }
            paymentComponent.parent.on('change', function (event) {
                console.log(event);
                // this.payButton.disabled = !event.complete;
                if (!event.complete) {
                    payButton.addClass('voltio-required');
                    $('[name="voltio-selected-bank"]').val('0');
                } else {
                    $('[name="voltio-selected-bank"]').val('1');
                    payButton.removeClass('voltio-required');
                    payButton.addClass('voltio-pass');
                }
            });
            paymentComponent.mount("#volt-payment-component");
            const termsComponent = paymentContainer.createTerms();
            termsComponent.mount("#volt-payment-terms"); // the element above pay button
            $('.volt-blockui').remove();
        }
        function voltBlockUI(){
            var int = setInterval(function () {
                if ($('#payment').find('.blockUI.blockOverlay').length <= 0) {
                    $('#payment').append('<div class="blockUI blockOverlay volt-blockui" style="z-index: 1000; border: none; margin: 0px; padding: 0px; width: 100%; height: 100%; top: 0px; left: 0px; background: rgb(255, 255, 255); opacity: 0.6; cursor: default; position: absolute;"></div>');
                    $('#payment').css('position', 'relative');
                    clearInterval(int);
                }
            }, 1);
        }

        function getPaymentComponent(){
            return paymentComponent;
        }

        function payButtonListener(){
            payButton.addClass('voltio-required');
        }
        return {
            initialize: initialize,
            voltBlockUI: voltBlockUI,
            getPaymentComponent: getPaymentComponent,
            // paymentComponent: paymentComponent
            // Dodajemy pozostałe publiczne funkcje...
        };
    })()

    // var voltio = {
    //
    //
    //     payButtonListener: function () {
    //
    //     }
    // }

    $('body').on('click', '.volt-modal', function (e) {
        e.preventDefault();
        if (e.target === this || $(e.target).hasClass('volt-close')) {
            $(this).hide();
        }
    });
    $(document).keyup(function (event) {
        if (event.which === 27) {
            if ($('.volt-modal').length > 0) {
                if ($('.volt-modal').is(':visible')) {
                    $('.volt-modal').hide();
                }
            }
        }
    });


    $(document.body).on("click", "#place_order.voltio-pass", function (evt) {
        if (!$(this).hasClass('voltio-required') && $('.payment_method_voltio input').prop('checked') === true) {
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
					'nonce': wc_checkout_params.update_order_review_nonce,
                },
                success: function (result) {
                    $('.woocommerce-NoticeGroup').html('');
                    try {
                        var response = jQuery.parseJSON(result);
                        if (response['error']) {
                            if ($('form.woocommerce-checkout .woocommerce-notices-wrapper .woocommerce-NoticeGroup').length <= 0) {
                                $('form.woocommerce-checkout').append('<div class="woocommerce-notices-wrapper"><div class="woocommerce-NoticeGroup"></div></div>');
                            }
                            $('form.woocommerce-checkout .woocommerce-NoticeGroup').html('<ul class="woocommerce-error"></ul>');
                            $.each(response['error'], function (index, task) {
                                $('.woocommerce-error').append('<li>' + task + '</li>');
                            });
                            $('html, body').animate({scrollTop: $('.woocommerce-error').offset().top - 50}, 1000);
                        } else if (response['success']) {
                            console.log(voltio.getPaymentComponent());
                            voltio.getPaymentComponent().checkout();
                        }
                    } catch (e) {

                    }


                    // $(document.body).trigger('updated_checkout');
                    // $('input[name="shipping_method[0]"]').trigger('change');
                    if (result > 0) {
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
    });
    $(document).ready(function () {
        setTimeout(function () {
            inject_voltio_modal();
        }, 2000)
    });

    $('body').on('click', '.show-volt-modal', function (e) {
        e.preventDefault();
        $('.volt-modal').css('display', 'flex');
    })
    $('body').on('click', '.volt-modal .close', function (e) {
        e.preventDefault();
        $('.volt-modal').hide();
    })

    function inject_voltio_modal() {
        if ($('[for="payment_method_voltio"]').length > 0) {
            $('<a href="#" class="show-volt-modal"><img src="' + $('.volt-modal').attr('data-volt-icon') + '" /></a>').insertBefore($('[for="payment_method_voltio"]').find('img:not(.volt-modal-icon)'));
            inject_volt_icon(true);
        }
    }

    function inject_volt_icon(show_loader) {
        if (show_loader) {
            voltio.voltBlockUI();
            setTimeout(function () {
                $('.volt-blockui').remove();
            }, 1000);
        }
        if ($('.payment_method_voltio').width() > 380) {
            $('[for="payment_method_voltio"] > img').attr('src', $('.volt-modal').attr('data-volt-icons-border'));
        } else {
            $('[for="payment_method_voltio"] > img').attr('src', $('.volt-modal').attr('data-volt-logo'));
        }

    }

    $(window).on('resize', function () {
        inject_volt_icon(false);
    })


})(jQuery)


