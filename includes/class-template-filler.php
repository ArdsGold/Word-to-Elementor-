<?php
/**
 * Fill the bundled Elementor JSON with parsed Word content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTE_Template_Filler {
	const HERO_TITLE      = '775791e4';
	const HERO_SUBTITLE   = '16470985';
	const HERO_INTRO      = '13c63b7c';
	const WHY_HEADING     = '79b98cd1';
	const PROCESS_HEADING = '37a26e4c';
	const FAQ_HEADING     = '4a3fd35c';
	const FAQ_TOGGLE      = 'ff78da7';
	const CLOSING_HEADING = '67663685';
	const CLOSING_INTRO   = '67af4214';

	/**
	 * @var string[]
	 */
	private $service_ids = array( '2ab919e8', '3363aefc', '3caf3129', '7dca336f' );

	/**
	 * @var string[]
	 */
	private $why_ids = array( 'eda1818', '17dfeafe', '5954d86d', '6c3a305e', '707225c1', '3ef8494b' );

	/**
	 * @var array<int, array{heading:string,body:string}>
	 */
	private $process_ids = array(
		array( 'heading' => '4f770113', 'body' => '3d139e77' ),
		array( 'heading' => '7611f7a3', 'body' => '4ad514a9' ),
		array( 'heading' => '246e5e43', 'body' => '2182194d' ),
		array( 'heading' => '345afdc7', 'body' => '4cfb9cb1' ),
		array( 'heading' => '18fa8903', 'body' => '8b446e4' ),
		array( 'heading' => '2d8e5c4a', 'body' => '511c11bc' ),
	);

	/**
	 * @param array $outline
	 * @return array{content:array,page_settings:array,title:string}
	 * @throws Exception
	 */
	public function fill( $outline ) {
		$path = WTE_PLUGIN_DIR . 'templates/AutomationTestTemplate2.0.json';
		if ( ! is_readable( $path ) ) {
			throw new Exception( __( 'The Elementor template file is missing.', 'word-to-elementor-wf' ) );
		}

		$raw  = file_get_contents( $path );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['content'] ) ) {
			throw new Exception( __( 'The Elementor template JSON is invalid.', 'word-to-elementor-wf' ) );
		}

		$index = array();
		$this->index_nodes( $data['content'], $index );

		$this->set_heading( $index, self::HERO_TITLE, $outline['title'] );
		$this->set_heading( $index, self::HERO_SUBTITLE, $outline['subtitle'] );
		$this->set_editor( $index, self::HERO_INTRO, $outline['intro'] );

		foreach ( $this->service_ids as $i => $id ) {
			if ( empty( $outline['services'][ $i ] ) ) {
				break;
			}
			$this->set_icon_box( $index, $id, $outline['services'][ $i ]['title'], $outline['services'][ $i ]['body'] );
		}

		$this->set_heading( $index, self::WHY_HEADING, $outline['why_heading'] );
		foreach ( $this->why_ids as $i => $id ) {
			if ( empty( $outline['why'][ $i ] ) ) {
				break;
			}
			$this->set_icon_box( $index, $id, $outline['why'][ $i ]['title'], $outline['why'][ $i ]['body'] );
		}

		$this->set_heading( $index, self::PROCESS_HEADING, $outline['process_heading'] );
		foreach ( $this->process_ids as $i => $pair ) {
			if ( empty( $outline['process'][ $i ] ) ) {
				break;
			}
			$this->set_heading( $index, $pair['heading'], $outline['process'][ $i ]['title'] );
			$this->set_editor( $index, $pair['body'], array( $outline['process'][ $i ]['body'] ) );
		}

		$this->set_heading( $index, self::FAQ_HEADING, $outline['faq_heading'] );
		$this->set_faqs( $index, self::FAQ_TOGGLE, $outline['faqs'] );

		$this->set_heading( $index, self::CLOSING_HEADING, $outline['closing_heading'] );
		$this->set_editor( $index, self::CLOSING_INTRO, $outline['closing'] );

		$page_settings = isset( $data['page_settings'] ) && is_array( $data['page_settings'] )
			? $data['page_settings']
			: array( 'hide_title' => 'yes' );

		return array(
			'content'        => $data['content'],
			'page_settings'  => $page_settings,
			'title'          => $outline['title'],
		);
	}

	/**
	 * @param array $nodes
	 * @param array $index
	 */
	private function index_nodes( &$nodes, &$index ) {
		foreach ( $nodes as &$node ) {
			if ( ! empty( $node['id'] ) ) {
				$index[ $node['id'] ] = &$node;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->index_nodes( $node['elements'], $index );
			}
		}
		unset( $node );
	}

	/**
	 * @param array  $index
	 * @param string $id
	 * @param string $title
	 */
	private function set_heading( &$index, $id, $title ) {
		if ( '' === (string) $title || empty( $index[ $id ] ) ) {
			return;
		}
		$index[ $id ]['settings']['title'] = $title;
	}

	/**
	 * @param array  $index
	 * @param string $id
	 * @param string $title
	 * @param string $body
	 */
	private function set_icon_box( &$index, $id, $title, $body ) {
		if ( empty( $index[ $id ] ) ) {
			return;
		}
		if ( '' !== (string) $title ) {
			$index[ $id ]['settings']['title_text'] = $title;
		}
		if ( '' !== (string) $body ) {
			$index[ $id ]['settings']['description_text'] = $body;
		}
	}

	/**
	 * @param array    $index
	 * @param string   $id
	 * @param string[] $paragraphs
	 */
	private function set_editor( &$index, $id, $paragraphs ) {
		if ( empty( $index[ $id ] ) || empty( $paragraphs ) ) {
			return;
		}
		$html = '';
		foreach ( $paragraphs as $para ) {
			$para = trim( (string) $para );
			if ( '' === $para ) {
				continue;
			}
			$html .= '<p>' . esc_html( $para ) . '</p>';
		}
		if ( '' !== $html ) {
			$index[ $id ]['settings']['editor'] = $html;
		}
	}

	/**
	 * @param array $index
	 * @param string $id
	 * @param array $faqs
	 */
	private function set_faqs( &$index, $id, $faqs ) {
		if ( empty( $index[ $id ] ) || empty( $faqs ) ) {
			return;
		}
		if ( empty( $index[ $id ]['settings']['tabs'] ) || ! is_array( $index[ $id ]['settings']['tabs'] ) ) {
			return;
		}
		foreach ( $index[ $id ]['settings']['tabs'] as $i => &$tab ) {
			if ( empty( $faqs[ $i ] ) ) {
				break;
			}
			if ( ! empty( $faqs[ $i ]['q'] ) ) {
				$tab['tab_title'] = $faqs[ $i ]['q'];
			}
			if ( ! empty( $faqs[ $i ]['a'] ) ) {
				$tab['tab_content'] = '<p>' . esc_html( $faqs[ $i ]['a'] ) . '</p>';
			}
		}
	}
}
