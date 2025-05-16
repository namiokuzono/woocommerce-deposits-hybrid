jQuery(document).ready(function($) {
    // Handle payment option selection
    $('input[name="wc_deposits_hybrid_option"]').on('change', function() {
        var selectedOption = $(this).val();
        
        // Hide all payment plan options
        $('.payment-plan-options').hide();
        
        // Show payment plan options if plan is selected
        if (selectedOption === 'plan') {
            $('.payment-plan-options').show();
        }
        
        // Update cart item data
        updateCartItemData(selectedOption);
    });

    // Handle payment plan selection
    $('#wc_deposits_hybrid_plan_id').on('change', function() {
        var selectedOption = $('input[name="wc_deposits_hybrid_option"]:checked').val();
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
            data.plan_id = $('#wc_deposits_hybrid_plan_id').val();
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