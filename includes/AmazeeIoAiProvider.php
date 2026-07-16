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
	 * Retrieves the configured endpoint and access token.
	 *
	 * @return array{url: string, token: string} Configuration array containing url and token keys.
	 */
	public static function getApiConfiguration(): array {
		$endpointUrl = defined( 'AMAZEE_ENDPOINT_URL' ) ? AMAZEE_ENDPOINT_URL : get_option( 'wp_ai_client_amazee_endpoint_url', '' );
		$authToken   = defined( 'AMAZEE_LLM_TOKEN' ) ? AMAZEE_LLM_TOKEN : get_option( 'wp_ai_client_amazee_llm_token', '' );

		return array(
			'url'   => is_string( $endpointUrl ) ? rtrim( trim( $endpointUrl ), '/' ) : '',
			'token' => is_string( $authToken ) ? trim( $authToken ) : '',
		);
	}

	/**
	 * Resolves the request authentication to use for API calls.
	 *
	 * amazee.ai issues tokens in a `key_alias|token` format via the core
	 * connector credentials; only the part after the pipe is the bearer
	 * token. When no usable core credential is given, the token configured
	 * via constant or option is used instead.
	 *
	 * @param RequestAuthenticationInterface|null $coreAuth Authentication provided by the AI client core, if any.
	 * @throws RuntimeException If no token is configured anywhere.
	 */
	public static function resolveRequestAuthentication( ?RequestAuthenticationInterface $coreAuth ): RequestAuthenticationInterface {
		if ( $coreAuth instanceof ApiKeyRequestAuthentication ) {
			$rawKey = $coreAuth->getApiKey();
			if ( false !== strpos( $rawKey, '|' ) ) {
				list( , $tokenPart ) = explode( '|', $rawKey, 2 );
				return new ApiKeyRequestAuthentication( trim( $tokenPart ) );
			}
			if ( '' !== trim( $rawKey ) ) {
				return $coreAuth;
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
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		$caps = $modelMetadata->getSupportedCapabilities();
		foreach ( $caps as $cap ) {
			if ( $cap->isTextGeneration() ) {
				return new AmazeeIoTextModel( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException(
			sprintf( 'Capabilities not supported by amazee.ai provider: %s', implode( ', ', $caps ) )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$iconLocation = realpath( dirname( __DIR__ ) . '/assets/icon.svg' );
		if ( ! $iconLocation ) {
			$iconLocation = null;
		}

		return new ProviderMetadata(
			'amazeeio',
			'amazee.ai',
			ProviderTypeEnum::server(),
			'https://my.amazee.io',
			null,
			__( 'Connects your site to secure open-weight LLMs hosted by amazee.ai.', 'amazee-ai-provider' ),
			$iconLocation
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
