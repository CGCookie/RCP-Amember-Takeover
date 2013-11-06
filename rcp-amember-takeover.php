<?php
/*
Plugin Name: Restrict Content Pro - amember Take OVer
Plugin URL: http://pippinsplugins.com/
Description: Kill the heinous rule of amember!
Version: 0.1
Author: Pippin Williamson
Author URI: http://pippinsplugins.com
Contributors: mordauk
*/


define( 'RCP_AMEMBER_DB_MEMBERS', 'amember_members' );
define( 'RCP_AMEMBER_DB_PAYMENTS', 'amember_payments' );


/*
 * Retrieves the Amember user ID from the PayPal payer_id variable
 *
 * @param int the payer_id from Paypal
 * @return int the ID of the member in amember
 *
*/
function rcp_get_amember_user_id_from_payment( $payer_id ) {

	global $wpdb;

	$amember_id = $wpdb->get_var( $wpdb->prepare( "SELECT `member_id` FROM " . RCP_AMEMBER_DB_PAYMENTS . " WHERE `payer_id`='" . $payer_id . "';" ) );

	return $amember_id;
}


/*
 * Retrieves the Amember user ID from the PayPal subscr_id variable
 *
 * @param int the subscr_id from Paypal
 * @return int the ID of the member in amember
 *
*/
function rcp_get_amember_user_id_from_subscr_id( $subscr_id ) {

	global $wpdb;

	$amember_id = $wpdb->get_var( $wpdb->prepare( "SELECT `member_id` FROM " . RCP_AMEMBER_DB_PAYMENTS . " WHERE `receipt_id`='" . $subscr_id . "';" ) );

	return $amember_id;
}


/*
 * Retrieves the Amember user ID from the WordPress login name
 *
 * @param string the user's login name
 * @return int the ID of the member in amember
 *
*/
function rcp_get_amember_user_id_from_wp_login( $user_login ) {

	global $wpdb;

	$amember_id = $wpdb->get_var( $wpdb->prepare( "SELECT `member_id` FROM " . RCP_AMEMBER_DB_MEMBERS . " WHERE `login`='" . $user_login . "';" ) );

	return $amember_id;
}


/*
 * Retrieves the WordPress user ID from the amember user ID
 *
 * @param $amember_user_id int The ID of the user in amember
 * @return int the ID of the member in WordPress
 *
*/
function rcp_get_wp_user_id_from_amember_id( $amember_user_id ) {

	global $wpdb;

	$user_login = $wpdb->get_var( $wpdb->prepare( "SELECT `login` FROM " . RCP_AMEMBER_DB_MEMBERS . " WHERE `member_id`='" . $amember_user_id . "';" ) );

	$wp_user_data = get_user_by( 'login', $user_login );

	return $wp_user_data->ID;
}


/*
 * Stores the PayPal payer_id in the WP usermeta of the specified user
 *
 * @param $wp_user_id int The ID of the user in WordPress
 * @param $payer_id int The PayPal payer_id
 * @return void
 *
*/

function rcp_store_paypal_payer_id( $wp_user_id, $payer_id ) {
	update_user_meta( $wp_user_id, 'rcp_paypal_payer_id', $payer_id );
}


/*
 * Gets the PayPal payer_id from the WP usermeta of the specified user
 *
 * @param $wp_user_id int The ID of the user in WordPress
 * @return mixed - int if found, false otherwise
 *
*/

function rcp_get_paypal_payer_id( $wp_user_id ) {
	return get_user_meta( $wp_user_id, 'rcp_paypal_payer_id', true );
}


/*
 * Gets the WP user object from a PayPal payer_id
 *
 * @param $payer_id int The payer_id from PayPal
 * @return mixed - object if found, false otherwise
 *
*/

function rcp_get_wp_user_from_payer_id( $payer_id ) {

	$user = get_users(
		array(
			'meta_key' => 'rcp_paypal_payer_id',
			'meta_value' => $payer_id,
			'number' => 1
		)
	);
	if ( $user )
		return $user[0]; // if found, return the user object

	return false;
}



/*
 * Gets the last payment made by a user
 *
 * @param $wp_user_id int The WP user ID
 * @return object - the payment data object
 *
*/

function rcp_get_last_amember_payment( $wp_user_id ) {

	global $wpdb;

	$user_data = get_userdata( $wp_user_id );

	$amember_user_id = rcp_get_amember_user_id_from_wp_login( $user_data->user_login );

	$payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . RCP_AMEMBER_DB_PAYMENTS . " WHERE `member_id`='" . $amember_user_id . "' AND `paysys_id`='paypal_r' ORDER BY payment_id DESC;" ) );

	return $payment;
}


/*
 * Convert amember product names to RCP subscription names
 *
 * @param $product_name string The name of the amember product
 * @return string - the name of the subscription level in RCP
 *
*/

function rcp_get_sub_id_from_amember_product( $product_name ) {

	switch ( $product_name ) {

		case 'Monthly' :
			$subscription_name = 'Citizen Monthly';
			break;

		case 'Monthly Recurring' :
			$subscription_name = 'Citizen Monthly';
			break;

		case 'One Month' :
			$subscription_name = 'Citizen Monthly';
			break;

		case 'Quarterly' :
			$subscription_name = 'Citizen Quarterly';
			break;

		case 'Quarterly Recurring' :
			$subscription_name = 'Citizen Quarterly';
			break;

		case 'Three Months - $25' :
			$subscription_name = 'Citizen Quarterly';
			break;

		case 'Yearly Recurring' :
			$subscription_name = 'Citizen Yearly';
			break;

		case 'Yearly' :
			$subscription_name = 'Citizen Yearly';
			break;

		case 'One Year - $88' :
			$subscription_name = 'Citizen Yearly';
			break;

		case 'Educator 30 Days' :
			$subscription_name = 'Studio';
			break;

		case 'Educator - Monthly' :
			$subscription_name = 'Studio';
			break;

		default:
			$subscription_name = 'Citizen Monthly';
			break;
	}

	return $subscription_name;
}


function rcp_move_subscription_to_rcp( $posted ) {

	// only modify if the "custom" var is not a user ID. If this is a normal RCP IPN, custom will be set
	if ( !isset( $posted['custom'] ) || !is_numeric( $posted['custom'] ) || !get_userdata( $posted['custom'] ) ) {


		// this is an amember IPN


		$in_rcp = false;

		// attempt to retrieve the WP user from the payer ID
		$user_data = rcp_get_wp_user_from_payer_id( $posted['payer_id'] );

		// if no WP user found, use other methods
		if ( ! $user_data ) {

			$receipt_id = $posted['txn_type'] == 'web_accept' ? $posted['txn_id'] : $posted['subscr_id'];

			// grab the amember user ID from the subscr ID
			$amember_id = rcp_get_amember_user_id_from_subscr_id( $receipt_id );

			// if amember user found, get the WP user ID
			if ( $amember_id ) {

				$wp_user_id = rcp_get_wp_user_id_from_amember_id( $amember_id );
				$wp_user_id = $wp_user_id ? $wp_user_id : false;

			} else {

				// not found by subscr_id, use payer_id instead (not likely to work)
				$amember_id = rcp_get_amember_user_id_from_payment( $posted['payer_id'] );

				if ( $amember_id ) {

					$wp_user_id = rcp_get_wp_user_id_from_amember_id( $amember_id );
					$wp_user_id = $wp_user_id ? $wp_user_id : false;

				}
			}

			// if WP user ID found, get the user data
			if ( $wp_user_id ) {

				$user_data = get_userdata( $wp_user_id );


				// no WP user found so resort to using the payer email to find the user
			} else {

				// retrieve the user based on their PayPal email
				$user_data = get_user_by( 'email', $posted['payer_email'] );
				$wp_user_id = $user_data ? $user_data->ID : false;
			}

			if ( $wp_user_id ) {
				// store the payer ID
				rcp_store_paypal_payer_id( $wp_user_id, $posted['payer_id'] );
			}

		} else {

			// user has already been transferred and has payer_id stored in meta

			$in_rcp = true;

		}

		// we never found a user so it's time to bail
		if ( !$user_data ) {

			$send_to = array( 'mordauk@gmail.com', 'wes@cgcookie.com', 'jonathan@cgcookie.com' );

			$message = "The email " . $posted['payer_email'] . " could not be processed by the IPN listener. This user needs to be checked and possibly acted upon. IPN data (for Pip):\n\n";
			$message .= print_r( $posted, true );

			// email a note
			wp_mail( $send_to, 'CGC IPN Fail', $message );
			return; // can't do anything for this IPN / user
		}

		// if we got here, then a WP user was successfully found

		$user_id = $user_data->ID;

		$subscription_name = rcp_get_sub_id_from_amember_product( $_POST['item_name'] );

		$subscription = rcp_get_subscription_details_by_name( $subscription_name );

		$subscription_id = $subscription->id;

		if ( ! $in_rcp ) {

			$subscription_key = strtolower( md5( uniqid() ) );
			update_user_meta( $user_id, 'rcp_subscription_key', $subscription_key );
			update_user_meta( $user_id, 'rcp_subscription_level', $subscription_id );

		} else {

			if ( $posted['txn_type'] == 'subscr_payment' ) {

				//$subscription_key = 'this is a test key';
				$subscription_key = rcp_get_subscription_key( $user_id );
				if ( ! $subscription_key ) {
					$subscription_key = strtolower( md5( uniqid() ) );
					update_user_meta( $user_id, 'rcp_subscription_key', $subscription_key );
					update_user_meta( $user_id, 'rcp_subscription_level', $subscription_id );
				}

			} else {
				// if this is a new payment or subscr_signup, we need to create a new subscription key
				$subscription_key = strtolower( md5( uniqid() ) );
				update_user_meta( $user_id, 'rcp_subscription_key', $subscription_key );
				update_user_meta( $user_id, 'rcp_subscription_level', $subscription_id );

			}		

		}
		$posted['item_number'] = $subscription_key;
		$posted['custom'] = $user_id;
		$posted['item_name'] = $subscription_name;

	}

	return $posted;

}
add_filter( 'rcp_ipn_post', 'rcp_move_subscription_to_rcp' );

function rcp_cgc_store_paypal_payer_id( $payment_data, $user_id, $posted ) {
	rcp_store_paypal_payer_id( $user_id, $posted['payer_id'] );
}
add_action( 'rcp_valid_ipn', 'rcp_cgc_store_paypal_payer_id', 10, 3 );

function rcp_add_cgc_table_column_header_and_footer() {
	echo '<th>Payer ID</th>';
}
add_action( 'rcp_members_page_table_header', 'rcp_add_cgc_table_column_header_and_footer' );
add_action( 'rcp_members_page_table_footer', 'rcp_add_cgc_table_column_header_and_footer' );

function rcp_add_cgc_table_column_content( $user_id ) {
	$payer_id = rcp_get_paypal_payer_id( $user_id );
	echo '<td>' . $payer_id . '</td>';
}
add_action( 'rcp_members_page_table_column', 'rcp_add_cgc_table_column_content' );
