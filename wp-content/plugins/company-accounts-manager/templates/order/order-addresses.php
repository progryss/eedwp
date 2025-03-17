<?php
/**
 * Order Customer Details for Company Admins
 *
 * This is a custom template for displaying order addresses for Company Admins
 * viewing their child account orders.
 *
 * @package Company_Accounts_Manager
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

$show_shipping = !wc_ship_to_billing_address_only() && $order->needs_shipping_address();
?>
<section class="woocommerce-customer-details cam-order-addresses">
    <h2 class="cam-section-title"><?php _e('Customer Details', 'company-accounts-manager'); ?></h2>

    <?php if ($show_shipping) : ?>
        <section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses">
            <div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">
    <?php endif; ?>

    <h3 class="woocommerce-column__title"><?php _e('Billing address', 'company-accounts-manager'); ?></h3>

    <address>
        <?php echo wp_kses_post($order->get_formatted_billing_address(esc_html__('N/A', 'company-accounts-manager'))); ?>

        <?php if ($order->get_billing_phone()) : ?>
            <p class="woocommerce-customer-details--phone"><?php echo esc_html($order->get_billing_phone()); ?></p>
        <?php endif; ?>

        <?php if ($order->get_billing_email()) : ?>
            <p class="woocommerce-customer-details--email"><?php echo esc_html($order->get_billing_email()); ?></p>
        <?php endif; ?>
    </address>

    <?php if ($show_shipping) : ?>
        </div>

        <div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">
            <h3 class="woocommerce-column__title"><?php _e('Shipping address', 'company-accounts-manager'); ?></h3>
            <address>
                <?php echo wp_kses_post($order->get_formatted_shipping_address(esc_html__('N/A', 'company-accounts-manager'))); ?>

                <?php if ($order->get_shipping_phone()) : ?>
                    <p class="woocommerce-customer-details--phone"><?php echo esc_html($order->get_shipping_phone()); ?></p>
                <?php endif; ?>
            </address>
        </div>
    </section>
    <?php endif; ?>
</section>

<style>
.cam-order-addresses {
    margin-top: 30px;
    padding: 20px;
    background-color: #f8f8f8;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.cam-section-title {
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
    font-size: 18px;
}

.cam-order-addresses .woocommerce-column__title {
    font-size: 16px;
    margin-bottom: 10px;
}

.cam-order-addresses address {
    font-style: normal;
    line-height: 1.5;
}

.cam-order-addresses .woocommerce-customer-details--phone,
.cam-order-addresses .woocommerce-customer-details--email {
    margin: 5px 0 0;
}

@media (min-width: 768px) {
    .cam-order-addresses .woocommerce-columns {
        display: flex;
        gap: 30px;
    }
    
    .cam-order-addresses .woocommerce-column {
        flex: 1;
    }
}
</style> 