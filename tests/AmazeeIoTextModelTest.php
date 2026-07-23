<?php

namespace Amazee\AiProvider\Tests;

use Amazee\AiProvider\AmazeeIoAiProvider;
use Amazee\AiProvider\AmazeeIoModelDirectory;
use Amazee\AiProvider\AmazeeIoTextModel;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

class AmazeeIoTextModelTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_mock_options']    = array();
		$GLOBALS['wp_mock_transients'] = array();
		$GLOBALS['wp_mock_options']['connectors_ai_amazeeio_api_key'] = 'https://llm.us103.amazee.ai/v1|test-token';
	}

	private function createModel( string $modelId = 'test-model' ): AmazeeIoTextModel {
		return new AmazeeIoTextModel(
			new ModelMetadata( $modelId, $modelId, array(), array() ),
			new ProviderMetadata( 'amazeeio', 'amazee.ai', ProviderTypeEnum::server() )
		);
	}

	public function testResolveRequestAuthenticationSplitsPipedToken() {
		$auth = AmazeeIoAiProvider::resolveRequestAuthentication(
			new ApiKeyRequestAuthentication( 'key-alias|secret-token' )
		);

		$this->assertInstanceOf( ApiKeyRequestAuthentication::class, $auth );
		$this->assertEquals( 'secret-token', $auth->getApiKey() );
	}

	public function testResolveRequestAuthenticationKeepsPlainKey() {
		$coreAuth = new ApiKeyRequestAuthentication( 'plain-token' );

		$this->assertSame( $coreAuth, AmazeeIoAiProvider::resolveRequestAuthentication( $coreAuth ) );
	}

	public function testResolveRequestAuthenticationFallsBackToConfiguredToken() {
		$auth = AmazeeIoAiProvider::resolveRequestAuthentication( new ApiKeyRequestAuthentication( '  ' ) );

		$this->assertEquals( 'test-token', $auth->getApiKey() );
	}

	public function testResolveRequestAuthenticationThrowsWhenUnconfigured() {
		$GLOBALS['wp_mock_options']['connectors_ai_amazeeio_api_key'] = '';

		$this->expectException( RuntimeException::class );
		AmazeeIoAiProvider::resolveRequestAuthentication( null );
	}

	public function testPrepareGenerateTextParamsFiltersUnsupportedParams() {
		$model = $this->createModel( 'strict-model' );
		$model->setConfig(
			ModelConfig::fromArray(
				array(
					'temperature' => 0.5,
					'topP'        => 0.9,
					'maxTokens'   => 128,
				)
			)
		);

		$GLOBALS['wp_mock_transients'][ AmazeeIoModelDirectory::cacheKey() ] = array(
			array(
				'model_name' => 'strict-model',
				'model_info' => array(
					'mode'                    => 'chat',
					'supported_openai_params' => array( 'max_tokens' ),
				),
			),
		);

		$params = $this->prepareParams( $model );

		$this->assertArrayHasKey( 'model', $params );
		$this->assertArrayHasKey( 'messages', $params );
		$this->assertArrayHasKey( 'max_tokens', $params );
		$this->assertArrayNotHasKey( 'temperature', $params );
		$this->assertArrayNotHasKey( 'top_p', $params );
	}

	public function testPrepareGenerateTextParamsKeepsAllParamsWhenUncached() {
		$model = $this->createModel();
		$model->setConfig( ModelConfig::fromArray( array( 'temperature' => 0.5 ) ) );

		$params = $this->prepareParams( $model );

		$this->assertArrayHasKey( 'temperature', $params );
	}

	public function testThrowIfNotSuccessfulMapsBudgetError() {
		$body = json_encode(
			array(
				'error' => array( 'message' => 'Budget has been exceeded! Current cost: 1.1, Max budget: 1.0' ),
			)
		);

		$this->expectException( ClientException::class );
		$this->expectExceptionMessageMatches( '/amazee\.ai budget has been exceeded/' );
		$this->throwIfNotSuccessful( new Response( 400, array(), $body ) );
	}

	public function testThrowIfNotSuccessfulDelegatesOtherErrors() {
		$this->expectException( ClientException::class );
		$this->expectExceptionMessageMatches( '/^((?!amazee\.ai budget).)*$/s' );
		$this->throwIfNotSuccessful( new Response( 404, array(), '{"error":{"message":"Not found"}}' ) );
	}

	public function testThrowIfNotSuccessfulAcceptsSuccess() {
		$this->throwIfNotSuccessful( new Response( 200, array(), '{}' ) );
		$this->addToAssertionCount( 1 );
	}

	private function prepareParams( AmazeeIoTextModel $model ): array {
		$prompt = array(
			new Message( MessageRoleEnum::user(), array( new MessagePart( 'Hello' ) ) ),
		);

		$method = new \ReflectionMethod( $model, 'prepareGenerateTextParams' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method->invoke( $model, $prompt );
	}

	private function throwIfNotSuccessful( Response $response ): void {
		$model  = $this->createModel();
		$method = new \ReflectionMethod( $model, 'throwIfNotSuccessful' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$method->invoke( $model, $response );
	}
}
