<?php
/**
 * PHPUnit Bootstrap file for Amazee.io AI Provider tests.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define ABSPATH and other WP constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

// Global variable storage for mock options.
$GLOBALS['wp_mock_options'] = array();
$GLOBALS['wp_mock_constants'] = array();
$GLOBALS['wp_mock_transients'] = array();

// Mock WordPress functions.
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $GLOBALS['wp_mock_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['wp_mock_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $string, $allowed_html, $allowed_protocols = array() ) {
		return $string;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain ) {
		return $text;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return $GLOBALS['wp_mock_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['wp_mock_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['wp_mock_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'wp_get_wp_version' ) ) {
	function wp_get_wp_version() {
		return '7.0';
	}
}
