<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Core\Helpers;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * Base class for the plugin's REST endpoints.
 *
 * A route registers itself: subclassing this and declaring `$route` is all it
 * takes, because the class is discovered and instantiated automatically.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
abstract class Abstract_Route {

	/**
	 * Route path, including the leading slash.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '';

	/**
	 * Allowed HTTP method or comma-separated method list.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $methods = 'GET';

	/**
	 * REST argument schema.
	 *
	 * @since 6.0.0
	 * @var array<string,mixed>
	 */
	protected $args = array();


	/**
	 * Register the route when WordPress builds its REST endpoints.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( $this->should_register() ) {
			add_action( 'rest_api_init', array( $this, 'register_route' ) );
		}
	}


	/**
	 * Decide whether this route should exist on this site.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	protected function should_register() {
		return true;
	}


	/**
	 * Register the route.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function register_route() {
		register_rest_route( WC_CUSTOM_INSTALLMENTS_REST, $this->route, array(
			'methods'             => $this->methods,
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => array( $this, 'permission' ),
			'args'                => $this->args,
		) );
	}


	/**
	 * Default permission check.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return bool
	 */
	public function permission( WP_REST_Request $request ) {
		return current_user_can( Helpers::capability() );
	}


	/**
	 * Handle the request.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return mixed
	 */
	abstract public function handle( WP_REST_Request $request );


	/**
	 * Build a standard success response.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $data | Pairs merged into the response body
	 * @return WP_REST_Response
	 */
	protected function success_response( array $data = array() ) {
		return rest_ensure_response( array_merge( array( 'status' => 'success' ), $data ) );
	}


	/**
	 * Build a standard error response.
	 *
	 * @since 6.0.0
	 * @param string $message | Human-readable description, already escaped
	 * @param array<string,mixed> $data | Additional pairs
	 * @return WP_REST_Response
	 */
	protected function error_response( $message, array $data = array() ) {
		return rest_ensure_response( array_merge( array(
			'status'  => 'error',
			'message' => $message,
		), $data ) );
	}
}
