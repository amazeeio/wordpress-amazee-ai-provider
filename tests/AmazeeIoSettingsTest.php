<?php

namespace Amazee\AiProvider\Tests;

use PHPUnit\Framework\TestCase;
use Amazee\AiProvider\AmazeeIoSettings;

class AmazeeIoSettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_mock_options']         = array();
		$GLOBALS['wp_mock_settings_errors'] = array();
		amazeeio_test_set_credential( '' );
	}

	private function connectorData(): array {
		return array(
			'connectors' => array(
				'amazeeio' => array( 'description' => 'Step 1: … Step 2: …' ),
			),
		);
	}

	public function testConnectorDescriptionDropsStepsOnceConfigured() {
		$GLOBALS['wp_mock_options']['ai_provider_for_amazee_ai_settings'] = array( 'endpoint_url' => 'https://llm.us103.amazee.ai/v1' );
		amazeeio_test_set_credential( 'a-token' );

		$data = ( new AmazeeIoSettings() )->update_connector_description( $this->connectorData() );

		$this->assertStringNotContainsString( 'Step 1', $data['connectors']['amazeeio']['description'] );
	}

	public function testConnectorDescriptionKeepsStepsWhileUnconfigured() {
		$data = ( new AmazeeIoSettings() )->update_connector_description( $this->connectorData() );

		$this->assertStringContainsString( 'Step 1', $data['connectors']['amazeeio']['description'] );
	}

	public function testSanitizeWarnsWhenEndpointUrlChanges() {
		$GLOBALS['wp_mock_options']['ai_provider_for_amazee_ai_settings'] = array( 'endpoint_url' => 'https://llm.us103.amazee.ai/v1' );

		$settings = new AmazeeIoSettings();
		$result   = $settings->sanitize_settings( array( 'endpoint_url' => 'https://llm.de102.amazee.ai/v1' ) );

		$this->assertSame( 'https://llm.de102.amazee.ai/v1', $result['endpoint_url'] );
		$this->assertCount( 1, $GLOBALS['wp_mock_settings_errors'] );
		$this->assertSame( 'warning', $GLOBALS['wp_mock_settings_errors'][0]['type'] );
	}

	public function testSanitizeStaysQuietWhenUrlUnchangedOrFirstSet() {
		$settings = new AmazeeIoSettings();

		// First-time set: no previous URL, no warning.
		$settings->sanitize_settings( array( 'endpoint_url' => 'https://llm.us103.amazee.ai/v1' ) );

		// Unchanged: same URL saved again, no warning.
		$GLOBALS['wp_mock_options']['ai_provider_for_amazee_ai_settings'] = array( 'endpoint_url' => 'https://llm.us103.amazee.ai/v1' );
		$settings->sanitize_settings( array( 'endpoint_url' => 'https://llm.us103.amazee.ai/v1' ) );

		$this->assertSame( array(), $GLOBALS['wp_mock_settings_errors'] );
	}
}
