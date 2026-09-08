<?php
/**
 * Fill an Elementor JSON template using data-customid attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTE_Template_Filler {
	const HERO_TITLE      = 'HeroH1';
	const HERO_INTRO      = 'HeroP';
	const SERVICES_HEADING = 'Section2H2';
	const WHY_HEADING     = 'Section3H2';
	const PROCESS_HEADING = 'Section4H2';
	const FAQ_HEADING     = 'Section5H2';
	const FAQ_TOGGLE      = 'Section5Content';
	const CLOSING_HEADING = 'Section6H2';
	const CLOSING_INTRO   = 'Section6P';

	/**
	 * @param array $outline
	 * @return array{content:array,page_settings:array,title:string}
	 * @throws Exception
	 */
	public function fill( $outline ) {
		$path = WTE_Template_Store::active_path();
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
		$this->set_editor( $index, self::HERO_INTRO, $outline['intro'] );

		if ( ! empty( $outline['services_heading'] ) ) {
			$this->set_heading( $index, self::SERVICES_HEADING, $outline['services_heading'] );
		}
		for ( $i = 0; $i < 4; $i++ ) {
			if ( empty( $outline['services'][ $i ] ) ) {
				break;
			}
			$this->set_icon_box(
				$index,
				'Section2Content' . ( $i + 1 ),
				$outline['services'][ $i ]['title'],
				$outline['services'][ $i ]['body']
			);
		}

		$this->set_heading( $index, self::WHY_HEADING, $outline['why_heading'] );
		for ( $i = 0; $i < 6; $i++ ) {
			if ( empty( $outline['why'][ $i ] ) ) {
				break;
			}
			$this->set_icon_box(
				$index,
				'Section3Content' . ( $i + 1 ),
				$outline['why'][ $i ]['title'],
				$outline['why'][ $i ]['body']
			);
		}

		$this->set_heading( $index, self::PROCESS_HEADING, $outline['process_heading'] );
		for ( $i = 0; $i < 6; $i++ ) {
			if ( empty( $outline['process'][ $i ] ) ) {
				break;
			}
			$n = $i + 1;
			$this->set_heading( $index, 'Section4Content' . $n . 'H3', $outline['process'][ $i ]['title'] );
			$this->set_editor( $index, 'Section4Content' . $n . 'Desc', array( $outline['process'][ $i ]['body'] ) );
		}

		$this->set_heading( $index, self::FAQ_HEADING, $outline['faq_heading'] );
		$this->set_faqs( $index, self::FAQ_TOGGLE, $outline['faqs'] );

		$this->set_heading( $index, self::CLOSING_HEADING, $outline['closing_heading'] );
		$this->set_editor( $index, self::CLOSING_INTRO, $outline['closing'] );

		$page_settings = isset( $data['page_settings'] ) && is_array( $data['page_settings'] )
			? $data['page_settings']
			: array( 'hide_title' => 'yes' );

		return array(
			'content'       => $data['content'],
			'page_settings' => $page_settings,
			'title'         => $outline['title'],
		);
	}

	/**
	 * Index widgets by data-customid from Elementor Advanced attributes.
	 *
	 * @param array $nodes
	 * @param array $index
	 */
	private function index_nodes( &$nodes, &$index ) {
		foreach ( $nodes as &$node ) {
			$custom_id = $this->node_custom_id( $node );
			if ( '' !== $custom_id ) {
				$index[ $custom_id ] = &$node;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->index_nodes( $node['elements'], $index );
			}
		}
		unset( $node );
	}

	/**
	 * @param array $node
	 * @return string
	 */
	private function node_custom_id( $node ) {
		if ( empty( $node['settings'] ) || ! is_array( $node['settings'] ) ) {
			return '';
		}
		$raw = '';
		if ( ! empty( $node['settings']['_attributes'] ) ) {
			$raw = $node['settings']['_attributes'];
		} elseif ( ! empty( $node['settings']['_element_custom_attributes'] ) ) {
			$raw = $node['settings']['_element_custom_attributes'];
		}
		if ( is_array( $raw ) ) {
			foreach ( $raw as $row ) {
				$key = isset( $row['key'] ) ? $row['key'] : ( isset( $row['name'] ) ? $row['name'] : '' );
				$val = isset( $row['value'] ) ? $row['value'] : '';
				if ( 'data-customid' === strtolower( (string) $key ) ) {
					return trim( (string) $val );
				}
			}
			return '';
		}

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = explode( '|', $line, 2 );
			if ( 2 === count( $parts ) && 'data-customid' === strtolower( trim( $parts[0] ) ) ) {
				return trim( $parts[1] );
			}
		}

		return '';
	}

	/**
	 * @param array  $index
	 * @param string $custom_id
	 * @param string $title
	 */
	private function set_heading( &$index, $custom_id, $title ) {
		if ( '' === (string) $title || empty( $index[ $custom_id ] ) ) {
			return;
		}
		$index[ $custom_id ]['settings']['title'] = $title;
	}

	/**
	 * @param array  $index
	 * @param string $custom_id
	 * @param string $title
	 * @param string $body
	 */
	private function set_icon_box( &$index, $custom_id, $title, $body ) {
		if ( empty( $index[ $custom_id ] ) ) {
			return;
		}
		if ( '' !== (string) $title ) {
			$index[ $custom_id ]['settings']['title_text'] = $title;
		}
		if ( '' !== (string) $body ) {
			$index[ $custom_id ]['settings']['description_text'] = $body;
		}
	}

	/**
	 * @param array    $index
	 * @param string   $custom_id
	 * @param string[] $paragraphs
	 */
	private function set_editor( &$index, $custom_id, $paragraphs ) {
		if ( empty( $index[ $custom_id ] ) || empty( $paragraphs ) ) {
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
			$index[ $custom_id ]['settings']['editor'] = $html;
		}
	}

	/**
	 * @param array  $index
	 * @param string $custom_id
	 * @param array  $faqs
	 */
	private function set_faqs( &$index, $custom_id, $faqs ) {
		if ( empty( $index[ $custom_id ] ) || empty( $faqs ) ) {
			return;
		}
		if ( empty( $index[ $custom_id ]['settings']['tabs'] ) || ! is_array( $index[ $custom_id ]['settings']['tabs'] ) ) {
			return;
		}
		foreach ( $index[ $custom_id ]['settings']['tabs'] as $i => &$tab ) {
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
		unset( $tab );
	}
}
