<?php
/**
 * PHPStan stub for the WP_Connector_Registry class introduced in WordPress
 * 7.0, which the WordPress stubs bundled with szepeviktor/phpstan-wordpress
 * do not know yet. Signatures mirror wp-includes/class-wp-connector-registry.php.
 *
 * @package Amazee\AiProvider
 */

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Commenting.ClassComment.Missing
class WP_Connector_Registry {

	/**
	 * Registers a connector.
	 *
	 * @param string               $id   Connector identifier.
	 * @param array<string, mixed> $args Connector data.
	 * @return array<string, mixed>|null The registered connector data, or null on failure.
	 */
	public function register( string $id, array $args ): ?array {}

	/**
	 * Unregisters a connector.
	 *
	 * @param string $id Connector identifier.
	 * @return array<string, mixed>|null The unregistered connector data, or null if not registered.
	 */
	public function unregister( string $id ): ?array {}

	/**
	 * Checks whether a connector is registered.
	 *
	 * @param string $id Connector identifier.
	 */
	public function is_registered( string $id ): bool {}
}
