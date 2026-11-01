<?php
/**
 * Amazee.io embedding generation model.
 *
 * @package Amazee\AiProvider
 */

declare( strict_types=1 );

namespace Amazee\AiProvider;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Embedding;
use WordPress\AiClient\Results\DTO\EmbeddingResult;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * Generates embeddings via the OpenAI-compatible `embeddings` endpoint.
 *
 * Only loaded when the AI client ships embedding support (php-ai-client 1.4+,
 * or the WordPress AI plugin's SDK overlay on older cores).
 */
class AmazeeIoEmbeddingModel extends AmazeeIoApiModel implements EmbeddingGenerationModelInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param array $inputs Text parts to embed.
	 * @throws InvalidArgumentException If an input is not non-empty text.
	 * @throws ResponseException If the response holds no embeddings.
	 */
	public function generateEmbeddingResult( array $inputs ): EmbeddingResult {
		$texts = array();
		foreach ( $inputs as $index => $part ) {
			$text = $part instanceof MessagePart ? $part->getText() : null;
			if ( null === $text || '' === trim( $text ) ) {
				throw new InvalidArgumentException( sprintf( 'Embedding input %d must be non-empty text.', (int) $index ) );
			}
			$texts[] = $text;
		}
		if ( array() === $texts ) {
			throw new InvalidArgumentException( 'At least one embedding input is required.' );
		}

		$params = array(
			'model' => $this->metadata()->getId(),
			'input' => $texts,
		);
		if ( null !== $this->getConfig()->getDimensions() ) {
			$params['dimensions'] = $this->getConfig()->getDimensions();
		}

		$data = $this->post( 'embeddings', $this->withCustomOptions( $params ) )->getData();

		$embeddings = array();
		foreach ( (array) ( $data['data'] ?? array() ) as $node ) {
			$vector = is_array( $node ) ? ( $node['embedding'] ?? null ) : null;
			if ( ! is_array( $vector ) || array() === $vector ) {
				throw ResponseException::fromInvalidData( 'amazee.ai', 'data.embedding', 'Expected an embedding vector.' );
			}
			$embeddings[] = new Embedding( array_map( 'floatval', array_values( $vector ) ), count( $vector ) );
		}
		if ( array() === $embeddings ) {
			throw ResponseException::fromMissingData( 'amazee.ai', 'data' );
		}

		$usage  = is_array( $data['usage'] ?? null ) ? $data['usage'] : array();
		$prompt = (int) ( $usage['prompt_tokens'] ?? 0 );

		return new EmbeddingResult(
			is_string( $data['id'] ?? null ) ? $data['id'] : uniqid( 'amazeeio-embedding-', true ),
			$embeddings,
			$embeddings[0]->getDimensions(),
			new TokenUsage( $prompt, 0, (int) ( $usage['total_tokens'] ?? $prompt ) ),
			$this->providerMetadata(),
			$this->metadata()
		);
	}
}
