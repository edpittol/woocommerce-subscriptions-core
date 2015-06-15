<?php
/**
 * Class to handle everything to do with changing a payment method for a subscription on the
 * edit subscription admin page.
 *
 * @class    WCS_Change_Payment_Method_Admin
 * @version  2.0
 * @package  WooCommerce Subscriptions/Includes
 * @category Class
 * @author   Prospress
 */

class WCS_Change_Payment_Method_Admin {

	/**
	 * Display the edit payment gateway option under
	 *
	 * @since 2.0
	 */
	public static function display_fields( $subscription ) {

		$payment_method        = ! empty( $subscription->payment_method ) ? $subscription->payment_method : '';
		$valid_payment_methods = self::get_valid_payment_methods( $subscription );

		echo '<p class="form-field form-field-wide">';

		if ( count( $valid_payment_methods ) > 1 ) {

			$found_method = false;
			echo '<label>' . esc_html__( 'Payment Method', 'woocommerce' ) . ':</label>';
			echo '<select class="wcs_payment_method_selector" name="_payment_method" id="_payment_method" class="first">';

			foreach ( $valid_payment_methods as $gateway_id => $gateway_title ) {

				echo '<option value="' . esc_attr( $gateway_id ) . '" ' . selected( $payment_method, $gateway_id, false ) . '>' . esc_html( $gateway_title ) . '</option>';
				if ( $payment_method == $gateway_id ) {
					$found_method = true;
				}
			}
			echo '</select>';

		} elseif ( count( $valid_payment_methods ) == 1 ) {
			echo '<strong>' . esc_html__( 'Payment Method', 'woocommerce' ) . ':</strong> ' . esc_html( current( $valid_payment_methods ) );
			echo '<input type="hidden" value="' . esc_attr( key( $valid_payment_methods ) ) . '" id="_payment_method" name="_payment_method">';
		}

		echo '</p>';

		$payment_method_table = apply_filters( 'woocommerce_subscription_payment_meta', array(), $subscription );

		if ( is_array( $payment_method_table ) ) {

			foreach ( $payment_method_table as $payment_method_id => $payment_method_meta ) {

				echo '<div class="wcs_payment_method_meta_fields" id="wcs_' . esc_attr( $payment_method_id ) . '_fields" ' . ( ( $payment_method_id != $payment_method || $subscription->is_manual() ) ? 'style="display:none;"' : '' ) .' >';

				foreach ( $payment_method_meta as $meta_table => $meta ) {

					foreach ( $meta as $meta_key => $meta_data ) {

						$field_id    = sprintf( '_payment_method_meta[%s][%s]', $meta_table , $meta_key );
						$field_label = ( ! empty( $meta_data['label'] ) ) ? $meta_data['label'] : $meta_key ;
						$field_value = ( ! empty( $meta_data['value'] ) ) ? $meta_data['value'] : null ;

						echo '<p class="form-field-wide">';
						echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $field_label ) . '</label>';
						echo '<input type="text" class="short" name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '" value="' . esc_attr( $field_value ) . '" placeholder="">';
						echo '</p>';

					}
				}

				echo '</div>';

			}
		}

	}

	/**
	 * Get the new payment data from POST and check the new payment method supports
	 * the new admin change hook.
	 *
	 * @since 2.0
	 * @param $subscription WC_Subscription
	 */
	public static function save_meta( $subscription ) {

		if ( empty( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( $_POST['woocommerce_meta_nonce'], 'woocommerce_save_data' ) ) {
			return;
		}

		$payment_gateways    = WC()->payment_gateways->payment_gateways();
		$payment_method      = isset( $_POST['_payment_method'] ) ? wc_clean( $_POST['_payment_method'] ) : '';
		$payment_method_meta = apply_filters( 'woocommerce_subscription_payment_meta', array(), $subscription );
		$payment_method_meta = ( ! empty( $payment_method_meta[ $payment_method ] ) ) ? $payment_method_meta[ $payment_method ] : array();

		$valid_payment_methods = self::get_valid_payment_methods( $subscription );

		if ( ! isset( $valid_payment_methods[ $payment_method ] ) ) {
			throw new Exception( __( 'Please choose a valid payment gateway to change to.', 'woocommerce-subscriptions' ) );
		}

		if ( ! empty( $payment_method_meta ) ) {

			foreach ( $payment_method_meta as $meta_table => &$meta ) {

				if ( ! is_array( $meta ) ) {
					continue;
				}

				foreach ( $meta as $meta_key => &$meta_data ) {
					$meta_data['value'] = $_POST['_payment_method_meta'][ $meta_table ][ $meta_key ];
				}
			}
		}

		$payment_gateway  = ( 'manual' != $payment_method ) ? $payment_gateways[ $payment_method ] : '';
		$subscription->set_payment_method( $payment_gateway, $payment_method_meta );

	}

	/**
	 * Get a list of possible gateways that a subscription could be changed to by admins.
	 *
	 * @since 2.0
	 * @param $subscription int | WC_Subscription
	 * @return
	 */
	public static function get_valid_payment_methods( $subscription ) {

		if ( ! $subscription instanceof WC_Subscription ) {
			$subscription = wcs_get_subscription( $subscription );
		}

		$valid_gateways = array();

		if ( 'yes' == get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals', 'no' ) || $subscription->is_manual() ) {
			$valid_gateways['manual'] = __( 'Manual Renewal', 'woocommerce-subscriptions' );
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		foreach ( $available_gateways as $gateway_id => $gateway ) {

			if ( $gateway->supports( 'subscription_payment_method_change_admin' ) && 'no' == get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'no' ) || ( ! $subscription->is_manual() && $gateway_id == $subscription->payment_method ) ) {
				$valid_gateways[ $gateway_id ] = $gateway->get_title();

			}
		}

		return $valid_gateways;

	}

}
