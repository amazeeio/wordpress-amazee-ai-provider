<?php
/**
 * Shared request handling for amazee.ai models without an SDK base class.
 *
 * @package Amazee\AiProvider
 */

declare( strict_types=1 );

namespace Amazee\AiProvider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;

/**
 * Sends authenticated JSON requests to an amazee.ai endpoint.
 */
abstract class AmazeeIoApiModel extends AbstractApiBasedModel {

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
	 * POSTs JSON params to an endpoint path and returns the successful response.
	 *
	 * @param string               $path   Request path relative to the base URL.
	 * @param array<string, mixed> $params Request body.
	 */
	protected function post( string $path, array $params ): Response {
		$request = new Request(
			HttpMethodEnum::POST(),
			AmazeeIoAiProvider::url( $path ),
			array(
				'Content-Type'    => 'application/json',
				'X-Amazee-Client' => AmazeeIoAiProvider::clientHeaderValue(),
			),
			$params,
			$this->getRequestOptions()
		);

		$response = $this->getHttpTransporter()->send(
			$this->getRequestAuthentication()->authenticateRequest( $request )
		);

		AmazeeIoAiProvider::throwOnBudgetError( $response );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $response;
	}

	/**
	 * Merges the caller's custom options into the request params.
	 *
	 * @param array<string, mixed> $params Request params built by the model.
	 * @return array<string, mixed>
	 */
	protected function withCustomOptions( array $params ): array {
		foreach ( $this->getConfig()->getCustomOptions() as $key => $value ) {
			if ( ! array_key_exists( $key, $params ) ) {
				$params[ $key ] = $value;
			}
		}

		return $params;
	}
}
