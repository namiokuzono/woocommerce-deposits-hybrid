<?php
// Template for Hybrid Deposit Options on the product page
if ( ! defined( 'ABSPATH' ) ) exit;

// Only show if at least one option is available
$has_nrd = ( $initial_percent && $is_nrd === 'yes' );
$has_plans = ( $allow_plans === 'yes' && ! empty( $selected_plans ) );
if ( ! $has_nrd && ! $has_plans ) return;
?>
<div class="wc-deposits-hybrid-options">
    <h4><?php esc_html_e( 'Choose a payment option:', 'wc-deposits-hybrid' ); ?></h4>
    <ul>
        <li>
            <label>
                <input type="radio" name="wc_deposits_hybrid_option" value="full" checked />
                <?php esc_html_e( 'Full Payment (100% upfront)', 'wc-deposits-hybrid' ); ?>
            </label>
        </li>
        <?php if ( $has_nrd ) : ?>
        <li>
            <label>
                <input type="radio" name="wc_deposits_hybrid_option" value="nrd" />
                <?php printf( esc_html__( '20%% Non-Refundable Deposit (Pay %s%% now, balance later)', 'wc-deposits-hybrid' ), esc_html( $initial_percent ) ); ?>
            </label>
        </li>
        <?php endif; ?>
        <?php if ( $has_plans ) : ?>
        <li>
            <label>
                <input type="radio" name="wc_deposits_hybrid_option" value="plan" />
                <?php printf( esc_html__( 'Payment Plan (Pay %s%% now, balance over time)', 'wc-deposits-hybrid' ), esc_html( $initial_percent ) ); ?>
            </label>
            <div class="wc-deposits-hybrid-plan-select" style="display:none; margin-top:8px;">
                <label for="wc_deposits_hybrid_plan_id"><?php esc_html_e( 'Choose a plan:', 'wc-deposits-hybrid' ); ?></label>
                <select name="wc_deposits_hybrid_plan_id" id="wc_deposits_hybrid_plan_id">
                    <?php foreach ( $selected_plans as $plan_id ) :
                        if ( isset( $payment_plans[ $plan_id ] ) ) : ?>
                        <option value="<?php echo esc_attr( $plan_id ); ?>"><?php echo esc_html( $payment_plans[ $plan_id ] ); ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
        </li>
        <?php endif; ?>
    </ul>
</div>
<script>
jQuery(function($){
    var $options = $('.wc-deposits-hybrid-options');
    $options.find('input[type=radio][name=wc_deposits_hybrid_option]').on('change', function(){
        if($(this).val() === 'plan') {
            $options.find('.wc-deposits-hybrid-plan-select').show();
        } else {
            $options.find('.wc-deposits-hybrid-plan-select').hide();
        }
    });
});
</script> 