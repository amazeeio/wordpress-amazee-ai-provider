<?php

namespace Amazee\AiProvider\Tests;

use Amazee\AiProvider\AmazeeIoModelDirectory;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

class AmazeeIoModelDirectoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_mock_options']    = array();
		$GLOBALS['wp_mock_transients'] = array();
		amazeeio_test_set_credential( 'https://llm.us103.amazee.ai/v1|test-token' );
	}

	private function seedModelData( array $data ): void {
		$GLOBALS['wp_mock_transients'][ AmazeeIoModelDirectory::cacheKey() ] = $data;
	}

	public function testCacheKeyVariesWithEndpointUrl() {
		$keyA = AmazeeIoModelDirectory::cacheKey();
		amazeeio_test_set_credential( 'https://llm.ch101.amazee.ai/v1|test-token' );
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

	/**
	 * Lists the model metadata built from the seeded cache.
	 *
	 * @return array<string, \WordPress\AiClient\Providers\Models\DTO\ModelMetadata>
	 */
	private function listModelMetadataFromCache(): array {
		$directory = new AmazeeIoModelDirectory();
		$method    = new \ReflectionMethod( $directory, 'sendListModelsRequest' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// No HTTP request happens on a cache hit.
		return $method->invoke( $directory );
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

		$list = $this->listModelMetadataFromCache();

		// Modes without a model class stay unexposed.
		$this->assertCount( 1, $list );
		$this->assertArrayHasKey( 'chat-model', $list );

		$capabilities = array_map( 'strval', $list['chat-model']->getSupportedCapabilities() );
		$this->assertContains( 'text_generation', $capabilities );
		$this->assertContains( 'chat_history', $capabilities );
	}

	public function testImageGenerationModeIsExposedAsImageGeneration() {
		$this->seedModelData(
			array(
				array(
					'model_name' => 'text_to_image',
					'model_info' => array(
						'mode'                    => 'image_generation',
						// Image models report the chat parameter list, which must not leak
						// into the metadata as text generation options.
						'supported_openai_params' => array( 'temperature', 'response_format' ),
						'supports_vision'         => true,
					),
				),
			)
		);

		$list = $this->listModelMetadataFromCache();

		$this->assertArrayHasKey( 'text_to_image', $list );
		$this->assertSame(
			array( 'image_generation' ),
			array_map( 'strval', $list['text_to_image']->getSupportedCapabilities() )
		);

		$options = array();
		foreach ( $list['text_to_image']->getSupportedOptions() as $option ) {
			$options[ $option->getName()->value ] = $option;
		}
		// Compared against the enum values, which differ between client versions.
		$this->assertSame(
			array(
				OptionEnum::inputModalities()->value,
				OptionEnum::outputModalities()->value,
				OptionEnum::outputFileType()->value,
			),
			array_keys( $options )
		);
		$this->assertTrue( $options[ OptionEnum::outputFileType()->value ]->isSupportedValue( FileTypeEnum::inline() ) );
		$this->assertTrue( $options[ OptionEnum::outputModalities()->value ]->isSupportedValue( array( ModalityEnum::image() ) ) );
	}
}
