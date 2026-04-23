<?php
/**
 * Lockdown Tools Hidden Login Class
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

	const LOGIN_PAGE_URL_OPTION = 'lockdown_tools_login_page_url';
	const REDIRECT_URL_OPTION   = 'lockdown_tools_redirect_url';
	const HIDE_DASHBOARD_OPTION = 'lockdown_tools_hide_dashboard';

	/**
	 * Whether the original request was for wp-login.php (now blocked).
	 *
	 * @var bool
	 */
	private static $wp_login_php = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_settings_fields' ) );

		// Intercept before WordPress routes the request.
		add_action( 'plugins_loaded', array( __CLASS__, 'intercept_request' ), 9999 );

		add_action( 'setup_theme', array( __CLASS__, 'setup_theme' ), 1 );
		add_action( 'init', array( __CLASS__, 'block_signup_activate' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'wp_loaded' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_export_data' ) );

		add_filter( 'site_url', array( __CLASS__, 'filter_site_url' ), 10, 4 );
		add_filter( 'network_site_url', array( __CLASS__, 'filter_network_site_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( __CLASS__, 'filter_wp_redirect' ), 10, 2 );
		add_filter( 'login_url', array( __CLASS__, 'filter_login_url' ), 10, 3 );
		add_filter( 'site_status_tests', array( __CLASS__, 'remove_loopback_test' ) );

		// Prevent WordPress from redirecting /wp-admin/ to wp-login.php, which exposes the real login URL.
		if ( self::hide_dashboard() ) {
			remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
		}
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	/**
	 * Register options with the Settings API.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting( 'general', self::LOGIN_PAGE_URL_OPTION, array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_slug' ),
			'show_in_rest'      => false,
		) );

		register_setting( 'general', self::REDIRECT_URL_OPTION, array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_slug' ),
			'show_in_rest'      => false,
		) );

		register_setting( 'general', self::HIDE_DASHBOARD_OPTION, array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'show_in_rest'      => false,
			'default'           => 1,
		) );
	}

	/**
	 * Add settings section and fields to Settings > General.
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

		add_settings_field(
			self::HIDE_DASHBOARD_OPTION,
			__( 'Hide Dashboard', 'lockdown-toolkit' ),
			array( __CLASS__, 'hide_dashboard_field' ),
			'general',
			'lockdown_toolkit_hide_login'
		);
	}

	/**
	 * @return void
	 */
	public static function section_callback() {
		echo wp_kses_post( __( 'Configure a custom login page location and redirect unauthorized login attempts.', 'lockdown-toolkit' ) );
	}

	/**
	 * @return void
	 */
	public static function login_page_url_field() {
		$value    = get_option( self::LOGIN_PAGE_URL_OPTION );
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
	 * @return void
	 */
	public static function redirect_url_field() {
		$value    = get_option( self::REDIRECT_URL_OPTION );
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
	 * @return void
	 */
	public static function hide_dashboard_field() {
		$value = get_option( self::HIDE_DASHBOARD_OPTION, true );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::HIDE_DASHBOARD_OPTION ); ?>" value="1" <?php checked( 1, $value ); ?> />
			<?php esc_html_e( 'Redirect unauthenticated /wp-admin/ requests to the Redirect URL above.', 'lockdown-toolkit' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'When unchecked, unauthenticated /wp-admin/ requests are sent to your custom login page instead.', 'lockdown-toolkit' ); ?></p>
		<?php
	}

	/**
	 * Strip a slug value of slashes, query strings, and fragments.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_slug( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$value = trim( $value, '/' );
		$value = explode( '?', $value )[0];
		$value = explode( '#', $value )[0];
		return sanitize_text_field( $value );
	}

	// -------------------------------------------------------------------------
	// URL helpers
	// -------------------------------------------------------------------------

	/**
	 * @return string
	 */
	private static function login_slug() {
		return get_option( self::LOGIN_PAGE_URL_OPTION, '' );
	}

	/**
	 * @return string
	 */
	private static function redirect_slug() {
		return get_option( self::REDIRECT_URL_OPTION, '' );
	}

	/**
	 * @return bool
	 */
	private static function hide_dashboard() {
		return (bool) get_option( self::HIDE_DASHBOARD_OPTION, true );
	}

	/**
	 * @return bool
	 */
	private static function use_trailing_slashes() {
		return '/' === substr( get_option( 'permalink_structure' ), -1, 1 );
	}

	/**
	 * Add or remove trailing slash to match the site's permalink structure.
	 *
	 * @param string $string
	 * @return string
	 */
	private static function maybe_slash( $string ) {
		return self::use_trailing_slashes() ? trailingslashit( $string ) : untrailingslashit( $string );
	}

	/**
	 * Full URL to the custom login page, or null if the slug is not configured.
	 *
	 * @param string|null $scheme
	 * @return string|null
	 */
	private static function login_url( $scheme = null ) {
		$slug = self::login_slug();
		if ( empty( $slug ) ) {
			return null;
		}
		$base = home_url( '/', $scheme );
		if ( get_option( 'permalink_structure' ) ) {
			return self::maybe_slash( $base . $slug );
		}
		return $base . '?' . $slug;
	}

	/**
	 * Full URL for blocked-access redirects.
	 *
	 * @param string|null $scheme
	 * @return string
	 */
	private static function redirect_url( $scheme = null ) {
		$slug = self::redirect_slug();
		if ( empty( $slug ) ) {
			return home_url( '/', $scheme );
		}
		if ( get_option( 'permalink_structure' ) ) {
			return self::maybe_slash( home_url( '/', $scheme ) . $slug );
		}
		return home_url( '/', $scheme ) . '?' . $slug;
	}

	// -------------------------------------------------------------------------
	// Request interception
	// -------------------------------------------------------------------------

	/**
	 * Intercept the request before WordPress routes it.
	 * Runs at plugins_loaded priority 9999.
	 *
	 * @return void
	 */
	public static function intercept_request() {
		if ( empty( self::login_slug() ) ) {
			return;
		}

		global $pagenow;

		$request = wp_parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

		$is_login_php = strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-login.php' ) !== false
			|| ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-login', 'relative' ) );

		$is_register_php = strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-register.php' ) !== false
			|| ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-register', 'relative' ) );

		if ( ( $is_login_php || $is_register_php ) && ! is_admin() ) {
			self::$wp_login_php     = true;
			$_SERVER['REQUEST_URI'] = self::maybe_slash( '/' . str_repeat( '-/', 10 ) );
			$pagenow                = 'index.php';

		} elseif (
			( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === home_url( self::login_slug(), 'relative' ) )
			|| ( ! get_option( 'permalink_structure' ) && isset( $_GET[ self::login_slug() ] ) && empty( $_GET[ self::login_slug() ] ) )
		) {
			$pagenow = 'wp-login.php';
		}
	}

	// -------------------------------------------------------------------------
	// Login page serving and admin redirect
	// -------------------------------------------------------------------------

	/**
	 * Serve the login page or redirect unauthenticated admin access.
	 * Runs on wp_loaded.
	 *
	 * @return void
	 */
	public static function wp_loaded() {
		if ( empty( self::login_slug() ) ) {
			return;
		}

		global $pagenow;

		$request = wp_parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

		if (
			self::hide_dashboard()
			&& is_admin()
			&& ! is_user_logged_in()
			&& ! defined( 'WP_CLI' )
			&& ! defined( 'DOING_AJAX' )
			&& ! defined( 'DOING_CRON' )
			&& 'admin-post.php' !== $pagenow
			&& ( ! isset( $request['path'] ) || '/wp-admin/options.php' !== $request['path'] )
		) {
			wp_safe_redirect( self::redirect_url() );
			die();
		}

		if ( self::$wp_login_php ) {
			self::load_theme_template();
		} elseif ( 'wp-login.php' === $pagenow ) {
			// Redirect trailing-slash mismatch.
			if (
				isset( $request['path'] )
				&& $request['path'] !== self::maybe_slash( $request['path'] )
				&& get_option( 'permalink_structure' )
			) {
				wp_safe_redirect(
					self::maybe_slash( self::login_url() )
					. ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '' )
				);
				die();
			}

			if ( is_user_logged_in() && ! isset( $_REQUEST['action'] ) ) {
				wp_safe_redirect( admin_url() );
				die();
			}

			global $error, $interim_login, $action, $user_login;
			require_once ABSPATH . 'wp-login.php';
			die();
		}
	}

	/**
	 * Load the active theme's template to serve the 404 page.
	 *
	 * @return void
	 */
	private static function load_theme_template() {
		global $pagenow;
		$pagenow = 'index.php';

		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', true );
		}

		wp();
		require_once ABSPATH . WPINC . '/template-loader.php';
		die();
	}

	// -------------------------------------------------------------------------
	// URL filters
	// -------------------------------------------------------------------------

	/**
	 * Replace any wp-login.php URL with the custom login URL.
	 *
	 * @param string      $url    URL to check.
	 * @param string|null $scheme URL scheme.
	 * @return string
	 */
	private static function replace_login_url( $url, $scheme = null ) {
		if ( empty( self::login_slug() ) ) {
			return $url;
		}

		// Password-protected post forms use this action legitimately.
		if ( strpos( $url, 'wp-login.php?action=postpass' ) !== false ) {
			return $url;
		}

		if ( strpos( $url, 'wp-login.php' ) !== false && strpos( (string) wp_get_referer(), 'wp-login.php' ) === false ) {
			if ( is_ssl() ) {
				$scheme = 'https';
			}

			$args = explode( '?', $url );

			if ( isset( $args[1] ) ) {
				parse_str( $args[1], $args );
				if ( isset( $args['login'] ) ) {
					$args['login'] = rawurlencode( $args['login'] );
				}
				$url = add_query_arg( $args, self::login_url( $scheme ) );
			} else {
				$url = self::login_url( $scheme );
			}
		}

		return $url;
	}

	/**
	 * @param string      $url
	 * @param string      $path
	 * @param string|null $scheme
	 * @param int|null    $blog_id
	 * @return string
	 */
	public static function filter_site_url( $url, $path, $scheme, $blog_id ) {
		return self::replace_login_url( $url, $scheme );
	}

	/**
	 * @param string      $url
	 * @param string      $path
	 * @param string|null $scheme
	 * @return string
	 */
	public static function filter_network_site_url( $url, $path, $scheme ) {
		return self::replace_login_url( $url, $scheme );
	}

	/**
	 * @param string $location
	 * @param int    $status
	 * @return string
	 */
	public static function filter_wp_redirect( $location, $status ) {
		// Leave Jetpack/WordPress.com SSO redirects alone.
		if ( strpos( $location, 'https://wordpress.com/wp-login.php' ) !== false ) {
			return $location;
		}
		return self::replace_login_url( $location );
	}

	/**
	 * @param string $login_url
	 * @param string $redirect
	 * @param bool   $force_reauth
	 * @return string
	 */
	public static function filter_login_url( $login_url, $redirect, $force_reauth ) {
		// Prevents a broken anchor on 404 pages that render a login link.
		if ( is_404() ) {
			return '#';
		}
		return $login_url;
	}

	// -------------------------------------------------------------------------
	// Security extras
	// -------------------------------------------------------------------------

	/**
	 * Block unauthenticated Customizer access.
	 *
	 * @return void
	 */
	public static function setup_theme() {
		global $pagenow;
		if ( ! is_user_logged_in() && 'customize.php' === $pagenow ) {
			wp_die( __( 'This has been disabled.', 'lockdown-toolkit' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Block wp-signup.php and wp-activate.php on single-site installs.
	 *
	 * @return void
	 */
	public static function block_signup_activate() {
		if ( empty( self::login_slug() ) ) {
			return;
		}
		if (
			! is_multisite()
			&& (
				strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-signup' ) !== false
				|| strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-activate' ) !== false
			)
		) {
			wp_die( __( 'This feature is not enabled.', 'lockdown-toolkit' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Remove the loopback requests test from Site Health.
	 * The request interception mangles the URI during the loopback check, causing a false positive.
	 *
	 * @param array $tests
	 * @return array
	 */
	public static function remove_loopback_test( $tests ) {
		unset( $tests['async']['loopback_requests'] );
		return $tests;
	}

	/**
	 * Redirect GDPR data export confirmation URLs through the custom login slug.
	 *
	 * @return void
	 */
	public static function redirect_export_data() {
		if ( empty( self::login_slug() ) ) {
			return;
		}
		if (
			! empty( $_GET )
			&& isset( $_GET['action'] ) && 'confirmaction' === $_GET['action']
			&& isset( $_GET['request_id'] )
			&& isset( $_GET['confirm_key'] )
		) {
			$request_id = (int) $_GET['request_id'];
			$key        = sanitize_text_field( wp_unslash( $_GET['confirm_key'] ) );
			$result     = wp_validate_user_request_key( $request_id, $key );
			if ( ! is_wp_error( $result ) ) {
				wp_safe_redirect( add_query_arg(
					array(
						'action'      => 'confirmaction',
						'request_id'  => $request_id,
						'confirm_key' => $key,
					),
					self::login_url()
				) );
				exit();
			}
		}
	}
}
