<?php
/**
 * Amazee.io image generation model execution wrapper.
 *
 * @package Amazee\AiProvider
 */

declare( strict_types=1 );

namespace Amazee\AiProvider;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;

/**
 * Executes image generation requests via amazee.ai endpoints.
 */
class AmazeeIoImageModel extends AbstractOpenAiCompatibleImageGenerationModel {

	/**
	 * {@inheritDoc}
	 *
	 * @param HttpMethodEnum $method  HTTP method.
	 * @param string         $path    Request path relative to the base URL.
	 * @param array          $headers Request headers.
	 * @param mixed          $data    Request body data.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$headers['X-Amazee-Client'] = AmazeeIoAiProvider::clientHeaderValue();

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
			$core_auth = parent::getRequestAuthentication();
		} catch ( \Exception $exception ) {
			$core_auth = null;
		}

		return AmazeeIoAiProvider::resolveRequestAuthentication( $core_auth );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Response $response HTTP response to check.
	 */
	protected function throwIfNotSuccessful( Response $response ): void {
		AmazeeIoAiProvider::throwOnBudgetError( $response );

		parent::throwIfNotSuccessful( $response );
	}
}
