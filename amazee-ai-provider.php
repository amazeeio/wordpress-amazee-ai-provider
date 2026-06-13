<?php
/**
 * Plugin Name: AI Provider for amazee.ai
 * Plugin URI: https://github.com/amazeeio/wordpress-amazee-ai-provider
 * Description: Adds amazee.ai AI hosting to the available AI providers
 * Version: 1.0
 * Author: amazee.ai
 * Author URI: https://amazee.ai/
 * License: GPL-2.0-or-later
 */

namespace Amazee\AiProvider;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determines whether the current WordPress version is supported.
 *
 * This plugin supports WordPress 7.0+.
 *
 * @param string $version WordPress version.
 */
function is_supported_wordpress_version( string $version ): bool {
	return version_compare( $version, '7.0', '>=' ) || 0 === strpos( $version, '7.0-' );
}

/**
 * Display admin notice about unsupported WordPress version.
 */
function display_unsupported_wordpress_version_notice(): void {
	$allowed_html = array(
		'code' => array(),
	);
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: current WordPress version */
					__( 'The AI Provider for amazee.ai plugin requires WordPress 7.0 or newer. Current version: %s.', 'amazee-ai-provider' ),
					'<code>' . esc_html( wp_get_wp_version() ) . '</code>'
				),
				$allowed_html
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Display admin notice about missing required AI client.
 *
 * WordPress 7.0+ ships with the AI client in core. If the client class is still
 * unavailable, this notice informs the user about the missing dependency.
 */
function display_missing_ai_plugin_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'The AI Provider for amazee.ai plugin requires the WordPress AI client available in WordPress 7.0 and newer.', 'amazee-ai-provider' ); ?></p>
	</div>
	<?php
}

/**
 * Display admin notice about missing Composer dependencies.
 */
function display_composer_notice(): void {
	$allowed_html = array(
		'code' => array(),
	);
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %1$s: composer install command, %2$s: plugin directory path */
					__( 'Your installation of the amazee.ai AI provider plugin is incomplete. Please run %1$s in the %2$s directory.', 'amazee-ai-provider' ),
					'<code>composer install --no-dev</code>',
					'<code>' . esc_html( plugin_dir_path( __FILE__ ) ) . '</code>'
				),
				$allowed_html
			);
			?>
		</p>
	</div>
	<?php
}

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
	'plugins_loaded',
	function () {
		$wp_version = wp_get_wp_version();
		if ( ! is_supported_wordpress_version( $wp_version ) ) {
			add_action( 'admin_notices', __NAMESPACE__ . '\\display_unsupported_wordpress_version_notice' );
			return;
		}

		// This plugin requires the WordPress AI client, which is part of WordPress core
		// starting with WordPress 7.0. If unavailable, show an admin notice.
		if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
			add_action( 'admin_notices', __NAMESPACE__ . '\\display_missing_ai_plugin_notice' );
			return;
		}

		$my_autoload = __DIR__ . '/vendor/autoload.php';
		if ( ! file_exists( $my_autoload ) ) {
			add_action( 'admin_notices', __NAMESPACE__ . '\\display_composer_notice' );
			return;
		}

		require_once $my_autoload;
	},
	20
);

add_action(
	'init',
	function () {
		// Guard against unexpected load-order issues.
		if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
			return;
		}

		if ( ! class_exists( \Amazee\AiProvider\AmazeeIoAiProvider::class ) ) {
			return;
		}

		$registry = \WordPress\AiClient\AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( AmazeeIoAiProvider::class ) ) {
			$registry->registerProvider( \Amazee\AiProvider\AmazeeIoAiProvider::class );
		}
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
				$value = defined( 'AMAZEE_ENDPOINT_URL' ) ? AMAZEE_ENDPOINT_URL : get_option( 'wp_ai_client_amazee_endpoint_url', '' );
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
				$value = $is_constant_defined ? '••••••••••••••••' : get_option( 'wp_ai_client_amazee_llm_token', '' );
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
									'a' => array(
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

