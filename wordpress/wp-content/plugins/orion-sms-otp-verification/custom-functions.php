<?php
/**
 * Custom functions for the Orion Plugin.
 * Contains definition of constants, file includes and enqueuing stylesheets and scripts.
 *
 * @package Orion SMS OTP verification
 */

/* Define Constants */
define( 'IHS_OTP_URI', plugins_url( 'orion-sms-otp-verification' ) );
define( 'IHS_OTP_PATH', plugin_dir_path( __FILE__ ) );
define( 'IHS_OTP_JS_URI', plugins_url( 'orion-sms-otp-verification' ) . '/vendor/js' );
define( 'IHS_OTP_CSS_URI', plugins_url( 'orion-sms-otp-verification' ) . '/css' );

require IHS_OTP_PATH . 'vendor/twilio-php-master/Twilio/index.php';


if ( ! function_exists( 'ihs_otp_enqueue_scripts' ) ) {
	/**
	 * Enqueue Styles and Scripts.
	 */
	function ihs_otp_enqueue_scripts( $hook ) {
		wp_enqueue_style( 'ihs_otp_styles', IHS_OTP_URI . '/style.css' );

		// If its the checkout page.
		if ( class_exists( 'WooCommerce' ) && is_checkout() ) {
			wp_enqueue_script( 'ihs_otp_woo_commerce_js', IHS_OTP_JS_URI . '/woo-commerce-js/woo-commerce.js', array( 'jquery' ), '', true );
			wp_localize_script(
				'ihs_otp_woo_commerce_js', 'otp_obj', array(
					'ajax_url'   => admin_url( 'admin-ajax.php' ),
					'ajax_nonce' => wp_create_nonce( 'ihs_otp_nonce_action_name' ),
					'form_selector' => get_option( 'ihs_otp_woo_form_selector' ),
					'submit_btn_selector' => get_option( 'ihs_otp_woo_submit_btn-selector' ),
					'input_required' => get_option( 'ihs_otp_woo_mobile_input_required' ),
					'mobile_input_name' => get_option( 'ihs_otp_woo_mobile_input_name' ),
					'ihs_country_code' => get_option( 'ihs_otp_woo_country_code' ),
					'ihs_mobile_length' => get_option( 'ihs_woo_mobile_length' ),
				)
			);
		} else {
			wp_enqueue_script( 'ihs_otp_main_js', IHS_OTP_JS_URI . '/main.js', array( 'jquery' ), '', true );
			wp_enqueue_script( 'ihs_otp_reset_password_js', IHS_OTP_JS_URI . '/reset-password.js', array( 'jquery' ), '', true );

			wp_localize_script(
				'ihs_otp_main_js', 'otp_obj', array(
					'ajax_url'            => admin_url( 'admin-ajax.php' ),
					'ajax_nonce'          => wp_create_nonce( 'ihs_otp_nonce_action_name' ),
					'form_selector'       => get_option( 'ihs_otp_form_selector' ),
					'submit_btn_selector' => get_option( 'ihs_otp_submit_btn-selector' ),
					'input_required'      => get_option( 'ihs_otp_mobile_input_required' ),
					'mobile_input_name'   => get_option( 'ihs_otp_mobile_input_name' ),
					'ihs_country_code'    => get_option( 'ihs_otp_country_code' ),
					'ihs_mobile_length'   => get_option( 'ihs_mobile_length' ),
				)
			);
			wp_localize_script(
				'ihs_otp_reset_password_js', 'reset_pass_obj', array(
					'ajax_url'          => admin_url( 'admin-ajax.php' ),
					'ajax_nonce'        => wp_create_nonce( 'ihs_otp_nonce_reset_pass' ),
					'form_selector'     => get_option( 'ihs_otp_login_form_selector' ),
					'country_code'      => get_option( 'ihs_otp_mob_country_code' ),
					'ihs_mobile_length' => get_option( 'ihs_mobile_length' ),
					'login_input_name'  => get_option( 'ihs_otp_login_form_input_name' ),
				)
			);
		}
	}
}

add_action( 'wp_enqueue_scripts', 'ihs_otp_enqueue_scripts' );

if ( ! function_exists( 'ihs_otp_enqueue_admin_scripts' ) ) {
	/**
	 * Enqueue Styles and Scripts for admin.
	 *
	 * @param {string} $hook Hook.
	 */
	function ihs_otp_enqueue_admin_scripts( $hook ) {
		if ( 'toplevel_page_orion-sms-otp-verification/inc/admin-settings' === $hook
		|| 'orion-otp_page_ihs_otp_plugin_woocommerce_settings_page' === $hook ) {
			wp_enqueue_style( 'ihs_otp_admin_font_awesome', '//use.fontawesome.com/releases/v5.0.13/css/all.css' );
			wp_enqueue_style( 'ihs_otp_admin_bootstrap_styles', '//stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css' );
			wp_enqueue_style( 'ihs_otp_admin_styles', IHS_OTP_CSS_URI . '/admin.css' );
			wp_enqueue_script( 'ihs_otp_admin_script', IHS_OTP_JS_URI . '/admin.js', array( 'jquery' ), '', true );
		}
	}
	add_action( 'admin_enqueue_scripts', 'ihs_otp_enqueue_admin_scripts' );
}


if ( ! function_exists( 'ihs_otp_ajax_handler' ) ) {
	/**
	 * Send OTP .
	 */
	function ihs_otp_ajax_handler() {
		if ( isset( $_POST['security'] ) ) {
			$nonce_val = esc_html( wp_unslash( $_POST['security'] ) );
		}

		if ( ! wp_verify_nonce( $nonce_val, 'ihs_otp_nonce_action_name' ) ) {
			wp_die();
		}

		$mobile_number = $_POST['data']['mob'];
		$woo_commerce_query = ( ! empty( $_POST['data']['woo_commerce'] ) ) ? $_POST['data']['woo_commerce'] : false;
		$country_code_from_form = $_POST['data']['country_code'];
		$country_code_from_form = str_replace( '+', '', $country_code_from_form );
		$otp_country_cod = ( ! empty( $woo_commerce_query ) ) ? get_option( 'ihs_otp_woo_country_code' ) : get_option( 'ihs_otp_country_code' );
		$country_code = ( $country_code_from_form ) ? $country_code_from_form : $otp_country_cod;

		$otp_pin = mt_rand( 100000, 500000 );

		$mobile_number = ( isset( $mobile_number ) && is_numeric( $mobile_number ) ) ? wp_unslash( $mobile_number ) : '';
		$mobile_number = absint( $mobile_number );
		$message_template = ( ! empty( $woo_commerce_query ) ) ? get_option( 'ihs_otp_woo_msg_template' ) : get_option( 'ihs_otp_msg_template' );
		$api_used =  ( get_option( 'ihs_api_type' ) ) ? get_option( 'ihs_api_type' ) : 'otp';

		// Send the OTP and you should get a boolean response from the ihs_send_otp function.
		$response = ihs_send_otp( $mobile_number, $country_code, $otp_pin, $message_template, $woo_commerce_query );
//		$response = true;

		// If response is true which means message was sent
		if ( $response ) {

			// Send OTP if the api used is msg91, if its twilio then don't send the otp
			$otp_pin = ( 'msg91' === $api_used ) ? $otp_pin : '';
			$is_otp_send = true;
		} else {
			$is_otp_send = false;
			$otp_pin = '';
		}

		wp_send_json_success(
			array(
				'otp_pin_sent_to_js' => $otp_pin,
				'otp_sent' => strval( $is_otp_send ),
				'mobile' => strval( $mobile_number ),
				'country_code' => strval( $country_code ),
				'api' => $api_used,
				'data_recieved_from_js'    => $_POST,
			)
		);
	}

	add_action( 'wp_ajax_ihs_otp_ajax_hook', 'ihs_otp_ajax_handler' );
	add_action( 'wp_ajax_nopriv_ihs_otp_ajax_hook', 'ihs_otp_ajax_handler' );
}

if ( ! function_exists( 'ihs_send_otp' ) ) {
	/**
	 * Send Otp after checking the route and  the api to be used
	 *
	 * @param {int}    $mob_number Mobile number.
	 * @param {int}    $country_code Country Code.
	 * @param {string} $otp_pin Otp pin.
	 * @param {string} $message_template Message Template.
	 *
	 * @return {mixed} $response Response or Error.
	 */
	function ihs_send_otp( $mob_number, $country_code, $otp_pin, $message_template, $woo_commerce_query ) {
		$auth_key = get_option( 'ihs_otp_auth_key' );
		$otp_length = strlen( $otp_pin );
		$message = str_replace( '{OTP}', $otp_pin, $message_template );
		$sender_id = ( ! empty( $woo_commerce_query ) ) ? get_option( 'ihs_otp_woo_sender_id' ) : get_option( 'ihs_otp_sender_id' );
		$country_code = str_replace( '+', '', $country_code );
		$mob_number_with_country_code = '+' . $country_code . $mob_number;
//		$message = urlencode( $message );

		$route = ( ! empty( $woo_commerce_query ) ) ? get_option( 'ihs_woo_mgs_route' ) : get_option( 'ihs_mgs_route' );
		$api_used =  ( get_option( 'ihs_api_type' ) ) ? get_option( 'ihs_api_type' ) : 'otp';

		// If twilio api used
		if ( 'twilio' === $api_used ) {

			// Will return true if the otp was sent successfully.
			return ihs_send_otp_via_twilio( $mob_number, $country_code, $message );
		} else {

			// If msg91 used, check which route used and then send otp.
			if ( 'otp-route' === $route ) {

				// Will return true if the otp was sent successfully.
				return ihs_send_otp_via_otp_route(
					$otp_length, $auth_key, $message,
					$sender_id, $mob_number_with_country_code, $otp_pin );
			}
		}

	}
}

if ( ! function_exists( 'ihs_send_otp_via_twilio' ) ) {
	/**
	 * Send the OTP via twilio api
	 *
	 * Mobile Number $mob_number is without country code and country code $country_code is without plus sign.
	 *
	 * @param {int}    $mob_number Mobile number.
	 * @param {int}    $country_code Country Code.
	 * @param {string}    $message Text Message
	 *
	 * @return {boolean} Returns true if the message sent successfully
	 */
	function ihs_send_otp_via_twilio( $mob_number, $country_code, $message ) {
		$api_key = ( get_option( 'ihs_twilio_api_key' ) ) ? get_option( 'ihs_twilio_api_key' ) : '';

		if ( ! $api_key ) {
			return;
		}

		$url = "https://api.authy.com/protected/json/phones/verification/start";
		$response = wp_remote_post( $url, array(
				'method' => 'POST',
				'timeout' => 30,
				'redirection' => 10,
				'httpversion' => '1.1',
				'blocking' => true,
				'headers' => array(),
				'body' => array(
					'api_key' => $api_key,
					'via' => 'sms',
					'phone_number' => $mob_number,
					'country_code' => $country_code
				),
				'cookies' => array()
			)
		);

		$decoded_response = json_decode( $response['body'] );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return false;
		} else if ( ('OK' === $response['response']['message'] ) && $decoded_response->success ) {
			return true;
		}
	}
}

if ( ! function_exists( 'ihs_handle_entered_otp_verification_twilio' ) ) {
	/**
	 * Verify if the OTP entered is correct via twilio api
	 *
	 * Mobile Number $mob_number is without country code and country code $country_code is without plus sign.
	 *
	 */
	function ihs_handle_entered_otp_verification_twilio() {

		if ( isset( $_POST['security'] ) ) {
			$nonce_val = esc_html( wp_unslash( $_POST['security'] ) );
		}

		if ( ! wp_verify_nonce( $nonce_val, 'ihs_otp_nonce_action_name' ) ) {
			wp_die();
		}

		$mob_number = $_POST['data']['mob'];
		$country_code = $_POST['data']['country_code'];
		$otp_entered = $_POST['data']['otp_entered'];

		$response = ihs_verify_entered_otp_twilio( $mob_number, $country_code, $otp_entered );

		wp_send_json_success(
			array(
				'response' => $response,
				'data_recieved_from_js'    => $_POST,
			)
		);
	}
	add_action( 'wp_ajax_ihs_verify_twilio', 'ihs_handle_entered_otp_verification_twilio' );
	add_action( 'wp_ajax_nopriv_ihs_verify_twilio', 'ihs_handle_entered_otp_verification_twilio' );

}

if ( ! function_exists( 'ihs_verify_entered_otp_twilio' ) ) {
	/**
	 * Verify if the OTP entered is correct via twilio api
	 *
	 * Mobile Number $mob_number is without country code and country code $country_code is without plus sign.
	 *
	 * @param {string}    $mob_number Mobile number.
	 * @param {string}    $country_code Country Code.
	 * @param {string}    $otp_entered Otp entered
	 *
	 * @return {boolean} Returns true if the message sent successfully
	 */
	function ihs_verify_entered_otp_twilio( $mob_number, $country_code, $otp_entered ) {
		$api_key = ( get_option( 'ihs_twilio_api_key' ) ) ? get_option( 'ihs_twilio_api_key' ) : '';

		if ( ! $api_key ) {
			return;
		}

		$url = "https://api.authy.com/protected/json/phones/verification/check";
		$response = wp_remote_post( $url, array(
				'method' => 'GET',
				'timeout' => 30,
				'redirection' => 10,
				'httpversion' => '1.1',
				'blocking' => true,
				'headers' => array(
					'X-Authy-Api-Key'=> $api_key
				),
				'body' => array(
					'phone_number' => $mob_number,
					'country_code' => $country_code,
					'verification_code' => $otp_entered
				),
				'cookies' => array(),
			)
		);
		$decoded_response = json_decode( $response['body'] );

		if ( is_wp_error( $response ) || ! $decoded_response->success ) {
			$error_message = $decoded_response->message;
			return array(
				'error_code' => $decoded_response->error_code,
				'error_message' => $error_message,
				'success' => false
			);
		} else if ( $decoded_response->success ) {
			// If no error
			return array(
				'error_code' => '',
				'error_message' => '',
				'success' => true
			);
		}

	}
}

if ( ! function_exists( 'ihs_send_otp_via_otp_route' ) ) {
	/**
	 * Send OTP via OTP route.
	 *
	 * @param $otp_length
	 * @param $auth_key
	 * @param $message
	 * @param $sender_id
	 * @param $mob_number
	 * @param $otp_pin
	 *
	 * @return bool
	 */
	function ihs_send_otp_via_otp_route( $otp_length, $auth_key, $message,
		$sender_id, $mob_number, $otp_pin ) {
		$url = "http://control.msg91.com/api/sendotp.php?otp_length=$otp_length&authkey=$auth_key&message=$message&sender=$sender_id&mobile=$mob_number&otp=$otp_pin";
		$response = wp_remote_post( $url, array(
				'method' => 'POST',
				'timeout' => 30,
				'redirection' => 10,
				'httpversion' => '1.1',
				'blocking' => true,
				'headers' => array(),
				'body' => array(),
				'cookies' => array()
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
//			echo "Something went wrong: $error_message";
			return false;
		} else if ( 'OK' === $response['response']['message'] ) {
			return true;
		}
	}
}

if ( ! function_exists( 'ihs_otp_send_new_pass' ) ) {
	/**
	 * Send New Password.
	 */
	function ihs_otp_send_new_pass() {
		if ( isset( $_POST['security'] ) ) {
			$nonce_val = sanitize_text_field( wp_unslash( $_POST['security'] ) );
		}

		if ( ! wp_verify_nonce( $nonce_val, 'ihs_otp_nonce_reset_pass' ) ) {
			wp_die();
		}
		$mobile_number = $_POST['data']['mob'];
		$country_code_from_form = $_POST['data']['country_code'];
		$country_code_from_form = str_replace( '+', '', $country_code_from_form );
		$mobile_number = ( isset( $mobile_number ) && is_numeric( $mobile_number ) ) ? wp_unslash( $mobile_number ) : '';
		$mobile_number = absint( $mobile_number );
		$meta_key = get_option( 'ihs_otp_mob_meta_key' );
		$meta_key = sanitize_text_field( $meta_key );
		$message_template = get_option( 'ihs_otp_reset_template' );
		$api_used =  ( get_option( 'ihs_api_type' ) ) ? get_option( 'ihs_api_type' ) : 'otp';

		$country_code_prefix = get_option( 'ihs_otp_mob_country_code' );
		$is_saved_with_country_code = get_option( 'ihs_no_saved_with_country' );
		$new_password = mt_rand( 100000, 500000 );

		if ( 'msg91' === $api_used ) {
			$response = ihs_send_otp( $mobile_number, $country_code_from_form, $new_password, $message_template, '' );
		} else if ( 'twilio' === $api_used ){
			$twilio_reset_pass_msg = str_replace( '{OTP}', $new_password, $message_template );
			$mob_with_country_code = '+' . $country_code_from_form . $mobile_number;
			$response =  ihs_reset_password_sms_by_twilio( $twilio_reset_pass_msg, $mob_with_country_code );
		}
		$database_mob_number = '';

		if ( ( 'Yes' === $is_saved_with_country_code ) && $response ) {
			$database_mob_number = '+' . $country_code_from_form . $mobile_number;
		} else if ( 'No' === $is_saved_with_country_code && $response ){
			$database_mob_number = $mobile_number;
		}
		$args = array(
			'meta_key' => $meta_key,
			'meta_value' => $database_mob_number,
		);

		$user_obj = get_users( $args );
		$user_id = $user_obj[0]->data->ID;

		/**
		 * If response is true which means message was sent and,
		 * If user exists update the new password for him.
		 */
		if ( $response && $user_id ) {
			wp_set_password( $new_password, $user_id );
			$is_otp_send = true;
		} else {
			$is_otp_send = false;
			$new_password = '';
		}

		wp_send_json_success(
			array(
				'otp_pin_sent_to_js' => true,
				'data_recieved_from_js'    => $_POST,
				'user_id' => $user_id,
				'mobile_no' => strval( $mobile_number ),
				'country_from_form' => $country_code_from_form,
				'msg' => $message_template,
				'api' => $api_used,
			)
		);
	}

	add_action( 'wp_ajax_ihs_otp_reset_ajax_hook', 'ihs_otp_send_new_pass' );
	add_action( 'wp_ajax_nopriv_ihs_otp_reset_ajax_hook', 'ihs_otp_send_new_pass' );
}

if ( ! function_exists( 'ihs_reset_password_sms_by_twilio' ) ) {
	/**
	 * Send Reset Password SMS using TWILIO route.
	 *
	 * @param {String} $message Message.
	 * @param {String} $phone Phone No.
	 *
	 * @return bool
	 */
	function ihs_reset_password_sms_by_twilio( $message, $phone ) {

		// Your Account SID and Auth Token from twilio.com/console
		$tw_sid_key = get_option( 'ihs_twilio_sid_key' );
		$tw_auth_token = get_option( 'ihs_twilio_auth_token' );
		$tw_phone_number = get_option( 'ihs_twilio_phone_number' );
		$sid = ( ! empty( $tw_sid_key ) ) ? $tw_sid_key : '';
		$token = ( ! empty( $tw_auth_token ) ) ? $tw_auth_token : '';
		$twilio_mob_no = ( ! empty( $tw_phone_number ) ) ? $tw_phone_number : '';

		echo $sid . ' ' . $token . ' ' . $twilio_mob_no . ' ' . $message . ' ' . $phone;

		$my_ihs_class = new IHS_Send_Programmable_SMS();
		$my_ihs_class->ihs_send_msg_using_twilio( $sid, $token, $phone, $twilio_mob_no, $message );
		return true;
	}
}
