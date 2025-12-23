<?php
/**
 * Lockdown Tools Hidden Login Class
 *
 * Allows admins to hide the WordPress login page and redirect
 * wp-login.php requests to a custom location
 *
 * @package LockdownTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lockdown_Toolkit_Hidden_Login
 */
class Lockdown_Toolkit_Hidden_Login {

	/**
	 * Option key for login page URL
	 */
	const LOGIN_PAGE_URL_OPTION = 'lockdown_tools_login_page_url';

	/**
	 * Option key for redirect URL
	 */
	const REDIRECT_URL_OPTION = 'lockdown_tools_redirect_url';

	/**
	 * Initialize the hidden login functionality
	 *
	 * @return void
	 */
	public static function init() {
		// Register settings page fields
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_settings_fields' ) );

		// Handle wp-login.php redirects
		add_action( 'init', array( __CLASS__, 'handle_login_redirect' ), 1 );

		// Handle custom login page
		add_action( 'init', array( __CLASS__, 'handle_custom_login_page' ), 1 );

		// Filter password reset emails to use custom login URL
		add_filter( 'retrieve_password_message', array( __CLASS__, 'filter_password_reset_message' ), 10, 4 );

		// Filter site_url to replace wp-login.php URLs with custom login URL
		add_filter( 'site_url', array( __CLASS__, 'filter_site_url' ), 10, 4 );

		// Filter login URL to use custom login page
		add_filter( 'login_url', array( __CLASS__, 'filter_login_url' ), 10, 3 );

		// Hook after password reset to redirect to login page
		add_action( 'after_password_reset', array( __CLASS__, 'after_password_reset_redirect' ), 10, 2 );
	}

	/**
	 * Register settings for the General settings page
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting( 'general', self::LOGIN_PAGE_URL_OPTION, array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_login_page_url' ),
			'show_in_rest'      => false,
		) );

		register_setting( 'general', self::REDIRECT_URL_OPTION, array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_redirect_url' ),
			'show_in_rest'      => false,
		) );
	}

	/**
	 * Add settings fields to the General settings page
	 *
	 * @return void
	 */
	public static function add_settings_fields() {
		add_settings_section(
			'lockdown_toolkit_hide_login',
			__( 'Hide Login Page', 'lockdown-toolkit' ),
			array( __CLASS__, 'section_callback' ),
			'general'
		);

		add_settings_field(
			self::LOGIN_PAGE_URL_OPTION,
			__( 'Login Page URL', 'lockdown-toolkit' ),
			array( __CLASS__, 'login_page_url_field' ),
			'general',
			'lockdown_toolkit_hide_login'
		);

		add_settings_field(
			self::REDIRECT_URL_OPTION,
			__( 'Redirect URL', 'lockdown-toolkit' ),
			array( __CLASS__, 'redirect_url_field' ),
			'general',
			'lockdown_toolkit_hide_login'
		);
	}

	/**
	 * Settings section callback
	 *
	 * @return void
	 */
	public static function section_callback() {
		echo wp_kses_post( __( 'Configure a custom login page location and redirect unauthorized login attempts.', 'lockdown-toolkit' ) );
	}

	/**
	 * Login page URL field callback
	 *
	 * @return void
	 */
	public static function login_page_url_field() {
		$value = get_option( self::LOGIN_PAGE_URL_OPTION );
		$site_url = home_url();
		?>
		<div style="display: flex; gap: 10px; align-items: center;">
			<span style="color: #666; font-size: 14px;"><?php echo esc_html( $site_url ); ?><strong>/</strong></span>
			<input type="text" name="<?php echo esc_attr( self::LOGIN_PAGE_URL_OPTION ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="my-login" style="width: 300px;" />
		</div>
		<p class="description"><?php esc_html_e( 'Enter the path where your login page will be accessible (e.g., my-login). Leave empty to disable.', 'lockdown-toolkit' ); ?></p>
		<?php
	}

	/**
	 * Redirect URL field callback
	 *
	 * @return void
	 */
	public static function redirect_url_field() {
		$value = get_option( self::REDIRECT_URL_OPTION );
		$site_url = home_url();
		?>
		<div style="display: flex; gap: 10px; align-items: center;">
			<span style="color: #666; font-size: 14px;"><?php echo esc_html( $site_url ); ?><strong>/</strong></span>
			<input type="text" name="<?php echo esc_attr( self::REDIRECT_URL_OPTION ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="404" style="width: 300px;" />
		</div>
		<p class="description"><?php esc_html_e( 'Enter the path where users should be redirected if they try to access wp-login.php directly (e.g., 404). Leave empty to redirect to the home page.', 'lockdown-toolkit' ); ?></p>
		<?php
	}

	/**
	 * Sanitize login page URL
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string
	 */
	public static function sanitize_login_page_url( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Remove leading and trailing slashes
		$value = trim( $value, '/' );

		// Remove any query strings or fragments
		$value = strtok( $value, '?' );
		$value = strtok( $value, '#' );

		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize redirect URL
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string
	 */
	public static function sanitize_redirect_url( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Remove leading and trailing slashes
		$value = trim( $value, '/' );

		// Remove any query strings or fragments
		$value = strtok( $value, '?' );
		$value = strtok( $value, '#' );

		return sanitize_text_field( $value );
	}

	/**
	 * Handle redirects from wp-login.php
	 *
	 * @return void
	 */
	public static function handle_login_redirect() {
		// Only run this on the frontend
		if ( is_admin() ) {
			return;
		}

		// Skip if user is already logged in
		if ( is_user_logged_in() ) {
			return;
		}

		$login_page_url = get_option( self::LOGIN_PAGE_URL_OPTION );

		// Only proceed if login page URL is set
		if ( empty( $login_page_url ) ) {
			return;
		}

		// Check if the current request is for wp-login.php
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// Match wp-login.php requests (with or without trailing slash, with or without query string)
		if ( preg_match( '#/wp-login\.php#i', $request_uri ) ) {
			// Get request method
			$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';

			// Get the raw query string without sanitizing (we'll sanitize individual params)
			$query_string = isset( $_SERVER['QUERY_STRING'] ) ? wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
			parse_str( $query_string, $query_params );

			// Check POST data for action as well (for form submissions)
			$post_action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

			// Allow password reset related actions to redirect to custom login page
			$allowed_actions = array( 'lostpassword', 'rp', 'resetpass' );
			$is_password_reset = ( isset( $query_params['action'] ) && in_array( $query_params['action'], $allowed_actions, true ) ) ||
			                      ( ! empty( $post_action ) && in_array( $post_action, $allowed_actions, true ) );

			// Also check for checkemail parameter (shown after requesting password reset)
			$has_checkemail = isset( $query_params['checkemail'] );

			if ( $is_password_reset || $has_checkemail ) {
				// For POST requests to password reset, let them through (don't redirect)
				if ( 'POST' === $request_method ) {
					return;
				}

				// For GET requests, redirect to custom login page with query parameters preserved
				$custom_login_url = home_url( '/' . $login_page_url );
				if ( ! empty( $query_string ) ) {
					$custom_login_url .= '?' . $query_string;
				}
				wp_redirect( $custom_login_url );
				exit;
			}

			// Skip other POST requests (form submissions like login)
			if ( 'POST' === $request_method ) {
				return;
			}

			// For all other wp-login.php requests, redirect to the configured redirect URL
			$redirect_path = get_option( self::REDIRECT_URL_OPTION );
			if ( empty( $redirect_path ) ) {
				$redirect_url = home_url();
			} else {
				$redirect_url = home_url( '/' . $redirect_path );
			}

			wp_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Handle custom login page requests
	 *
	 * @return void
	 */
	public static function handle_custom_login_page() {
		// Only run this on the frontend
		if ( is_admin() ) {
			return;
		}

		$login_page_url = get_option( self::LOGIN_PAGE_URL_OPTION );

		// Only proceed if option is set
		if ( empty( $login_page_url ) ) {
			return;
		}

		// Get the current request path
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// Parse the URL to get just the path
		$parsed_url = wp_parse_url( $request_uri );
		$request_path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';

		// Construct the login page URL with leading slash
		$login_url = '/' . $login_page_url;

		// Check if the current request matches the custom login page URL
		if ( $request_path === $login_url || $request_path === $login_url . '/' ) {
			// Load the WordPress login page using the standard login template
			// Do NOT modify $_SERVER['REQUEST_URI'] - WordPress needs the actual path for cookie handling
			// Note: wp-login.php will handle redirecting logged-in users appropriately
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
	}

	/**
	 * Filter password reset message to use custom login URL
	 *
	 * @param string  $message    Default password reset email message.
	 * @param string  $key        The activation key.
	 * @param string  $user_login The username for the user.
	 * @param WP_User $user_data  WP_User object.
	 * @return string Modified message with custom login URL.
	 */
	public static function filter_password_reset_message( $message, $key, $user_login, $user_data ) {
		$login_page_url = get_option( self::LOGIN_PAGE_URL_OPTION );

		// Only modify if custom login page is set
		if ( empty( $login_page_url ) ) {
			return $message;
		}

		// Get the custom login URL
		$custom_login_url = home_url( '/' . $login_page_url );

		// Replace all variations of wp-login.php URLs in the email
		$message = str_replace( site_url( 'wp-login.php' ), $custom_login_url, $message );
		$message = str_replace( network_site_url( 'wp-login.php', null, 'login' ), $custom_login_url, $message );
		$message = str_replace( home_url( 'wp-login.php' ), $custom_login_url, $message );

		return $message;
	}

	/**
	 * Filter site_url to replace wp-login.php with custom login URL
	 *
	 * @param string      $url     The complete site URL including scheme and path.
	 * @param string      $path    Path relative to the site URL.
	 * @param string|null $scheme  Scheme to give the site URL context.
	 * @param int|null    $blog_id The blog ID, or null for the current blog.
	 * @return string Modified URL with custom login page.
	 */
	public static function filter_site_url( $url, $path, $scheme, $blog_id ) {
		$login_page_url = get_option( self::LOGIN_PAGE_URL_OPTION );

		// Only modify if custom login page is set
		if ( empty( $login_page_url ) ) {
			return $url;
		}

		// Only modify wp-login.php URLs
		if ( strpos( $url, 'wp-login.php' ) !== false ) {
			// Replace wp-login.php with custom login URL
			$url = str_replace( 'wp-login.php', trim( $login_page_url, '/' ), $url );
		}

		return $url;
	}

	/**
	 * Filter login URL to use custom login page
	 *
	 * @param string $login_url    The login URL.
	 * @param string $redirect     The redirect URL.
	 * @param bool   $force_reauth Whether to force reauth.
	 * @return string Modified login URL with custom login page.
	 */
	public static function filter_login_url( $login_url, $redirect, $force_reauth ) {
		$login_page_url = get_option( self::LOGIN_PAGE_URL_OPTION );

		// Only modify if custom login page is set
		if ( empty( $login_page_url ) ) {
			return $login_url;
		}

		// Replace wp-login.php with custom login URL
		if ( strpos( $login_url, 'wp-login.php' ) !== false ) {
			$login_url = str_replace( 'wp-login.php', trim( $login_page_url, '/' ), $login_url );
		}

		return $login_url;
	}

	/**
	 * Redirect to login page after password reset instead of showing success message
	 *
	 * @param WP_User $user     The user whose password was reset.
	 * @param string  $new_pass The new password.
	 * @return void
	 */
	public static function after_password_reset_redirect( $user, $new_pass ) {
		$login_page_url = get_option( self::LOGIN_PAGE_URL_OPTION );

		// Only redirect if custom login page is set
		if ( empty( $login_page_url ) ) {
			return;
		}

		// Redirect to login page with reset=true parameter to show success message
		$redirect_url = home_url( '/' . $login_page_url . '?reset=true' );
		wp_redirect( $redirect_url );
		exit;
	}
}
