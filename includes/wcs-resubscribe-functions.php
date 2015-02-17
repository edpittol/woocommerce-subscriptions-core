<?php
/**
 * WooCommerce Subscriptions Resubscribe Functions
 *
 * Functions for managing resubscribing to expired or cancelled subscriptions.
 *
 * @author 		Prospress
 * @category 	Core
 * @package 	WooCommerce Subscriptions/Functions
 * @version     2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Check if a given order was created to resubscribe to a cancelled or expired subscription.
 *
 * @param WC_Order|int $order The WC_Order object or ID of a WC_Order order.
 * @since 2.0
 */
function wcs_is_resubscribe_order( $order ) {

	if ( ! is_object( $order ) ) {
		$order = new WC_Order( $order );
	}

	if ( '' !== get_post_meta( $order->id, '_original_subscription', true ) && 0 == $order->post->post_parent ) {
		$is_resubscribe_order = true;
	} else {
		$is_resubscribe_order = false;
	}

	return apply_filters( 'woocommerce_subscriptions_is_resubscribe_order', $is_resubscribe_order, $order );
}