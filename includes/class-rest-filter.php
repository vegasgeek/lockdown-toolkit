<?php
/**
 * REST Hider REST Filter Class
 *
 * Filters REST endpoints to hide those configured by the admin
 *
 * @package RestHider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class REST_Hider_REST_Filter
 */
class REST_Hider_REST_Filter {

	/**
	 * Option key for hidden endpoints
	 */
	const HIDDEN_ENDPOINTS_OPTION_KEY = 'rest_hider_hidden_endpoints';

	/**
	 * Initialize the REST filter
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'filter_rest_request' ), 10, 3 );
	}

	/**
	 * Filter REST requests to hide configured endpoints
	 *
	 * @param mixed            $result   The dispatch result. Usually a WP_REST_Response or WP_Error.
	 * @param WP_REST_Server   $server   The REST server instance.
	 * @param WP_REST_Request  $request  The REST request.
	 * @return mixed
	 */
	public static function filter_rest_request( $result, $server, $request ) {
		$requested_route = $request->get_route();
		$hidden_endpoints = self::get_hidden_endpoints();

		// Check if this endpoint is hidden.
		if ( isset( $hidden_endpoints[ $requested_route ] ) && $hidden_endpoints[ $requested_route ] ) {
			return new WP_Error(
				'rest_hider_forbidden',
				__( 'This REST endpoint is not available.', 'resthider' ),
				array( 'status' => 403 )
			);
		}

		return $result;
	}

	/**
	 * Get hidden endpoints from database
	 *
	 * @return array
	 */
	public static function get_hidden_endpoints() {
		$hidden_endpoints = get_option( self::HIDDEN_ENDPOINTS_OPTION_KEY, array() );
		return apply_filters( 'lockdown_toolkit_hidden_endpoints', $hidden_endpoints );
	}

	/**
	 * Set endpoint hidden status
	 *
	 * @param string $route  The endpoint route.
	 * @param bool   $hidden Whether the endpoint should be hidden.
	 * @return bool
	 */
	public static function set_endpoint_hidden( $route, $hidden ) {
		$hidden_endpoints = self::get_hidden_endpoints();
		if ( $hidden ) {
			$hidden_endpoints[ $route ] = true;
		} else {
			unset( $hidden_endpoints[ $route ] );
		}
		return update_option( self::HIDDEN_ENDPOINTS_OPTION_KEY, $hidden_endpoints );
	}
}
