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
		amazeeio_test_set_credential( 'https://llm.ch101.amazee.ai/v1/|core-token' );

		$config = AmazeeIoAiProvider::getApiConfiguration();

		$this->assertEquals( 'https://llm.ch101.amazee.ai/v1', $config['url'] );
		$this->assertEquals( 'core-token', $config['token'] );
	}

	public function testGetApiConfigurationFromSettingsOption() {
		$GLOBALS['wp_mock_options']['ai_provider_for_amazee_ai_settings'] = array( 'endpoint_url' => 'https://llm.opt.amazee.ai/v1/' );
		amazeeio_test_set_credential( 'plain-token' );

		$config = AmazeeIoAiProvider::getApiConfiguration();

		$this->assertEquals( 'https://llm.opt.amazee.ai/v1', $config['url'] );
		$this->assertEquals( 'plain-token', $config['token'] );
	}

	public function testSettingsOptionUrlBeatsPipeCredentialUrl() {
		$GLOBALS['wp_mock_options']['ai_provider_for_amazee_ai_settings'] = array( 'endpoint_url' => 'https://llm.opt.amazee.ai/v1' );
		amazeeio_test_set_credential( 'https://llm.pipe.amazee.ai/v1|pipe-token' );

		$config = AmazeeIoAiProvider::getApiConfiguration();

		$this->assertEquals( 'https://llm.opt.amazee.ai/v1', $config['url'] );
		$this->assertEquals( 'pipe-token', $config['token'] );
	}

	public function testGetApiConfigurationCoreCredentialPlainToken() {
		amazeeio_test_set_credential( 'just-a-token' );

		$config = AmazeeIoAiProvider::getApiConfiguration();

		$this->assertEquals( '', $config['url'] );
		$this->assertEquals( 'just-a-token', $config['token'] );
	}

	/**
	 * The stored credential belongs to the AI client, which resolves it and
	 * hands it to the provider. The plugin must never read it back out of the
	 * options table itself.
	 */
	public function testStoredCredentialOptionIsNeverRead() {
		$GLOBALS['wp_mock_options']['connectors_ai_amazeeio_api_key'] = 'https://llm.de102.amazee.ai/v1|option-token';
		amazeeio_test_set_credential( '' );

		$config = AmazeeIoAiProvider::getApiConfiguration();

		$this->assertSame( '', $config['url'] );
		$this->assertSame( '', $config['token'] );
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
		amazeeio_test_set_credential( 'fallback-token' );

		$directory = new AmazeeIoModelDirectory();
		$auth = $directory->getRequestAuthentication();

		$this->assertInstanceOf( ApiKeyRequestAuthentication::class, $auth );
		$this->assertEquals( 'fallback-token', $auth->getApiKey() );
	}
}
