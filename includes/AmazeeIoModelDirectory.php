<?php
/**
 * Amazee.io model catalog directory class.
 *
 * @package Amazee\AiProvider
 */

declare( strict_types=1 );

namespace Amazee\AiProvider;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Discovers and exposes models supported by the amazee.ai endpoints.
 *
 * Model capabilities are read dynamically from the LiteLLM `model/info`
 * endpoint and cached in a transient to avoid hitting the API on every
 * request that needs the model catalog.
 */
class AmazeeIoModelDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * How long to cache the raw model information, in seconds.
	 */
	private const CACHE_TTL = 43200;

	/**
	 * Returns the transient name used to cache model information.
	 *
	 * Keyed by endpoint URL so switching regions never serves stale models.
	 */
	public static function cacheKey(): string {
		$config = AmazeeIoAiProvider::getApiConfiguration();
		return 'amazeeio_ai_models_' . md5( $config['url'] );
	}

	/**
	 * Returns the OpenAI-compatible request parameters a model supports.
	 *
	 * Reads the `supported_openai_params` list from the cached model
	 * information. Returns null when unknown (cache expired or the endpoint
	 * does not report the list), in which case no filtering should happen.
	 *
	 * @param string $modelId Model identifier.
	 * @return list<string>|null
	 */
	public static function getSupportedApiParams( string $modelId ): ?array {
		$modelData = get_transient( self::cacheKey() );
		if ( ! is_array( $modelData ) ) {
			return null;
		}

		foreach ( $modelData as $infoNode ) {
			if ( ! is_array( $infoNode ) || ( $infoNode['model_name'] ?? null ) !== $modelId ) {
				continue;
			}
			$params = $infoNode['model_info']['supported_openai_params'] ?? null;
			return is_array( $params ) && array() !== $params ? array_values( $params ) : null;
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request(
			$method,
			AmazeeIoAiProvider::url( $path ),
			$headers,
			$data
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRequestAuthentication(): RequestAuthenticationInterface {
		try {
			$coreAuth = parent::getRequestAuthentication();
		} catch ( \Exception $exception ) {
			$coreAuth = null;
		}

		return AmazeeIoAiProvider::resolveRequestAuthentication( $coreAuth );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function sendListModelsRequest(): array {
		$modelData = get_transient( self::cacheKey() );
		if ( ! is_array( $modelData ) ) {
			$modelData = $this->fetchModelData();
			set_transient( self::cacheKey(), $modelData, self::CACHE_TTL );
		}

		$metadataList = array();
		foreach ( $modelData as $infoNode ) {
			$metadata = $this->modelMetadataFromInfo( $infoNode );
			if ( null !== $metadata ) {
				$metadataList[ $metadata->getId() ] = $metadata;
			}
		}

		return $metadataList;
	}

	/**
	 * Fetches the raw model information from the LiteLLM `model/info` endpoint.
	 *
	 * @return list<mixed> Raw model info nodes.
	 */
	private function fetchModelData(): array {
		$transporter = $this->getHttpTransporter();
		$apiReq      = $this->createRequest( HttpMethodEnum::GET(), 'model/info' );
		$apiReq      = $this->getRequestAuthentication()->authenticateRequest( $apiReq );
		$apiRes      = $transporter->send( $apiReq );

		$this->throwIfNotSuccessful( $apiRes );

		$body = $apiRes->getData();
		if ( ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return array();
		}

		return array_values( $body['data'] );
	}

	/**
	 * Builds model metadata from a single `model/info` node.
	 *
	 * Only chat models are exposed; capabilities and options are derived
	 * from the capability flags the endpoint reports for each model.
	 *
	 * @param mixed $infoNode Raw node from the model info response.
	 */
	private function modelMetadataFromInfo( $infoNode ): ?ModelMetadata {
		if ( ! is_array( $infoNode ) || ! isset( $infoNode['model_name'] ) ) {
			return null;
		}

		$id   = $infoNode['model_name'];
		$meta = $infoNode['model_info'] ?? null;
		if ( ! is_array( $meta ) || 'chat' !== ( $meta['mode'] ?? '' ) ) {
			return null;
		}

		$capabilities = array(
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		);

		// Core attributes supported by all models.
		$options   = array();
		$options[] = new SupportedOption( OptionEnum::systemInstruction() );
		$options[] = new SupportedOption( OptionEnum::maxTokens() );
		$options[] = new SupportedOption( OptionEnum::temperature() );
		$options[] = new SupportedOption( OptionEnum::topP() );

		if ( ! empty( $meta['supports_function_calling'] ) || ! empty( $meta['supports_tool_choice'] ) ) {
			$options[] = new SupportedOption( OptionEnum::functionDeclarations() );
		}

		if ( ! empty( $meta['supports_response_schema'] ) ) {
			$options[] = new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) );
			$options[] = new SupportedOption( OptionEnum::outputSchema() );
		}

		if ( ! empty( $meta['supports_vision'] ) ) {
			$options[] = new SupportedOption(
				OptionEnum::inputModalities(),
				array(
					array( ModalityEnum::text() ),
					array( ModalityEnum::text(), ModalityEnum::image() ),
				)
			);
		} else {
			$options[] = new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) );
		}
		$options[] = new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) );

		return new ModelMetadata( $id, $id, $capabilities, $options );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		return array();
	}
}
