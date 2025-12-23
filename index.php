<?php
/**
 * Plugin Name: Lockdown Toolkit by VegasGeek
 * Plugin URI: https://vegasgeek.com
 * Description: A suite of tools to harden and protect your WordPress site
 * Version: 1.0.4
 * Author: VegasGeek
 * Author URI: https://vegasgeek.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lockdown-toolkit
 *
 * @package LockdownToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'LOCKDOWN_TOOLKIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOCKDOWN_TOOLKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LOCKDOWN_TOOLKIT_VERSION', '1.0.4' );

// Include necessary files.
require_once LOCKDOWN_TOOLKIT_PLUGIN_DIR . 'includes/class-rest-filter.php';
require_once LOCKDOWN_TOOLKIT_PLUGIN_DIR . 'includes/class-hidden-login.php';

/**
 * Initialize the Lockdown Toolkit plugin
 */
function lockdown_toolkit_init() {
	// Initialize the REST filter to block hidden endpoints.
	REST_Hider_REST_Filter::init();

	// Initialize the hidden login functionality.
	Lockdown_Toolkit_Hidden_Login::init();
}

// Hook initialization to WordPress.
add_action( 'plugins_loaded', 'lockdown_toolkit_init' );

/**
 * On plugin activation, hide sensitive endpoints
 */
function lockdown_toolkit_on_activation() {
	// Hide sensitive endpoints.
	$hidden_endpoints = array(
		'/wp/v2/users' => true,
		'/wp/v2/media' => true,
	);

	update_option( REST_Hider_REST_Filter::HIDDEN_ENDPOINTS_OPTION_KEY, $hidden_endpoints );
}

register_activation_hook( __FILE__, 'lockdown_toolkit_on_activation' );

// Check for plugin updates.
require 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/vegasgeek/lockdown-toolkit/',
	__FILE__,
	'lockdown-toolkit'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );
