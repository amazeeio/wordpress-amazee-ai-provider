<?php
/**
 * Amazee.io text model execution wrapper.
 *
 * @package Amazee\AiProvider
 */

declare( strict_types=1 );

namespace Amazee\AiProvider;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Executes text generation requests via amazee.ai endpoints.
 */
class AmazeeIoTextModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request(
			$method,
			AmazeeIoAiProvider::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
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
		return new ApiKeyRequestAuthentication( $config['token'] );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function prepareResponseFormatParam( ?array $outputSchema ): array {
		if ( is_array( $outputSchema ) ) {
			return array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'outputSchema',
					'schema' => $outputSchema,
				),
			);
		}

		return array( 'type' => 'json_object' );
	}
}
