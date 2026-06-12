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
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
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
 */
class AmazeeIoModelDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

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
			if ( $coreAuth instanceof ApiKeyRequestAuthentication ) {
				$rawKey = $coreAuth->getApiKey();
				if ( str_contains( $rawKey, '|' ) ) {
					list( , $tokenPart ) = explode( '|', $rawKey, 2 );
					return new ApiKeyRequestAuthentication( trim( $tokenPart ) );
				}
				return $coreAuth;
			}
		} catch ( \Exception $exception ) {
			// Fail over to use option settings.
		}

		$config = AmazeeIoAiProvider::getApiConfiguration();
		if ( empty( $config['token'] ) ) {
			throw new \RuntimeException( 'The amazee.ai LLM token is not configured.' );
		}
		return new ApiKeyRequestAuthentication( $config['token'] );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function sendListModelsRequest(): array {
		$transporter = $this->getHttpTransporter();
		$apiReq      = $this->createRequest( HttpMethodEnum::GET(), 'model/info' );
		$apiReq      = $this->getRequestAuthentication()->authenticateRequest( $apiReq );
		$apiRes      = $transporter->send( $apiReq );

		$this->throwIfNotSuccessful( $apiRes );

		$body = $apiRes->getData();
		if ( ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return array();
		}

		$metadataList = array();
		foreach ( $body['data'] as $infoNode ) {
			if ( ! is_array( $infoNode ) || ! isset( $infoNode['model_name'] ) ) {
				continue;
			}

			$id   = $infoNode['model_name'];
			$meta = $infoNode['model_info'] ?? null;
			if ( ! is_array( $meta ) ) {
				continue;
			}

			$capabilities = array();
			$options      = array();

			$runMode = $meta['mode'] ?? '';
			if ( 'chat' === $runMode ) {
				$capabilities[] = CapabilityEnum::textGeneration();
				$capabilities[] = CapabilityEnum::chatHistory();
			} else {
				continue;
			}

			// Core attributes supported by all models.
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

			$metadataList[ $id ] = new ModelMetadata(
				$id,
				$id,
				$capabilities,
				$options
			);
		}

		return $metadataList;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		return array();
	}
}
