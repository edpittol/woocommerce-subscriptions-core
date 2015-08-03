<?php
/**
 * WooCommerce Subscriptions Formatting
 *
 * Functions for formatting subscription data.
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
 * Creates a subscription price string from an array of subscription details. For example, ""$5 / month for 12 months".
 *
 * @param array $subscription_details A set of name => value pairs for the subscription details to include in the string. Available keys:
 *		'initial_amount': The upfront payment for the subscription, including sign up fees, as a string from the @see wc_price(). Default empty string (no initial payment)
 *		'initial_description': The word after the initial payment amount to describe the amount. Examples include "now" or "initial payment". Defaults to "up front".
 *		'recurring_amount': The amount charged per period. Default 0 (no recurring payment).
 *		'subscription_interval': How regularly the subscription payments are charged. Default 1, meaning each period e.g. per month.
 *		'subscription_period': The temporal period of the subscription. Should be one of {day|week|month|year} as used by @see wcs_get_subscription_period_strings()
 *		'subscription_length': The total number of periods the subscription should continue for. Default 0, meaning continue indefinitely.
 *		'trial_length': The total number of periods the subscription trial period should continue for.  Default 0, meaning no trial period.
 *		'trial_period': The temporal period for the subscription's trial period. Should be one of {day|week|month|year} as used by @see wcs_get_subscription_period_strings()
 * @since 2.0
 * @return string The price string with translated and billing periods included
 */
function wcs_price_string( $subscription_details ) {
	global $wp_locale;

	$subscription_details = wp_parse_args( $subscription_details, array(
			'currency'              => '',
			'initial_amount'        => '',
			'initial_description'   => _x( 'up front', 'initial payment on a subscription', 'woocommerce-subscriptions' ),
			'recurring_amount'      => '',

			// Schedule details
			'subscription_interval' => 1,
			'subscription_period'   => '',
			'subscription_length'   => 0,
			'trial_length'          => 0,
			'trial_period'          => '',

			// Syncing details
			'is_synced'                => false,
			'synchronised_payment_day' => 0,

			// Params for wc_price()
			'display_excluding_tax_label' => false,
		)
	);

	$subscription_details['subscription_period'] = strtolower( $subscription_details['subscription_period'] );

	// Make sure prices have been through wc_price()
	if ( is_numeric( $subscription_details['initial_amount'] ) ) {
		$initial_amount_string = wc_price( $subscription_details['initial_amount'], array( 'currency' => $subscription_details['currency'], 'ex_tax_label' => $subscription_details['display_excluding_tax_label'] ) );
	} else {
		$initial_amount_string = $subscription_details['initial_amount'];
	}

	if ( is_numeric( $subscription_details['recurring_amount'] ) ) {
		$recurring_amount_string = wc_price( $subscription_details['recurring_amount'], array( 'currency' => $subscription_details['currency'], 'ex_tax_label' => $subscription_details['display_excluding_tax_label'] ) );
	} else {
		$recurring_amount_string = $subscription_details['recurring_amount'];
	}

	$subscription_period_string = wcs_get_subscription_period_strings( $subscription_details['subscription_interval'], $subscription_details['subscription_period'] );
	$subscription_ranges = wcs_get_subscription_ranges();

	if ( $subscription_details['subscription_length'] > 0 && $subscription_details['subscription_length'] == $subscription_details['subscription_interval'] ) {
		if ( ! empty( $subscription_details['initial_amount'] ) ) {
			if ( $subscription_details['subscription_interval'] == $subscription_details['subscription_length'] && 0 == $subscription_details['trial_length'] ) {
				$subscription_string = $initial_amount_string;
			} else {
				/* translators: 1$: initial amount, 2$: initial description (eg "up front"), 3$: recurring amount string (eg £10 / month ) */
				$subscription_string = sprintf( __( '%1$s %2$s then %3$s', 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string );
			}
		} else {
			$subscription_string = $recurring_amount_string;
		}
	} elseif ( true === $subscription_details['is_synced'] && in_array( $subscription_details['subscription_period'], array( 'week', 'month', 'year' ) ) ) {
		// Verbosity is important here to enable translation
		$payment_day = $subscription_details['synchronised_payment_day'];
		switch ( $subscription_details['subscription_period'] ) {
			case 'week':
				$payment_day_of_week = WC_Subscriptions_Synchroniser::get_weekday( $payment_day );
				if ( 1 == $subscription_details['subscription_interval'] ) {
					if ( ! empty( $subscription_details['initial_amount'] ) ) {
						/* translators: 1$: initial amount, 2$: initial description (eg "up front"), 3$: recurring amount string, 4$: payment day of the week, e.g $15 up front, then $10 every Wednesday */
						$subscription_string = sprintf( __( '%1$s %2$s then %3$s every %4$s', 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, $payment_day_of_week );
					} else {
						/* translators: 1$: recurring amount string, 2$: day of the week, eg $10 every Wednesday */
						$subscription_string = sprintf( __( '%1$s every %2$s', 'woocommerce-subscriptions' ), $recurring_amount_string, $payment_day_of_week );
					}
				} else {
					 // e.g. $5 every 2 weeks on Wednesday
					if ( ! empty( $subscription_details['initial_amount'] ) ) {
						/* translators: 1$: initial amount, 2$: initial description (eg "up front" ), 3$: recurring amount, 4$: interval (eg "2nd week"), 5$: day of the week (eg "thursday"); eg $10 up front, then $20 every 2nd week on Wednesday */
						$subscription_string = sprintf( __( '%1$s %2$s then %3$s every %4%s on %5$s', 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, wcs_get_subscription_period_strings( $subscription_details['subscription_interval'], $subscription_details['subscription_period'] ), $payment_day_of_week );
					} else {
						/* translators: 1$: recurring amount string, 2$: interval (eg "2nd week"), 3$: day of the week (eg "thursday"); eg $10 every 2nd week on Wednesday */
						$subscription_string = sprintf( __( '%1$s every %2$s on %3$s', 'woocommerce-subscriptions' ), $recurring_amount_string, wcs_get_subscription_period_strings( $subscription_details['subscription_interval'], $subscription_details['subscription_period'] ), $payment_day_of_week );
					}
				}
				break;
			case 'month':
				if ( 1 == $subscription_details['subscription_interval'] ) {
					// e.g. $15 on the 15th of each month
					if ( ! empty( $subscription_details['initial_amount'] ) ) {
						if ( $payment_day > 27 ) {
							/* translators: 1$: initial amount, 2$: initial description (eg "up front"), 3$: recurring amount; eg $10 up front then $30 on the last day of each month */
							$subscription_string = sprintf( __( '%1$s %2$s then %3$s on the last day of each month', 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string );
						} else {
							/* translators: 1$: initial amount, 2$: initial description (eg "up front"), 3$: recurring amount, 4$: day of the month (eg "23rd"); eg $10 up front then $40 on the 23rd of each month */
							$subscription_string = sprintf( __( '%1$s %2$s then %3$s on the %4$s of each month', 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, WC_Subscriptions::append_numeral_suffix( $payment_day ) );
						}
					} else {
						if ( $payment_day > 27 ) {
							/* translators: placeholder is recurring amount */
							$subscription_string = sprintf( __( '%s on the last day of each month', 'woocommerce-subscriptions' ), $recurring_amount_string );
						} else {
							/* translators: %1: recurring amount, %2: day of the month (eg "23rd") */
							$subscription_string = sprintf( __( '%1$s on the %2$s of each month', 'woocommerce-subscriptions' ), $recurring_amount_string, WC_Subscriptions::append_numeral_suffix( $payment_day ) );
						}
					}
				} else {
					// e.g. $15 on the 15th of every 3rd month
					if ( ! empty( $subscription_details['initial_amount'] ) ) {
						if ( $payment_day > 27 ) {
							/* translators: %1: initial amount, %2: initial description (eg "up front"), %3: recurring amount, %4: interval (eg "3rd") */
							$subscription_string = sprintf( __( '%1$s %2$s then %3$s on the last day of every %4$s month', 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, WC_Subscriptions::append_numeral_suffix( $subscription_details['subscription_interval'] ) );
						} else {
							/* translators: %1: initial amount, %2: initial description (eg "up front"), %3: recurring amount, %4: day of the month (eg "23rd"), %5: interval (eg: "3rd") */
							$subscription_string = sprintf( __( '%1$s %2$s then %3$s on the %4$s day of every %5$s month', 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, WC_Subscriptions::append_numeral_suffix( $payment_day ), WC_Subscriptions::append_numeral_suffix( $subscription_details['subscription_interval'] ) );
						}
					} else {
						if ( $payment_day > 27 ) {
							/* translators: %1: recurring amount, %2: interval (eg "3rd") */
							$subscription_string = sprintf( __( '%1$s on the last day of every %2$s month', 'woocommerce-subscriptions' ), $recurring_amount_string, WC_Subscriptions::append_numeral_suffix( $subscription_details['subscription_interval'] ) );
						} else {
							/* translators: %1: recurring amount, %2: day in month (eg "23rd"), %3: interval (eg: "3rd") */
							$subscription_string = sprintf( __( '%1$s on the %2$s day of every %3$s month', 'woocommerce-subscriptions' ), $recurring_amount_string, WC_Subscriptions::append_numeral_suffix( $payment_day ), WC_Subscriptions::append_numeral_suffix( $subscription_details['subscription_interval'] ) );
						}
					}
				}
				break;
			case 'year':
				if ( 1 == $subscription_details['subscription_interval'] ) {
					// e.g. $15 on March 15th each year
					if ( ! empty( $subscription_details['initial_amount'] ) ) {
						/* translators: %1: initial amount, %2: intial description (eg "up front"), %3: recurring amount, %4: month of year (eg "March"), %5: day of the month (eg "23rd") */
						$subscription_string = sprintf( __( '%1$s %2$s then %3$s on %4$s %5$s each year', 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, $wp_locale->month[ $payment_day['month'] ], WC_Subscriptions::append_numeral_suffix( $payment_day['day'] ) );
					} else {
						/* translators: %1: recurring amount, %2: month (eg "March"), %3: day of the month (eg "23rd") */
						$subscription_string = sprintf( __( '%1$s on %2$s %3$s each year', 'woocommerce-subscriptions' ), $recurring_amount_string, $wp_locale->month[ $payment_day['month'] ], WC_Subscriptions::append_numeral_suffix( $payment_day['day'] ) );
					}
				} else {
					// e.g. $15 on March 15th every 3rd year
					if ( ! empty( $subscription_details['initial_amount'] ) ) {
						/* translators: %1: initial amount, %2: initial description (eg "up front"), %3: recurring amount, %4: month (eg "March"), %5: day of the month (eg "23rd"), %6: interval (eg "3rd") */
						$subscription_string = sprintf( __( '%1$s %2$s then %3$s on %4$s %5$s every %6$s year', 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, $wp_locale->month[ $payment_day['month'] ], WC_Subscriptions::append_numeral_suffix( $payment_day['day'] ), WC_Subscriptions::append_numeral_suffix( $subscription_details['subscription_interval'] ) );
					} else {
						/* translators: %1: recurring amount, %2: month (eg "March"), %3: day of month (eg "23rd"), %4: interval (eg "3rd") */
						$subscription_string = sprintf( __( '%1$s on %2$s %3$s every %4$s year', 'woocommerce-subscriptions' ), $recurring_amount_string, $wp_locale->month[ $payment_day['month'] ], WC_Subscriptions::append_numeral_suffix( $payment_day['day'] ), WC_Subscriptions::append_numeral_suffix( $subscription_details['subscription_interval'] ) );
					}
				}
				break;
		}
	} elseif ( ! empty( $subscription_details['initial_amount'] ) ) {
		/* translators: %1: initial amount, %2: initial description (eg "up front"), %3: recurring amount, %4: subscription period (eg "month" or "3 months") */
		$subscription_string = sprintf( _n( '%1$s %2$s then %3$s / %4$s', '%1$s %2$s then %3$s every %4$s', $subscription_details['subscription_interval'], 'woocommerce-subscriptions' ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, $subscription_period_string );
	} elseif ( ! empty( $subscription_details['recurring_amount'] ) || intval( $subscription_details['recurring_amount'] ) === 0 ) {
		/* translators: %1: recurring amount, %2: subscription period (eg "month" or "3 months") */
		$subscription_string = sprintf( _n( '%1$s / %2$s', ' %1$s every %2$s', $subscription_details['subscription_interval'], 'woocommerce-subscriptions' ), $recurring_amount_string, $subscription_period_string );
	} else {
		$subscription_string = '';
	}

	if ( $subscription_details['subscription_length'] > 0 ) {
		/* translators: %1: subscription string (eg "$10 up front then $5 on March 23rd every 3rd year"), %2: length (eg: "4 years") */
		$subscription_string = sprintf( __( '%1$s for %2$s', 'woocommerce-subscriptions' ), $subscription_string, $subscription_ranges[ $subscription_details['subscription_period'] ][ $subscription_details['subscription_length'] ] );
	}

	if ( $subscription_details['trial_length'] > 0 ) {
		$trial_length = wcs_get_subscription_trial_period_strings( $subscription_details['trial_length'], $subscription_details['trial_period'] );
		if ( ! empty( $subscription_details['initial_amount'] ) ) {
			/* translators: %1: subscription string (eg "$10 up front then $5 on March 23rd every 3rd year"), %2: trial length (eg "3 weeks") */
			$subscription_string = sprintf( __( '%1$s after %2$s free trial', 'woocommerce-subscriptions' ), $subscription_string, $trial_length );
		} else {
			/* translators: %1: trial length (eg: "3 weeks"), %2: subscription string (eg "$10 up front then $5 on March 23rd every 3rd year") */
			$subscription_string = sprintf( __( '%1$s free trial then %2$s', 'woocommerce-subscriptions' ), ucfirst( $trial_length ), $subscription_string );
		}
	}

	if ( $subscription_details['display_excluding_tax_label'] && get_option( 'woocommerce_calc_taxes' ) == 'yes' ) {
		$subscription_string .= ' <small>' . WC()->countries->ex_tax_or_vat() . '</small>';
	}

	return apply_filters( 'woocommerce_subscription_price_string', $subscription_string, $subscription_details );
}
