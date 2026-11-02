<?php

namespace Amazee\AiProvider\Tests;

use Amazee\AiProvider\AmazeeIoEmbeddingModel;
use Amazee\AiProvider\AmazeeIoSpeechModel;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

class AmazeeIoApiModelTest extends TestCase {

	/** @var Request|null */
	private $sent;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_mock_options']    = array();
		$GLOBALS['wp_mock_transients'] = array();
		amazeeio_test_set_credential( 'https://llm.us103.amazee.ai/v1|test-token' );
		$this->sent = null;
	}

	/**
	 * @template T of \Amazee\AiProvider\AmazeeIoApiModel
	 * @param class-string<T> $class
	 * @return T
	 */
	private function model( string $class, Response $response ) {
		$model = new $class(
			new ModelMetadata( 'm', 'm', array(), array() ),
			new ProviderMetadata( 'amazeeio', 'amazee.ai', ProviderTypeEnum::server() )
		);

		$transporter = $this->createMock( HttpTransporterInterface::class );
		$transporter->method( 'send' )->willReturnCallback(
			function ( Request $request ) use ( $response ) {
				$this->sent = $request;
				return $response;
			}
		);
		$model->setHttpTransporter( $transporter );

		return $model;
	}

	public function testEmbeddingRequestAndResult() {
		$body  = json_encode(
			array(
				'data'  => array( array( 'embedding' => array( 0.1, 0.2, 0.3 ) ), array( 'embedding' => array( 1, 0, 0 ) ) ),
				'usage' => array( 'prompt_tokens' => 4, 'total_tokens' => 4 ),
			)
		);
		$model = $this->model( AmazeeIoEmbeddingModel::class, new Response( 200, array(), $body ) );
		$model->setConfig( ModelConfig::fromArray( array( 'dimensions' => 3 ) ) );

		$result = $model->generateEmbeddingResult( array( new MessagePart( 'a' ), new MessagePart( 'b' ) ) );

		$this->assertSame( 'https://llm.us103.amazee.ai/v1/embeddings', $this->sent->getUri() );
		$this->assertSame( 'Bearer test-token', $this->sent->getHeaderAsString( 'Authorization' ) );
		$this->assertSame( array( 'model' => 'm', 'input' => array( 'a', 'b' ), 'dimensions' => 3 ), $this->sent->getData() );
		$this->assertCount( 2, $result->getEmbeddings() );
		$this->assertSame( 3, $result->getDimensions() );
		$this->assertSame( array( 1.0, 0.0, 0.0 ), $result->getEmbeddings()[1]->getValues() );
	}

	public function testEmbeddingMapsBudgetError() {
		$model = $this->model(
			AmazeeIoEmbeddingModel::class,
			new Response( 400, array(), '{"error":{"message":"Budget has been exceeded!"}}' )
		);

		$this->expectException( ClientException::class );
		$this->expectExceptionMessageMatches( '/amazee\.ai budget has been exceeded/' );
		$model->generateEmbeddingResult( array( new MessagePart( 'a' ) ) );
	}

	public function testSpeechRequestAndResult() {
		$model = $this->model( AmazeeIoSpeechModel::class, new Response( 200, array(), 'RIFFaudio' ) );
		$model->setConfig( ModelConfig::fromArray( array( 'outputMimeType' => 'audio/wav', 'outputSpeechVoice' => 'nova' ) ) );

		$result = $model->convertTextToSpeechResult(
			array( new Message( MessageRoleEnum::user(), array( new MessagePart( 'Hello' ) ) ) )
		);

		$this->assertSame( 'https://llm.us103.amazee.ai/v1/audio/speech', $this->sent->getUri() );
		$this->assertSame(
			array( 'model' => 'm', 'input' => 'Hello', 'voice' => 'nova', 'response_format' => 'wav' ),
			$this->sent->getData()
		);
		$file = $result->toFile();
		$this->assertSame( 'audio/wav', $file->getMimeType() );
		$this->assertSame( base64_encode( 'RIFFaudio' ), $file->getBase64Data() );
	}
}
