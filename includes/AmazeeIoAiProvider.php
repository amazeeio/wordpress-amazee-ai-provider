<?php
/**
 * Amazee.io AI provider connector class.
 *
 * @package Amazee\AiProvider
 */

declare( strict_types=1 );

namespace Amazee\AiProvider;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Handles integration of the amazee.ai AI service provider.
 */
class AmazeeIoAiProvider extends AbstractApiProvider {

	/**
	 * Plugin version, sent as client identification header on API requests.
	 *
	 * Must be kept in sync with the version in the main plugin file.
	 */
	public const VERSION = '1.2';

	/**
	 * Value of the `X-Amazee-Client` header sent with every API request.
	 */
	public static function clientHeaderValue(): string {
		return 'ai-provider-for-amazee-ai/' . self::VERSION;
	}

	/**
	 * Retrieves the configured endpoint and access token.
	 *
	 * @return array{url: string, token: string} Configuration array containing url and token keys.
	 */
	public static function getApiConfiguration(): array {
		$endpoint_url = defined( 'AMAZEE_ENDPOINT_URL' ) ? AMAZEE_ENDPOINT_URL : '';
		$auth_token   = defined( 'AMAZEE_LLM_TOKEN' ) ? AMAZEE_LLM_TOKEN : '';

		$endpoint_url = is_string( $endpoint_url ) ? trim( $endpoint_url ) : '';
		$auth_token   = is_string( $auth_token ) ? trim( $auth_token ) : '';

		// Fall back to the credential managed via the WordPress core
		// Connectors screen, which may carry the endpoint as `url|token`.
		if ( '' === $endpoint_url || '' === $auth_token ) {
			list( $core_url, $core_token ) = self::parseCredential( self::getCoreConnectorCredential() );
			if ( '' === $endpoint_url ) {
				$endpoint_url = $core_url;
			}
			if ( '' === $auth_token ) {
				$auth_token = $core_token;
			}
		}

		return array(
			'url'   => rtrim( $endpoint_url, '/' ),
			'token' => $auth_token,
		);
	}

	/**
	 * Returns the raw credential stored by the WordPress core Connectors screen.
	 *
	 * Checked in the same order core uses: environment variable, constant,
	 * then the setting written by the Connectors UI.
	 */
	private static function getCoreConnectorCredential(): string {
		$env_value = getenv( 'AMAZEEIO_API_KEY' );
		if ( is_string( $env_value ) && '' !== $env_value ) {
			return $env_value;
		}
		if ( defined( 'AMAZEEIO_API_KEY' ) && is_string( AMAZEEIO_API_KEY ) ) {
			return AMAZEEIO_API_KEY;
		}

		$option = get_option( 'connectors_ai_amazeeio_api_key', '' );
		return is_string( $option ) ? $option : '';
	}

	/**
	 * Splits a stored credential into endpoint URL and token.
	 *
	 * Supported formats: `token`, `key_alias|token` and
	 * `https://llm.<region>.amazee.ai/v1|token`.
	 *
	 * @param string $credential Raw credential value.
	 * @return array{0: string, 1: string} Endpoint URL (may be empty) and token.
	 */
	private static function parseCredential( string $credential ): array {
		$credential = trim( $credential );
		if ( false === strpos( $credential, '|' ) ) {
			return array( '', $credential );
		}

		list( $prefix, $token ) = explode( '|', $credential, 2 );
		$prefix                 = trim( $prefix );
		$url                    = preg_match( '#^https?://#i', $prefix ) ? $prefix : '';

		return array( $url, trim( $token ) );
	}

	/**
	 * Resolves the request authentication to use for API calls.
	 *
	 * The amazee.ai service issues tokens in a `key_alias|token` format via the core
	 * connector credentials; only the part after the pipe is the bearer
	 * token. When no usable core credential is given, the token configured
	 * via constant or option is used instead.
	 *
	 * @param RequestAuthenticationInterface|null $core_auth Authentication provided by the AI client core, if any.
	 * @throws RuntimeException If no token is configured anywhere.
	 */
	public static function resolveRequestAuthentication( ?RequestAuthenticationInterface $core_auth ): RequestAuthenticationInterface {
		if ( $core_auth instanceof ApiKeyRequestAuthentication ) {
			$raw_key = $core_auth->getApiKey();
			if ( false !== strpos( $raw_key, '|' ) ) {
				list( , $token_part ) = explode( '|', $raw_key, 2 );
				return new ApiKeyRequestAuthentication( trim( $token_part ) );
			}
			if ( '' !== trim( $raw_key ) ) {
				return $core_auth;
			}
		}

		$config = static::getApiConfiguration();
		if ( empty( $config['token'] ) ) {
			throw new RuntimeException( 'The amazee.ai LLM token is not configured.' );
		}
		return new ApiKeyRequestAuthentication( $config['token'] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws RuntimeException If no API URL is configured.
	 */
	protected static function baseUrl(): string {
		$config = static::getApiConfiguration();
		if ( empty( $config['url'] ) ) {
			throw new RuntimeException( 'The amazee.ai API URL is not configured.' );
		}
		return $config['url'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ModelMetadata    $model_metadata    Metadata of the model to create.
	 * @param ProviderMetadata $provider_metadata Metadata of this provider.
	 * @throws RuntimeException If the model capabilities are not supported.
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {
		$caps = $model_metadata->getSupportedCapabilities();
		foreach ( $caps as $cap ) {
			if ( $cap->isTextGeneration() ) {
				return new AmazeeIoTextModel( $model_metadata, $provider_metadata );
			}
		}

		throw new RuntimeException(
			sprintf( 'Capabilities not supported by amazee.ai provider: %s', esc_html( implode( ', ', $caps ) ) )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$icon_location = realpath( dirname( __DIR__ ) . '/assets/icon.svg' );
		if ( ! $icon_location ) {
			$icon_location = null;
		}

		return new ProviderMetadata(
			'amazeeio',
			'amazee.ai',
			ProviderTypeEnum::server(),
			'https://my.amazee.io',
			RequestAuthenticationMethod::apiKey(),
			__( 'Secure private AI for your site, hosted by amazee.ai. Copy the endpoint URL from my.amazee.io, add a | pipe, then your token: <endpoint URL>|<token>.', 'ai-provider-for-amazee-ai' ),
			$icon_location
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		// Attempt to list models to confirm connection parameters are valid.
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new AmazeeIoModelDirectory();
	}
}
