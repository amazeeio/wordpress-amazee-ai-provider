<?php
/**
 * Plugin Name: AI Provider for amazee.ai
 * Plugin URI: https://github.com/amazeeio/wordpress-amazee-ai-provider
 * Description: Adds amazee.ai AI hosting to the available AI providers
 * Version: 1.2
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: amazee.ai
 * Author URI: https://amazee.ai/
 * License: GPL-2.0-or-later
 * Text Domain: ai-provider-for-amazee-ai
 */

namespace Amazee\AiProvider;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MINIMUM_WP_VERSION = '7.0';

/**
 * Determines whether the current WordPress version is supported.
 *
 * Pre-release versions of the minimum WordPress version are accepted.
 *
 * @param string $version WordPress version.
 */
function is_supported_wordpress_version( string $version ): bool {
	return version_compare( $version, MINIMUM_WP_VERSION, '>=' )
		|| 0 === strpos( $version, MINIMUM_WP_VERSION . '-' );
}

/**
 * Queues an error notice for display in the admin.
 *
 * @param string $message Notice message. May contain `code` and `a` tags.
 */
function queue_admin_error_notice( string $message ): void {
	add_action(
		'admin_notices',
		static function () use ( $message ): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				wp_kses(
					$message,
					array(
						'code' => array(),
						'a'    => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				)
			);
		}
	);
}

/**
 * Verifies requirements, loads classes and schedules provider registration.
 *
 * Runs late on plugins_loaded so the AI client (WordPress core 7.0+) and any
 * site-wide Composer autoloader are available first.
 */
function bootstrap(): void {
	if ( ! is_supported_wordpress_version( wp_get_wp_version() ) ) {
		queue_admin_error_notice(
			sprintf(
				/* translators: %1$s: minimum WordPress version, %2$s: current WordPress version */
				__( 'The AI Provider for amazee.ai plugin requires WordPress %1$s or newer. Current version: %2$s.', 'ai-provider-for-amazee-ai' ),
				MINIMUM_WP_VERSION,
				'<code>' . esc_html( wp_get_wp_version() ) . '</code>'
			)
		);
		return;
	}

	if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
		queue_admin_error_notice(
			__( 'The AI Provider for amazee.ai plugin requires the WordPress AI client available in WordPress 7.0 and newer.', 'ai-provider-for-amazee-ai' )
		);
		return;
	}

	// The provider classes may already be autoloadable, e.g. on sites that
	// install this plugin via Composer and load a site-wide autoloader.
	// Otherwise fall back to the autoloader bundled with packaged releases.
	if ( ! class_exists( AmazeeIoAiProvider::class ) ) {
		$bundled_autoload = __DIR__ . '/vendor/autoload.php';
		if ( file_exists( $bundled_autoload ) ) {
			require_once $bundled_autoload;
		}
	}

	if ( ! class_exists( AmazeeIoAiProvider::class ) ) {
		queue_admin_error_notice(
			sprintf(
				/* translators: %1$s: composer install command, %2$s: plugin directory path */
				__( 'Your installation of the amazee.ai AI provider plugin is incomplete. Please run %1$s in the %2$s directory.', 'ai-provider-for-amazee-ai' ),
				'<code>composer install --no-dev</code>',
				'<code>' . esc_html( plugin_dir_path( __FILE__ ) ) . '</code>'
			)
		);
		return;
	}

	add_action( 'init', __NAMESPACE__ . '\\register_provider' );
}

/**
 * Registers the amazee.ai provider with the AI client registry.
 */
function register_provider(): void {
	$registry = \WordPress\AiClient\AiClient::defaultRegistry();
	if ( ! $registry->hasProvider( AmazeeIoAiProvider::class ) ) {
		$registry->registerProvider( AmazeeIoAiProvider::class );
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 20 );

/**
 * Add settings link to plugin actions.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( array $links ): array {
		$settings_page_url = 'options-connectors.php';

		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( $settings_page_url ),
			esc_html__( 'Settings', 'ai-provider-for-amazee-ai' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
);

// Credentials are managed by the WordPress core Connectors screen
// (Settings > Connectors), based on this provider's apiKey authentication
// metadata. The legacy wp_ai_client_amazee_* options are still read as a
// fallback for existing installs but no longer have their own UI.
