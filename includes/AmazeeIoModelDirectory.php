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
	 * @param string $model_id Model identifier.
	 * @return list<string>|null
	 */
	public static function getSupportedApiParams( string $model_id ): ?array {
		$model_data = get_transient( self::cacheKey() );
		if ( ! is_array( $model_data ) ) {
			return null;
		}

		foreach ( $model_data as $info_node ) {
			if ( ! is_array( $info_node ) || ( $info_node['model_name'] ?? null ) !== $model_id ) {
				continue;
			}
			$params = $info_node['model_info']['supported_openai_params'] ?? null;
			return is_array( $params ) && array() !== $params ? array_values( $params ) : null;
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param HttpMethodEnum $method  HTTP method.
	 * @param string         $path    Request path relative to the base URL.
	 * @param array          $headers Request headers.
	 * @param mixed          $data    Request body data.
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
			$core_auth = parent::getRequestAuthentication();
		} catch ( \Exception $exception ) {
			$core_auth = null;
		}

		return AmazeeIoAiProvider::resolveRequestAuthentication( $core_auth );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function sendListModelsRequest(): array {
		$model_data = get_transient( self::cacheKey() );
		if ( ! is_array( $model_data ) ) {
			$model_data = $this->fetchModelData();
			set_transient( self::cacheKey(), $model_data, self::CACHE_TTL );
		}

		$metadata_list = array();
		foreach ( $model_data as $info_node ) {
			$metadata = $this->modelMetadataFromInfo( $info_node );
			if ( null !== $metadata ) {
				$metadata_list[ $metadata->getId() ] = $metadata;
			}
		}

		return $metadata_list;
	}

	/**
	 * Fetches the raw model information from the LiteLLM `model/info` endpoint.
	 *
	 * @return list<mixed> Raw model info nodes.
	 */
	private function fetchModelData(): array {
		$transporter = $this->getHttpTransporter();
		$api_req      = $this->createRequest( HttpMethodEnum::GET(), 'model/info' );
		$api_req      = $this->getRequestAuthentication()->authenticateRequest( $api_req );
		$api_res      = $transporter->send( $api_req );

		$this->throwIfNotSuccessful( $api_res );

		$body = $api_res->getData();
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
	 * @param mixed $info_node Raw node from the model info response.
	 */
	private function modelMetadataFromInfo( $info_node ): ?ModelMetadata {
		if ( ! is_array( $info_node ) || ! isset( $info_node['model_name'] ) ) {
			return null;
		}

		$id   = $info_node['model_name'];
		$meta = $info_node['model_info'] ?? null;
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
	 *
	 * @param Response $response HTTP response to parse.
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		return array();
	}
}
