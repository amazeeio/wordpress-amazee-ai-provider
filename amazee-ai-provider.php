<?php
/**
 * Plugin Name: AI Provider for amazee.ai
 * Plugin URI: https://github.com/amazeeio/wordpress-amazee-ai-provider
 * Description: Adds amazee.ai AI hosting to the available AI providers
 * Version: 1.1
 * Author: amazee.ai
 * Author URI: https://amazee.ai/
 * License: GPL-2.0-or-later
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
				__( 'The AI Provider for amazee.ai plugin requires WordPress %1$s or newer. Current version: %2$s.', 'amazee-ai-provider' ),
				MINIMUM_WP_VERSION,
				'<code>' . esc_html( wp_get_wp_version() ) . '</code>'
			)
		);
		return;
	}

	if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
		queue_admin_error_notice(
			__( 'The AI Provider for amazee.ai plugin requires the WordPress AI client available in WordPress 7.0 and newer.', 'amazee-ai-provider' )
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
				__( 'Your installation of the amazee.ai AI provider plugin is incomplete. Please run %1$s in the %2$s directory.', 'amazee-ai-provider' ),
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
			esc_html__( 'Settings', 'amazee-ai-provider' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
);

add_action(
	'admin_init',
	function () {
		// Register individual settings.
		register_setting(
			'wp-ai-client-settings',
			'wp_ai_client_amazee_endpoint_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_setting(
			'wp-ai-client-settings',
			'wp_ai_client_amazee_llm_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Add custom settings fields under the core Connectors credentials section.
		add_settings_field(
			'wp-ai-client-amazee-endpoint-url',
			__( 'amazee.ai ENDPOINT_URL', 'amazee-ai-provider' ),
			function () {
				$is_constant_defined = defined( 'AMAZEE_ENDPOINT_URL' );
				$value               = defined( 'AMAZEE_ENDPOINT_URL' ) ? AMAZEE_ENDPOINT_URL : get_option( 'wp_ai_client_amazee_endpoint_url', '' );
				?>
				<input
					type="url"
					id="wp_ai_client_amazee_endpoint_url"
					name="wp_ai_client_amazee_endpoint_url"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					placeholder="https://llm.us103.amazee.ai/v1"
					<?php disabled( $is_constant_defined ); ?>
				>
				<p class="description">
					<?php
					if ( $is_constant_defined ) {
						esc_html_e( 'Configured via AMAZEE_ENDPOINT_URL constant in wp-config.php.', 'amazee-ai-provider' );
					} else {
						esc_html_e( 'The endpoint URL for your amazee.ai region.', 'amazee-ai-provider' );
					}
					?>
				</p>
				<?php
			},
			'wp-ai-client',
			'wp-ai-client-provider-credentials'
		);

		add_settings_field(
			'wp-ai-client-amazee-llm-token',
			__( 'amazee.ai LLM_TOKEN', 'amazee-ai-provider' ),
			function () {
				$is_constant_defined = defined( 'AMAZEE_LLM_TOKEN' );
				$value               = $is_constant_defined ? '••••••••••••••••' : get_option( 'wp_ai_client_amazee_llm_token', '' );
				?>
				<input
					type="password"
					id="wp_ai_client_amazee_llm_token"
					name="wp_ai_client_amazee_llm_token"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					<?php disabled( $is_constant_defined ); ?>
				>
				<p class="description">
					<?php
					if ( $is_constant_defined ) {
						esc_html_e( 'Configured via AMAZEE_LLM_TOKEN constant in wp-config.php.', 'amazee-ai-provider' );
					} else {
						printf(
							/* translators: %s: Link to my.amazee.io */
							wp_kses(
								__( 'Your amazee.ai LLM token. You can find or create this in the <a href="%s" target="_blank" rel="noopener noreferrer">amazee.ai dashboard<span class="screen-reader-text"> (opens in a new tab)</span></a>.', 'amazee-ai-provider' ),
								array(
									'a'    => array(
										'href'   => array(),
										'target' => array(),
										'rel'    => array(),
									),
									'span' => array(
										'class' => array(),
									),
								)
							),
							'https://my.amazee.io'
						);
					}
					?>
				</p>
				<?php
			},
			'wp-ai-client',
			'wp-ai-client-provider-credentials'
		);
	}
);
