(function($){
    $(document).ready(function(){
        if($('#woocommerce_voltio_mode').length > 0){
            var select = $('#woocommerce_voltio_mode'), selected = false, current = '';
            select.find('option').each(function(){
                if($(this).attr('selected')){
                    selected = true;
                    current = $(this).attr('value');
                }
                else{
                    selected = false;
                }
                $('<label class="label-mode"><input type="radio" name="vmode" '+(selected===true?'checked="checked"':'')+' value="'+$(this).attr('value')+'" />'+$(this).html()+'</label>').insertBefore(select);
            });
            $('<span class="current-config">Configure environment</span><div class="voltio-tabs"><a href="#" class="set-sandbox active" data-mode="sandbox">Sandbox</a><a href="#" class="set-production" data-mode="production">Production</a></div>').insertAfter(select);
            setTimeout(function(){
                $('.voltio-tabs a[data-mode="'+current+'"]').click();
            }, 800);
        }
        $('body').on('click', '.label-mode', function(){
            var clicked = $(this).find('input').val();
            $('.voltio-tabs [data-mode="'+clicked+'"]').click();
        });
        $('body').on('click', '[name="vmode"]', function(){
            $('#woocommerce_voltio_mode').val($(this).val());
        });
        $('.voltio-tabs a').on('click', function(e){
            e.preventDefault();
            $('.voltio-tabs a').removeClass('active');
            $(this).addClass('active');
            toggle_fields_by_mode($(this).attr('data-mode'));
        })
        var current_mode;
        if($('.fields-toggler-by-mode').val() != ''){
            current_mode = $('.fields-toggler-by-mode').val();
        }
        else{
            current_mode = 'sandbox';
        }
        toggle_fields_by_mode(current_mode);
        $('.fields-toggler-by-mode').on('change', function(){
            var val = $(this).val();
            toggle_fields_by_mode(val);
        })
        $('.has-color-picker').wpColorPicker();
        $('.has-px-suffix').each(function(){
            $(this).wrap('<div class="input-px-suffix" />')
        })
    });

    function toggle_fields_by_mode(val){
        $('.form-table tbody tr').each(function(){
            if($(this).find('.toggle-by-mode').length > 0){
                if($(this).find('.toggle-by-mode').hasClass(val)) {
                    $(this).show();
                    $(this).find('input').attr('required', 'required');
                }
                else{
                    $(this).hide();
                    $(this).find('input').removeAttr('required');
                }
            }
        })
    }
})(jQuery)