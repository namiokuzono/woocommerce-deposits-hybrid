jQuery(document).ready(function($) {
    var $options = $('.wc-deposits-hybrid-options');
    
    // Handle payment option selection
    $options.on('change', 'input[type=radio][name=wc_deposits_hybrid_option]', function() {
        var selectedOption = $(this).val();
        
        // Hide all payment plan options
        $options.find('.wc-deposits-hybrid-plan-select').hide();
        
        // Show payment plan options if plan is selected
        if (selectedOption === 'plan') {
            $options.find('.wc-deposits-hybrid-plan-select').show();
        }
        
        // Update cart item data
        updateCartItemData(selectedOption);
    });

    // Handle payment plan selection
    $options.on('change', '#wc_deposits_hybrid_plan_id', function() {
        var selectedOption = $options.find('input[name=wc_deposits_hybrid_option]:checked').val();
        if (selectedOption === 'plan') {
            updateCartItemData(selectedOption);
        }
    });

    // Update cart item data
    function updateCartItemData(selectedOption) {
        var data = {
            action: 'wc_deposits_hybrid_update_cart_item',
            nonce: wc_deposits_hybrid_params.nonce,
            option: selectedOption
        };

        if (selectedOption === 'plan') {
            data.plan_id = $options.find('#wc_deposits_hybrid_plan_id').val();
        }

        $.ajax({
            url: wc_deposits_hybrid_params.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // Trigger WooCommerce price update
                    $(document.body).trigger('wc_deposits_hybrid_price_updated');
                }
            }
        });
    }
}); 