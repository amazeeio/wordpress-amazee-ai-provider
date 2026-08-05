<?php
/**
 * Amazee.io settings screen class.
 *
 * @package Amazee\AiProvider
 */

declare( strict_types=1 );

namespace Amazee\AiProvider;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings > amazee.ai screen for configuring the endpoint URL.
 *
 * The LLM token itself stays on the core Settings > Connectors screen, where
 * the AI client stores and resolves it. This screen only manages the endpoint
 * URL and shows whether the connection works.
 */
class AmazeeIoSettings {

	private const OPTION_GROUP = 'ai-provider-for-amazee-ai-settings';
	private const OPTION_NAME  = 'ai_provider_for_amazee_ai_settings';
	private const PAGE_SLUG    = 'ai-provider-for-amazee-ai';
	private const SECTION_ID   = 'ai_provider_for_amazee_ai_main';

	/**
	 * Hooks the settings registration and screen into the admin.
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_screen' ) );
		add_action( 'admin_notices', array( $this, 'render_connectors_screen_notice' ) );
		add_action( 'wp_connectors_init', array( $this, 'update_connector_description' ) );
	}

	/**
	 * Drops the setup steps from the connector description once configured.
	 *
	 * The default description from the provider metadata walks through the
	 * two setup steps. The connector registry is built on demand and fires
	 * this action when endpoint URL and token are already resolvable, so the
	 * steps can be replaced with the plain tagline when both are set.
	 *
	 * @param \WP_Connector_Registry $registry Connector registry instance.
	 */
	public function update_connector_description( $registry ): void {
		$config = AmazeeIoAiProvider::getApiConfiguration();
		if ( '' === $config['url'] || '' === $config['token'] ) {
			return;
		}

		if ( ! $registry->is_registered( 'amazeeio' ) ) {
			return;
		}

		$connector = $registry->unregister( 'amazeeio' );
		if ( null === $connector ) {
			return;
		}

		$connector['description'] = __( 'Secure private AI for your site, hosted by amazee.ai.', 'ai-provider-for-amazee-ai' );
		$registry->register( 'amazeeio', $connector );
	}

	/**
	 * Points to the settings screen from the Connectors screen while the
	 * endpoint URL is missing.
	 *
	 * The connector card itself cannot link anywhere: core renders the
	 * description as escaped plain text, so a notice is the only place on
	 * that screen a link to the settings screen can live.
	 */
	public function render_connectors_screen_notice(): void {
		if ( 'options-connectors.php' !== $GLOBALS['pagenow'] || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config = AmazeeIoAiProvider::getApiConfiguration();
		if ( '' !== $config['url'] ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			sprintf(
				/* translators: 1: opening link tag to the amazee.ai settings screen, 2: closing link tag */
				esc_html__( 'amazee.ai — Step 1: set your endpoint URL on the %1$sSettings > amazee.ai%2$s screen. Step 2: enter your LLM token on the amazee.ai connector below.', 'ai-provider-for-amazee-ai' ),
				'<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) . '">',
				'</a>'
			)
		);
	}

	/**
	 * Registers the setting and its endpoint URL field.
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			'',
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_NAME . '_endpoint_url',
			__( 'Endpoint URL', 'ai-provider-for-amazee-ai' ),
			array( $this, 'render_endpoint_url_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-endpoint-url' )
		);
	}

	/**
	 * Registers the settings screen under the Settings menu.
	 */
	public function register_settings_screen(): void {
		add_options_page(
			__( 'amazee.ai Settings', 'ai-provider-for-amazee-ai' ),
			__( 'amazee.ai', 'ai-provider-for-amazee-ai' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_screen' )
		);
	}

	/**
	 * Sanitizes the settings array.
	 *
	 * @param mixed $value The input value.
	 * @return array<string, string> The sanitized settings.
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$endpoint_url = isset( $value['endpoint_url'] ) ? trim( (string) $value['endpoint_url'] ) : '';
		if ( '' !== $endpoint_url ) {
			$endpoint_url = rtrim( esc_url_raw( $endpoint_url ), '/' );
		}

		return array(
			'endpoint_url' => $endpoint_url,
		);
	}

	/**
	 * Renders the settings screen.
	 */
	public function render_screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>

		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: opening link tag to my.amazee.io, 2: closing link tag */
					esc_html__( 'amazee.ai credentials are managed in the amazee.io dashboard: copy the endpoint URL and LLM token for your region from %1$smy.amazee.io%2$s.', 'ai-provider-for-amazee-ai' ),
					'<a href="https://my.amazee.io" target="_blank" rel="noopener">',
					'</a>'
				);
				?>
			</p>
			<?php
			$config = AmazeeIoAiProvider::getApiConfiguration();
			if ( '' === $config['url'] || '' === $config['token'] ) :
				?>
			<p>
				<?php
				esc_html_e( 'Step 1: enter the endpoint URL below.', 'ai-provider-for-amazee-ai' );
				echo '<br>';
				printf(
					/* translators: 1: opening link tag to the Connectors screen, 2: closing link tag */
					esc_html__( 'Step 2: enter your LLM token for the amazee.ai connector on the %1$sSettings > Connectors%2$s screen.', 'ai-provider-for-amazee-ai' ),
					'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">',
					'</a>'
				);
				?>
			</p>
			<?php endif; ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
			<h2><?php esc_html_e( 'Connection', 'ai-provider-for-amazee-ai' ); ?></h2>
			<?php $this->render_connection_status(); ?>
		</div>

		<?php
	}

	/**
	 * Renders the endpoint URL field.
	 */
	public function render_endpoint_url_field(): void {
		$constant_url = defined( 'AMAZEE_ENDPOINT_URL' ) ? AMAZEE_ENDPOINT_URL : '';
		$constant_url = is_string( $constant_url ) ? trim( $constant_url ) : '';
		$constant_set = '' !== $constant_url;
		?>

		<input
			type="url"
			id="<?php echo esc_attr( self::OPTION_NAME . '-endpoint-url' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[endpoint_url]' ); ?>"
			value="<?php echo esc_attr( $constant_set ? $constant_url : self::get_endpoint_url() ); ?>"
			class="regular-text"
			placeholder="https://llm.&lt;region&gt;.amazee.ai/v1"
			required
			<?php disabled( $constant_set ); ?>
		/>
		<p class="description">
			<?php
			if ( $constant_set ) {
				printf(
					/* translators: 1: code tag, 2: closing code tag */
					esc_html__( 'Defined by the %1$sAMAZEE_ENDPOINT_URL%2$s constant in wp-config.php.', 'ai-provider-for-amazee-ai' ),
					'<code>',
					'</code>'
				);
			} else {
				printf(
					/* translators: 1: code tag, 2: closing code tag */
					esc_html__( 'The endpoint URL for your amazee.ai region, for example %1$shttps://llm.us103.amazee.ai/v1%2$s where %1$sus103%2$s is a US region.', 'ai-provider-for-amazee-ai' ),
					'<code>',
					'</code>'
				);
				echo '<br>';
				esc_html_e( 'Regions are also available in the UK, Germany, Switzerland, Australia and more.', 'ai-provider-for-amazee-ai' );
				echo '<br>';
				esc_html_e( 'There is no default: your LLM token only works with the region it was issued for, so copy the exact URL from my.amazee.io.', 'ai-provider-for-amazee-ai' );
			}
			?>
		</p>

		<?php
	}

	/**
	 * Renders the connection status by listing the models of the endpoint.
	 *
	 * The model catalog is cached in a transient for 12 hours, so this check
	 * only hits the API when the cache is cold.
	 */
	private function render_connection_status(): void {
		$config = AmazeeIoAiProvider::getApiConfiguration();

		if ( '' === $config['url'] || '' === $config['token'] ) {
			printf(
				'<p>%s</p>',
				esc_html__( 'Not connected yet: enter the endpoint URL and LLM token first.', 'ai-provider-for-amazee-ai' )
			);
			return;
		}

		try {
			$models = AmazeeIoAiProvider::modelMetadataDirectory()->listModelMetadata();
		} catch ( \Throwable $exception ) {
			printf(
				'<p style="color:#d63638;">%s</p>',
				sprintf(
					/* translators: %s: error message returned by the API */
					esc_html__( 'Could not connect: %s', 'ai-provider-for-amazee-ai' ),
					esc_html( $exception->getMessage() )
				)
			);
			return;
		}

		printf(
			'<p style="color:#00a32a;">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: number of models */
					_n( 'Connected — %d model available.', 'Connected — %d models available.', count( $models ), 'ai-provider-for-amazee-ai' ),
					count( $models )
				)
			)
		);

		// The endpoint describes models in `model_info.metadata`, which the
		// model directory caches raw in its transient but does not expose via
		// ModelMetadata, so read the descriptions from the cache directly.
		$descriptions = array();
		$model_data   = get_transient( AmazeeIoModelDirectory::cacheKey() );
		if ( is_array( $model_data ) ) {
			foreach ( $model_data as $info_node ) {
				if ( ! is_array( $info_node ) || ! isset( $info_node['model_name'] ) ) {
					continue;
				}
				$metadata = $info_node['model_info']['metadata'] ?? '';

				$descriptions[ $info_node['model_name'] ] = is_string( $metadata ) ? $metadata : '';
			}
		}

		$model_ids = array();
		foreach ( $models as $model ) {
			$model_ids[] = $model->getId();
		}
		sort( $model_ids );
		?>

		<table class="widefat striped" style="max-width:800px;">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Model', 'ai-provider-for-amazee-ai' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Description', 'ai-provider-for-amazee-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $model_ids as $model_id ) : ?>
					<tr>
						<td><code><?php echo esc_html( $model_id ); ?></code></td>
						<td><?php echo esc_html( $descriptions[ $model_id ] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
	}

	/**
	 * Returns the endpoint URL stored in the plugin option.
	 */
	public static function get_endpoint_url(): string {
		$settings = (array) get_option( self::OPTION_NAME, array() );
		$url      = isset( $settings['endpoint_url'] ) && is_string( $settings['endpoint_url'] ) ? trim( $settings['endpoint_url'] ) : '';

		return rtrim( $url, '/' );
	}
}
