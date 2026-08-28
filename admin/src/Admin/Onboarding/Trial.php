<?php

namespace MeuMouse\WC_Custom_Installments\Admin\Onboarding;

use MeuMouse\WC_Custom_Installments\Core\Logger;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Ask the licensing service for a trial key.
 *
 * This is the one place the free plugin talks to anything outside the site, and
 * it only speaks when a person fills in the form and presses the button. There
 * is no call on activation, none on page load, and none if the step is skipped
 * — which is what lets the plugin be distributed on WordPress.org at all.
 *
 * The service decides the plan and the duration; nothing here chooses either.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin\Onboarding
 * @author MeuMouse.com
 */
class Trial {

	/**
	 * Endpoint that issues a trial licence.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ENDPOINT = '/v1/licenses/demo';

	/**
	 * How long a trial lasts, for the copy to state.
	 *
	 * The service is the authority; this is what the screen promises, and the
	 * two have to be configured to agree.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DAYS = 7;


	/**
	 * Whether the trial can be offered on this site.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_available() {
		if ( defined('WC_CUSTOM_INSTALLMENTS_PRO_VERSION') ) {
			return false;
		}

		return '' !== (string) self::product_id();
	}


	/**
	 * The product a trial is issued for.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function product_id() {
		return defined('WC_CUSTOM_INSTALLMENTS_PRO_PRODUCT_ID') ? (string) WC_CUSTOM_INSTALLMENTS_PRO_PRODUCT_ID : '';
	}


	/**
	 * Exactly what leaves the site, so the form can show it before sending.
	 *
	 * A consent notice that describes the request in prose is a promise. This
	 * is the request.
	 *
	 * @since 1.0.0
	 * @param array<string,string> $input | The details the person typed
	 * @return array<string,string>
	 */
	public static function preview( $input ) {
		return array(
			'productId' => self::product_id(),
			'firstName' => (string) ( $input['first_name'] ?? '' ),
			'lastName' => (string) ( $input['last_name'] ?? '' ),
			'email' => (string) ( $input['email'] ?? '' ),
			'phone' => (string) ( $input['phone'] ?? '' ),
		);
	}


	/**
	 * Request a trial licence.
	 *
	 * @since 1.0.0
	 * @param array<string,string> $input | The details the person typed
	 * @return array<string,mixed>|WP_Error
	 */
	public static function request( $input ) {
		if ( ! self::is_available() ) {
			return new WP_Error(
				'wcci_trial_unavailable',
				__( 'Trials are not available from this site right now.', 'wc-custom-installments' )
			);
		}

		$body = self::preview( $input );
		$missing = array_keys( array_filter( $body, static fn( $value ) => '' === trim( (string) $value ) ) );

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'wcci_trial_incomplete',
				__( 'Please fill in your name, e-mail and phone number.', 'wc-custom-installments' )
			);
		}

		if ( ! is_email( $body['email'] ) ) {
			return new WP_Error(
				'wcci_trial_invalid_email',
				__( 'That e-mail address does not look right.', 'wc-custom-installments' )
			);
		}

		$response = wp_remote_post( self::url(), array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			),
			'body' => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			Logger::warning( 'Trial request failed to reach the service.', array( 'error' => $response->get_error_message() ) );

			return new WP_Error(
				'wcci_trial_unreachable',
				__( 'We could not reach the licensing service. Please try again in a moment.', 'wc-custom-installments' )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$payload = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 409 === $status ) {
			return new WP_Error(
				'wcci_trial_exists',
				__( 'A trial has already been issued for that e-mail address. Check your inbox, or use another address.', 'wc-custom-installments' )
			);
		}

		if ( $status < 200 || $status >= 300 || ! is_array( $payload ) ) {
			Logger::warning( 'Trial request was refused.', array( 'status' => $status ) );

			return new WP_Error(
				'wcci_trial_refused',
				__( 'The licensing service could not issue a trial right now. Please try again later.', 'wc-custom-installments' )
			);
		}

		$data = $payload['data'] ?? $payload;

		return array(
			// The key is shown once and e-mailed; it is never stored here,
			// because the free plugin has nothing to do with a licence.
			'license_key' => (string) ( $data['licenseKey'] ?? $data['license_key'] ?? '' ),
			'expires_at' => (string) ( $data['expiresAt'] ?? $data['expires_at'] ?? '' ),
			'email' => $body['email'],
		);
	}


	/**
	 * The full endpoint URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private static function url() {
		return untrailingslashit( WC_CUSTOM_INSTALLMENTS_API_URL ) . self::ENDPOINT;
	}
}
