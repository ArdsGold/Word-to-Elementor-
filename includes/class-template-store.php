<?php
/**
 * Resolve bundled vs uploaded Elementor template JSON.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTE_Template_Store {
	const OPTION_CUSTOM = 'wte_has_custom_template';

	/**
	 * Bundled default export.
	 */
	public static function bundled_path() {
		return WTE_PLUGIN_DIR . 'templates/AutomationTestTemplate2.0.json';
	}

	/**
	 * Writable copy in uploads, if present.
	 */
	public static function custom_path() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		return trailingslashit( $uploads['basedir'] ) . 'word-to-elementor-wf/custom-template.json';
	}

	/**
	 * Active template path (custom upload wins).
	 */
	public static function active_path() {
		$custom = self::custom_path();
		if ( $custom && is_readable( $custom ) && get_option( self::OPTION_CUSTOM ) ) {
			return $custom;
		}
		return self::bundled_path();
	}

	public static function using_custom() {
		$custom = self::custom_path();
		return (bool) ( $custom && is_readable( $custom ) && get_option( self::OPTION_CUSTOM ) );
	}

	/**
	 * @param string $tmp_path Uploaded file tmp path.
	 * @throws Exception
	 */
	public static function save_upload( $tmp_path ) {
		$raw  = file_get_contents( $tmp_path );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['content'] ) || ! is_array( $data['content'] ) ) {
			throw new Exception( __( 'That file is not a valid Elementor template JSON (missing content).', 'word-to-elementor-wf' ) );
		}

		$dest = self::custom_path();
		if ( ! $dest ) {
			throw new Exception( __( 'Could not determine the uploads directory.', 'word-to-elementor-wf' ) );
		}

		$dir = dirname( $dest );
		if ( ! wp_mkdir_p( $dir ) ) {
			throw new Exception( __( 'Could not create the template uploads folder.', 'word-to-elementor-wf' ) );
		}

		if ( false === file_put_contents( $dest, $raw ) ) {
			throw new Exception( __( 'Could not save the uploaded template.', 'word-to-elementor-wf' ) );
		}

		update_option( self::OPTION_CUSTOM, 1, false );
	}

	public static function reset() {
		$custom = self::custom_path();
		if ( $custom && file_exists( $custom ) ) {
			wp_delete_file( $custom );
		}
		delete_option( self::OPTION_CUSTOM );
	}
}
