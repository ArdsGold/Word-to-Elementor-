<?php
/**
 * Fill an Elementor JSON template using data-customid attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTE_Template_Filler {
	const HERO_TITLE       = 'HeroH1';
	const HERO_INTRO       = 'HeroP';
	const SERVICES_HEADING = 'Section2H2';
	const WHY_HEADING      = 'Section3H2';
	const PROCESS_HEADING  = 'Section4H2';
	const FAQ_HEADING      = 'Section5H2';
	const FAQ_TOGGLE       = 'Section5Content';
	const CLOSING_HEADING  = 'Section6H2';
	const CLOSING_INTRO    = 'Section6P';

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

		$content = &$data['content'];

		$this->set_heading( $content, self::HERO_TITLE, $outline['title'] );
		$this->set_editor( $content, self::HERO_INTRO, $outline['intro'] );

		if ( ! empty( $outline['services_heading'] ) ) {
			$this->set_heading( $content, self::SERVICES_HEADING, $outline['services_heading'] );
		}
		for ( $i = 0; $i < 4; $i++ ) {
			if ( empty( $outline['services'][ $i ] ) ) {
				break;
			}
			$this->set_icon_box(
				$content,
				'Section2Content' . ( $i + 1 ),
				$outline['services'][ $i ]['title'],
				$outline['services'][ $i ]['body']
			);
		}

		$this->set_heading( $content, self::WHY_HEADING, $outline['why_heading'] );
		for ( $i = 0; $i < 6; $i++ ) {
			if ( empty( $outline['why'][ $i ] ) ) {
				break;
			}
			$this->set_icon_box(
				$content,
				'Section3Content' . ( $i + 1 ),
				$outline['why'][ $i ]['title'],
				$outline['why'][ $i ]['body']
			);
		}

		$this->set_heading( $content, self::PROCESS_HEADING, $outline['process_heading'] );
		for ( $i = 0; $i < 6; $i++ ) {
			if ( empty( $outline['process'][ $i ] ) ) {
				break;
			}
			$n = $i + 1;
			$this->set_heading( $content, 'Section4Content' . $n . 'H3', $outline['process'][ $i ]['title'] );
			$this->set_editor( $content, 'Section4Content' . $n . 'Desc', array( $outline['process'][ $i ]['body'] ) );
		}

		$this->set_heading( $content, self::FAQ_HEADING, $outline['faq_heading'] );
		$this->set_faqs( $content, self::FAQ_TOGGLE, $outline['faqs'] );

		if ( ! empty( $outline['closing_heading'] ) ) {
			$this->set_heading( $content, self::CLOSING_HEADING, $outline['closing_heading'] );
		}
		if ( ! empty( $outline['closing'] ) ) {
			$this->set_editor( $content, self::CLOSING_INTRO, $outline['closing'] );
		}

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
	 * @param array  $nodes
	 * @param string $custom_id
	 * @param string $title
	 * @return bool
	 */
	private function set_heading( &$nodes, $custom_id, $title ) {
		if ( '' === (string) $title ) {
			return false;
		}
		foreach ( array_keys( $nodes ) as $i ) {
			if ( $this->node_custom_id( $nodes[ $i ] ) === $custom_id ) {
				$nodes[ $i ]['settings']['title'] = $this->phone_links( $title );
				return true;
			}
			if ( ! empty( $nodes[ $i ]['elements'] ) && is_array( $nodes[ $i ]['elements'] ) ) {
				if ( $this->set_heading( $nodes[ $i ]['elements'], $custom_id, $title ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array  $nodes
	 * @param string $custom_id
	 * @param string $title
	 * @param string $body
	 * @return bool
	 */
	private function set_icon_box( &$nodes, $custom_id, $title, $body ) {
		foreach ( array_keys( $nodes ) as $i ) {
			if ( $this->node_custom_id( $nodes[ $i ] ) === $custom_id ) {
				if ( '' !== (string) $title ) {
					$nodes[ $i ]['settings']['title_text'] = $this->phone_links( $title );
				}
				if ( '' !== (string) $body ) {
					$nodes[ $i ]['settings']['description_text'] = $this->phone_links( $body );
				}
				return true;
			}
			if ( ! empty( $nodes[ $i ]['elements'] ) && is_array( $nodes[ $i ]['elements'] ) ) {
				if ( $this->set_icon_box( $nodes[ $i ]['elements'], $custom_id, $title, $body ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array    $nodes
	 * @param string   $custom_id
	 * @param string[] $paragraphs
	 * @return bool
	 */
	private function set_editor( &$nodes, $custom_id, $paragraphs ) {
		if ( empty( $paragraphs ) ) {
			return false;
		}
		$html = '';
		foreach ( $paragraphs as $para ) {
			$para = trim( (string) $para );
			if ( '' === $para ) {
				continue;
			}
			$html .= '<p>' . $this->phone_links( $para ) . '</p>';
		}
		if ( '' === $html ) {
			return false;
		}
		foreach ( array_keys( $nodes ) as $i ) {
			if ( $this->node_custom_id( $nodes[ $i ] ) === $custom_id ) {
				$nodes[ $i ]['settings']['editor'] = $html;
				return true;
			}
			if ( ! empty( $nodes[ $i ]['elements'] ) && is_array( $nodes[ $i ]['elements'] ) ) {
				if ( $this->set_editor( $nodes[ $i ]['elements'], $custom_id, $paragraphs ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Convert common US phone-number formats in plain text to tel: links.
	 *
	 * Supported examples include:
	 * (305) 555-5555
	 * +1 305 555 5555
	 * 305-555-5555
	 * 305 555 5555
	 * 305.555.5555
	 * 3055555555
	 * 1-305-555-5555
	 *
	 * The href contains digits only, e.g. tel:3055555555.
	 *
	 * @param string $text
	 * @return string
	 */
	private function phone_links( $text ) {
		$text = (string) $text;

		/*
		 * Match either:
		 *  - an optional country code (1 / +1) followed by a 10-digit US number, or
		 *  - a plain 10-digit US number.
		 *
		 * Separators between digit groups may be spaces, hyphens, dots, or
		 * parentheses. Boundaries prevent matching a substring of a longer number.
		 */
		$pattern = '/(?<![\\d])(?:\\+?1[\\s.\\-]*)?(?:\\(\\d{3}\\)[\\s.\\-]*|\\d{3}[\\s.\\-]+)\\d{3}[\\s.\\-]+\\d{4}(?!\\d)|(?<![\\d])(?:\\+?1[\\s.\\-]*)?\\d{10}(?!\\d)/u';

		$result = '';
		$offset = 0;

		if ( preg_match_all( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
			$phone = $match[0];
			$byte_offset = $match[1];

			$result .= esc_html( substr( $text, $offset, $byte_offset - $offset ) );

			$digits = preg_replace( '/\\D+/', '', $phone );
			if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
				$digits = substr( $digits, 1 );
			}

			if ( 10 === strlen( $digits ) ) {
				$result .= '<a href="tel:' . esc_attr( $digits ) . '">' . esc_html( $phone ) . '</a>';
			} else {
				$result .= esc_html( $phone );
			}

			$offset = $byte_offset + strlen( $phone );
			}
		}

		$result .= esc_html( substr( $text, $offset ) );

		return $result;
	}

	/**
	 * @param array  $nodes
	 * @param string $custom_id
	 * @param array  $faqs
	 * @return bool
	 */
	private function set_faqs( &$nodes, $custom_id, $faqs ) {
		if ( empty( $faqs ) ) {
			return false;
		}
		foreach ( array_keys( $nodes ) as $i ) {
			if ( $this->node_custom_id( $nodes[ $i ] ) === $custom_id ) {
				if ( empty( $nodes[ $i ]['settings']['tabs'] ) || ! is_array( $nodes[ $i ]['settings']['tabs'] ) ) {
					return false;
				}
				foreach ( $nodes[ $i ]['settings']['tabs'] as $t => $tab ) {
					if ( empty( $faqs[ $t ] ) ) {
						break;
					}
					if ( ! empty( $faqs[ $t ]['q'] ) ) {
						$nodes[ $i ]['settings']['tabs'][ $t ]['tab_title'] = $this->phone_links( $faqs[ $t ]['q'] );
					}
					if ( ! empty( $faqs[ $t ]['a'] ) ) {
						$nodes[ $i ]['settings']['tabs'][ $t ]['tab_content'] = '<p>' . $this->phone_links( $faqs[ $t ]['a'] ) . '</p>';
					}
				}
				return true;
			}
			if ( ! empty( $nodes[ $i ]['elements'] ) && is_array( $nodes[ $i ]['elements'] ) ) {
				if ( $this->set_faqs( $nodes[ $i ]['elements'], $custom_id, $faqs ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
