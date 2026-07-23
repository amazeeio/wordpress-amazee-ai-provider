<?php
/**
 * Amazee.io text model execution wrapper.
 *
 * @package Amazee\AiProvider
 */

declare( strict_types=1 );

namespace Amazee\AiProvider;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Executes text generation requests via amazee.ai endpoints.
 */
class AmazeeIoTextModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * Request parameters that are always sent regardless of what a model
	 * reports as supported.
	 */
	private const ESSENTIAL_PARAMS = array( 'model', 'messages', 'modalities' );

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
	 * Drops parameters the selected model does not support, based on the
	 * `supported_openai_params` list the amazee.ai endpoint reports per
	 * model. Prevents API errors from e.g. sending `temperature` to a
	 * model that rejects it.
	 *
	 * @param array $prompt Prompt parts to generate text for.
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$params = parent::prepareGenerateTextParams( $prompt );

		$supported = AmazeeIoModelDirectory::getSupportedApiParams( $this->metadata()->getId() );
		if ( null === $supported ) {
			return $params;
		}

		foreach ( array_keys( $params ) as $key ) {
			if ( ! in_array( $key, self::ESSENTIAL_PARAMS, true ) && ! in_array( $key, $supported, true ) ) {
				unset( $params[ $key ] );
			}
		}

		return $params;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Maps amazee.ai budget errors to an actionable message.
	 *
	 * @param Response $response HTTP response to check.
	 * @throws ClientException If the amazee.ai budget has been exceeded.
	 */
	protected function throwIfNotSuccessful( Response $response ): void {
		if ( $response->isSuccessful() ) {
			return;
		}

		$data    = $response->getData();
		$message = is_array( $data ) ? ( $data['error']['message'] ?? '' ) : '';
		if ( is_string( $message ) && false !== stripos( $message, 'budget has been exceeded' ) ) {
			throw new ClientException(
				'Your amazee.ai budget has been exceeded. Review your plan and spend at https://my.amazee.io.'
			);
		}

		parent::throwIfNotSuccessful( $response );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array|null $output_schema JSON schema for the expected output, if any.
	 */
	protected function prepareResponseFormatParam( ?array $output_schema ): array {
		if ( null === $output_schema ) {
			return array( 'type' => 'json_object' );
		}

		return array(
			'type'        => 'json_schema',
			'json_schema' => array(
				'name'   => 'outputSchema',
				'schema' => $output_schema,
			),
		);
	}
}
