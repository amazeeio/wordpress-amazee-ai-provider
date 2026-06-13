<?php

namespace Amazee\AiProvider\Tests;

use PHPUnit\Framework\TestCase;
use Amazee\AiProvider\AmazeeIoAiProvider;
use Amazee\AiProvider\AmazeeIoModelDirectory;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

class AmazeeIoAiProviderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_mock_options'] = array();
		$GLOBALS['wp_mock_constants'] = array();
	}

	public function testGetApiConfigurationWithDbOptions() {
		$GLOBALS['wp_mock_options']['wp_ai_client_amazee_endpoint_url'] = 'https://llm.us103.amazee.ai/v1/';
		$GLOBALS['wp_mock_options']['wp_ai_client_amazee_llm_token'] = 'my-token';

		$config = AmazeeIoAiProvider::getApiConfiguration();

		$this->assertEquals( 'https://llm.us103.amazee.ai/v1', $config['url'] ); // Trims trailing slash.
		$this->assertEquals( 'my-token', $config['token'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testGetApiConfigurationWithConstants() {
		if ( ! defined( 'AMAZEE_ENDPOINT_URL' ) ) {
			define( 'AMAZEE_ENDPOINT_URL', 'https://llm.constant.amazee.ai/v1' );
		}
		if ( ! defined( 'AMAZEE_LLM_TOKEN' ) ) {
			define( 'AMAZEE_LLM_TOKEN', 'constant-token' );
		}

		$config = AmazeeIoAiProvider::getApiConfiguration();

		$this->assertEquals( 'https://llm.constant.amazee.ai/v1', $config['url'] );
		$this->assertEquals( 'constant-token', $config['token'] );
	}

	public function testGetRequestAuthenticationFallback() {
		$GLOBALS['wp_mock_options']['wp_ai_client_amazee_llm_token'] = 'fallback-token';

		$directory = new AmazeeIoModelDirectory();
		$auth = $directory->getRequestAuthentication();

		$this->assertInstanceOf( ApiKeyRequestAuthentication::class, $auth );
		$this->assertEquals( 'fallback-token', $auth->getApiKey() );
	}
}
