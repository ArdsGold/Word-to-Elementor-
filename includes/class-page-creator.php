<?php
/**
 * Create an Elementor draft page from filled template data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTE_Page_Creator {
	/**
	 * @param array  $filled  Result from WTE_Template_Filler::fill().
	 * @param string $title   Page title.
	 * @param bool   $publish Whether to publish immediately.
	 * @return int Post ID.
	 * @throws Exception
	 */
	public function create( $filled, $title, $publish = false ) {
		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => $publish ? 'publish' : 'draft',
				'post_type'   => 'page',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		$version = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0';

		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $post_id, '_elementor_version', $version );
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $filled['content'] ) ) );
		update_post_meta( $post_id, '_elementor_page_settings', $filled['page_settings'] );

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( $post_id );
			if ( $document ) {
				$document->save_template_type();
			}
		}

		// Enforce the requested final status after Elementor metadata work.
		// Elementor initialization can perform a save, so explicitly restore the
		// requested publish/draft status as the final operation.
		$final_status = $publish ? 'publish' : 'draft';
		if ( get_post_status( $post_id ) !== $final_status ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $final_status,
				)
			);
		}

		return (int) $post_id;
	}
}
