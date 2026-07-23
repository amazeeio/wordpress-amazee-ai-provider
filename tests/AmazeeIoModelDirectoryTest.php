<?php

namespace Amazee\AiProvider\Tests;

use Amazee\AiProvider\AmazeeIoModelDirectory;
use PHPUnit\Framework\TestCase;

class AmazeeIoModelDirectoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_mock_options']    = array();
		$GLOBALS['wp_mock_transients'] = array();
		$GLOBALS['wp_mock_options']['connectors_ai_amazeeio_api_key'] = 'https://llm.us103.amazee.ai/v1|test-token';
	}

	private function seedModelData( array $data ): void {
		$GLOBALS['wp_mock_transients'][ AmazeeIoModelDirectory::cacheKey() ] = $data;
	}

	public function testCacheKeyVariesWithEndpointUrl() {
		$keyA = AmazeeIoModelDirectory::cacheKey();
		$GLOBALS['wp_mock_options']['connectors_ai_amazeeio_api_key'] = 'https://llm.ch101.amazee.ai/v1|test-token';
		$keyB = AmazeeIoModelDirectory::cacheKey();

		$this->assertNotEquals( $keyA, $keyB );
	}

	public function testGetSupportedApiParamsReturnsParamsFromCache() {
		$this->seedModelData(
			array(
				array(
					'model_name' => 'llama-3.3-70b',
					'model_info' => array(
						'mode'                    => 'chat',
						'supported_openai_params' => array( 'temperature', 'max_tokens' ),
					),
				),
			)
		);

		$this->assertSame(
			array( 'temperature', 'max_tokens' ),
			AmazeeIoModelDirectory::getSupportedApiParams( 'llama-3.3-70b' )
		);
	}

	public function testGetSupportedApiParamsReturnsNullWhenUncached() {
		$this->assertNull( AmazeeIoModelDirectory::getSupportedApiParams( 'llama-3.3-70b' ) );
	}

	public function testGetSupportedApiParamsReturnsNullForUnknownModelOrEmptyList() {
		$this->seedModelData(
			array(
				array(
					'model_name' => 'no-params-model',
					'model_info' => array(
						'mode'                    => 'chat',
						'supported_openai_params' => array(),
					),
				),
			)
		);

		$this->assertNull( AmazeeIoModelDirectory::getSupportedApiParams( 'no-params-model' ) );
		$this->assertNull( AmazeeIoModelDirectory::getSupportedApiParams( 'unknown-model' ) );
	}

	public function testSendListModelsRequestBuildsMetadataFromCache() {
		$this->seedModelData(
			array(
				array(
					'model_name' => 'chat-model',
					'model_info' => array( 'mode' => 'chat' ),
				),
				array(
					'model_name' => 'embedding-model',
					'model_info' => array( 'mode' => 'embedding' ),
				),
				array( 'not-a-model-node' ),
			)
		);

		$directory = new AmazeeIoModelDirectory();
		$method    = new \ReflectionMethod( $directory, 'sendListModelsRequest' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$list = $method->invoke( $directory );

		// Only the chat model is exposed; no HTTP request happens on a cache hit.
		$this->assertCount( 1, $list );
		$this->assertArrayHasKey( 'chat-model', $list );

		$capabilities = array_map( 'strval', $list['chat-model']->getSupportedCapabilities() );
		$this->assertContains( 'text_generation', $capabilities );
		$this->assertContains( 'chat_history', $capabilities );
	}
}
