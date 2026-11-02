<?php
/**
 * Amazee.io text-to-speech model.
 *
 * @package Amazee\AiProvider
 */

declare( strict_types=1 );

namespace Amazee\AiProvider;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts\TextToSpeechConversionModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Converts text to speech via the OpenAI-compatible `audio/speech` endpoint.
 */
class AmazeeIoSpeechModel extends AmazeeIoApiModel implements TextToSpeechConversionModelInterface {

	/**
	 * Output MIME types mapped to the endpoint's `response_format` values.
	 */
	public const FORMATS = array(
		'audio/mpeg' => 'mp3',
		'audio/ogg'  => 'opus',
		'audio/wav'  => 'wav',
		'audio/flac' => 'flac',
		'audio/aac'  => 'aac',
	);

	/**
	 * Voice used when the caller does not choose one.
	 */
	private const DEFAULT_VOICE = 'alloy';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $prompt Messages whose text is spoken.
	 * @throws InvalidArgumentException If there is no text or the MIME type is unsupported.
	 * @throws ResponseException If the response holds no audio.
	 */
	public function convertTextToSpeechResult( array $prompt ): GenerativeAiResult {
		$texts = array();
		foreach ( $prompt as $message ) {
			foreach ( $message->getParts() as $part ) {
				if ( null !== $part->getText() ) {
					$texts[] = $part->getText();
				}
			}
		}
		$text = implode( "\n", $texts );
		if ( '' === trim( $text ) ) {
			throw new InvalidArgumentException( 'The prompt must contain text to convert to speech.' );
		}

		$config    = $this->getConfig();
		$mime_type = $config->getOutputMimeType() ?? 'audio/mpeg';
		if ( ! isset( self::FORMATS[ $mime_type ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Unsupported text-to-speech output MIME type "%s".', esc_html( $mime_type ) ) );
		}

		$params = array(
			'model'           => $this->metadata()->getId(),
			'input'           => $text,
			'voice'           => $config->getOutputSpeechVoice() ?? self::DEFAULT_VOICE,
			'response_format' => self::FORMATS[ $mime_type ],
		);

		$audio = $this->post( 'audio/speech', $this->withCustomOptions( $params ) )->getBody();
		if ( null === $audio || '' === $audio ) {
			throw ResponseException::fromMissingData( 'amazee.ai', 'body' );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- The SDK's File takes binary audio as base64.
		$message = new Message( MessageRoleEnum::model(), array( new MessagePart( new File( base64_encode( $audio ), $mime_type ) ) ) );

		return new GenerativeAiResult(
			uniqid( 'amazeeio-tts-', true ),
			array( new Candidate( $message, FinishReasonEnum::stop() ) ),
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata()
		);
	}
}
