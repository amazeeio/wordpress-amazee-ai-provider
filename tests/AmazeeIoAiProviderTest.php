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

	public function testGetApiConfigurationFromCoreConnectorCredential() {
		$GLOBALS['wp_mock_options']['connectors_ai_amazeeio_api_key'] = 'https://llm.ch101.amazee.ai/v1/|core-token';

		$config = AmazeeIoAiProvider::getApiConfiguration();

		$this->assertEquals( 'https://llm.ch101.amazee.ai/v1', $config['url'] );
		$this->assertEquals( 'core-token', $config['token'] );
	}

	public function testGetApiConfigurationCoreCredentialPlainToken() {
		$GLOBALS['wp_mock_options']['connectors_ai_amazeeio_api_key'] = 'just-a-token';

		$config = AmazeeIoAiProvider::getApiConfiguration();

		$this->assertEquals( '', $config['url'] );
		$this->assertEquals( 'just-a-token', $config['token'] );
	}

	public function testGetApiConfigurationCoreCredentialEnvVarWinsOverOption() {
		putenv( 'AMAZEEIO_API_KEY=https://llm.de102.amazee.ai/v1|env-token' );
		$GLOBALS['wp_mock_options']['connectors_ai_amazeeio_api_key'] = 'https://llm.ch101.amazee.ai/v1|db-token';

		try {
			$config = AmazeeIoAiProvider::getApiConfiguration();
		} finally {
			putenv( 'AMAZEEIO_API_KEY' );
		}

		$this->assertEquals( 'https://llm.de102.amazee.ai/v1', $config['url'] );
		$this->assertEquals( 'env-token', $config['token'] );
	}

	public function testVersionConstantMatchesPluginHeaderAndReadme() {
		$plugin = file_get_contents( dirname( __DIR__ ) . '/ai-provider-for-amazee-ai.php' );
		preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $plugin, $matches );
		$this->assertSame( AmazeeIoAiProvider::VERSION, $matches[1] ?? null, 'Version header in the main plugin file must match AmazeeIoAiProvider::VERSION.' );

		$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
		preg_match( '/^Stable tag:\s*(\S+)/m', $readme, $matches );
		$this->assertSame( AmazeeIoAiProvider::VERSION, $matches[1] ?? null, 'Stable tag in readme.txt must match AmazeeIoAiProvider::VERSION.' );
	}

	public function testGetRequestAuthenticationFallback() {
		$GLOBALS['wp_mock_options']['connectors_ai_amazeeio_api_key'] = 'fallback-token';

		$directory = new AmazeeIoModelDirectory();
		$auth = $directory->getRequestAuthentication();

		$this->assertInstanceOf( ApiKeyRequestAuthentication::class, $auth );
		$this->assertEquals( 'fallback-token', $auth->getApiKey() );
	}
}
