<?php
/**
 * Fill an Elementor JSON template using data-customid attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTE_Template_Filler {
	private $bold_phone_links = false;
	private $underline_phone_links = false;
	private $bold_words = false;
	private $underline_words = false;
	private $format_words = array();
	private $format_words_pattern = '';
	private $node_index = array();

	/**
	 * @param array $options Formatting options.
	 */
	public function __construct( $options = array() ) {
		$this->bold_phone_links   = ! empty( $options['bold_phone_links'] );
		$this->underline_phone_links = ! empty( $options['underline_phone_links'] );
		$this->bold_words         = ! empty( $options['bold_words'] );
		$this->underline_words    = ! empty( $options['underline_words'] );
		$this->format_words = ! empty( $options['format_words'] ) && is_array( $options['format_words'] )
			? array_values( array_filter( array_map( 'trim', $options['format_words'] ) ) )
			: array();

		if ( $this->format_words && ( $this->bold_words || $this->underline_words ) ) {
			$patterns = array_map( function ( $word ) {
				return preg_quote( $word, '/' );
			}, $this->format_words );
			$this->format_words_pattern = '/(?<![\p{L}\p{N}_])(?:' . implode( '|', $patterns ) . ')(?![\p{L}\p{N}_])/iu';
		}
	}

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
		$this->node_index = array();
		$this->index_nodes( $content );

		$this->set_heading( self::HERO_TITLE, $outline['title'] );
		$this->set_editor( self::HERO_INTRO, $outline['intro'] );

		if ( ! empty( $outline['services_heading'] ) ) {
			$this->set_heading( self::SERVICES_HEADING, $outline['services_heading'] );
		}
		for ( $i = 0; $i < 4; $i++ ) {
			if ( empty( $outline['services'][ $i ] ) ) {
				break;
			}
			$this->set_icon_box(
				'Section2Content' . ( $i + 1 ),
				$outline['services'][ $i ]['title'],
				$outline['services'][ $i ]['body']
			);
		}

		$this->set_heading( self::WHY_HEADING, $outline['why_heading'] );
		for ( $i = 0; $i < 6; $i++ ) {
			if ( empty( $outline['why'][ $i ] ) ) {
				break;
			}
			$this->set_icon_box(
				'Section3Content' . ( $i + 1 ),
				$outline['why'][ $i ]['title'],
				$outline['why'][ $i ]['body']
			);
		}

		$this->set_heading( self::PROCESS_HEADING, $outline['process_heading'] );
		for ( $i = 0; $i < 6; $i++ ) {
			if ( empty( $outline['process'][ $i ] ) ) {
				break;
			}
			$n = $i + 1;
			$this->set_heading( 'Section4Content' . $n . 'H3', $outline['process'][ $i ]['title'] );
			$this->set_editor( 'Section4Content' . $n . 'Desc', array( $outline['process'][ $i ]['body'] ) );
		}

		$this->set_heading( self::FAQ_HEADING, $outline['faq_heading'] );
		$this->set_faqs( self::FAQ_TOGGLE, $outline['faqs'] );

		if ( ! empty( $outline['closing_heading'] ) ) {
			$this->set_heading( self::CLOSING_HEADING, $outline['closing_heading'] );
		}
		if ( ! empty( $outline['closing'] ) ) {
			$this->set_editor( self::CLOSING_INTRO, $outline['closing'] );
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
	 * @param string $custom_id
	 * @param string $title
	 * @return bool
	 */
	private function set_heading( $custom_id, $title ) {
		if ( '' === (string) $title || empty( $this->node_index[ $custom_id ] ) ) {
			return false;
		}

		$this->node_index[ $custom_id ]['settings']['title'] = $this->phone_links( $title );
		return true;
	}

	/**
	 * Index fillable Elementor nodes once so each replacement is O(1).
	 *
	 * @param array $nodes
	 */
	private function index_nodes( &$nodes ) {
		foreach ( $nodes as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$custom_id = $this->node_custom_id( $node );
			if ( '' !== $custom_id && ! isset( $this->node_index[ $custom_id ] ) ) {
				$this->node_index[ $custom_id ] =& $node;
			}

			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->index_nodes( $node['elements'] );
			}
		}
		unset( $node );
	}

	/**
	 * @param string $custom_id
	 * @param string $title
	 * @param string $body
	 * @return bool
	 */
	private function set_icon_box( $custom_id, $title, $body ) {
		if ( empty( $this->node_index[ $custom_id ] ) ) {
			return false;
		}

		$node =& $this->node_index[ $custom_id ];
		if ( '' !== (string) $title ) {
			$node['settings']['title_text'] = $this->phone_links( $title );
		}
		if ( '' !== (string) $body ) {
			$node['settings']['description_text'] = $this->phone_links( $body );
		}
		unset( $node );

		return true;
	}

	/**
	 * @param string   $custom_id
	 * @param string[] $paragraphs
	 * @return bool
	 */
	private function set_editor( $custom_id, $paragraphs ) {
		if ( empty( $paragraphs ) || empty( $this->node_index[ $custom_id ] ) ) {
			return false;
		}

		$html = '';
		foreach ( $paragraphs as $para ) {
			$para = trim( (string) $para );
			if ( '' !== $para ) {
				$html .= '<p>' . $this->phone_links( $para ) . '</p>';
			}
		}

		if ( '' === $html ) {
			return false;
		}

		$this->node_index[ $custom_id ]['settings']['editor'] = $html;
		return true;
	}

	/**
	 * Convert phone numbers to tel: links and apply the selected formatting.
	 *
	 * The href contains digits only. Custom words are entered as a
	 * comma-separated list and are formatted case-insensitively.
	 *
	 * @param string $text
	 * @return string
	 */
	private function phone_links( $text ) {
		$text = (string) $text;

		$pattern = '/(?<![\d])(?:\+?1[\s.\-]*)?(?:\(\d{3}\)[\s.\-]*|\d{3}[\s.\-]+)\d{3}[\s.\-]+\d{4}(?!\d)|(?<![\d])(?:\+?1[\s.\-]*)?\d{10}(?!\d)/u';

		$result = '';
		$offset = 0;

		if ( preg_match_all( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$phone       = $match[0];
				$byte_offset = $match[1];

				$result .= $this->format_words_in_plain_text(
					substr( $text, $offset, $byte_offset - $offset )
				);

				$digits = preg_replace( '/\D+/', '', $phone );
				if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
					$digits = substr( $digits, 1 );
				}

				if ( 10 === strlen( $digits ) ) {
					$visible = esc_html( $phone );
					$visible = $this->wrap_formatting( $visible, $this->bold_phone_links, $this->underline_phone_links );
					$result .= '<a href="tel:' . esc_attr( $digits ) . '">' . $visible . '</a>';
				} else {
					$result .= $this->format_words_in_plain_text( $phone );
				}

				$offset = $byte_offset + strlen( $phone );
			}
		}

		$result .= $this->format_words_in_plain_text( substr( $text, $offset ) );

		return $result;
	}

	/**
	 * Apply bold/underline to configured words or phrases in escaped plain text.
	 *
	 * The textbox accepts comma-separated words or phrases, e.g.
	 * "roof repair, emergency service, licensed".
	 *
	 * @param string $text
	 * @return string
	 */
	private function format_words_in_plain_text( $text ) {
		$escaped = esc_html( (string) $text );
		if ( '' === $this->format_words_pattern ) {
			return $escaped;
		}

		return preg_replace_callback(
			$this->format_words_pattern,
			function ( $match ) {
				return $this->wrap_formatting(
					$match[0],
					$this->bold_words,
					$this->underline_words
				);
			},
			$escaped
		);
	}

	/**
	 * Wrap text in strong/u tags according to selected options.
	 *
	 * @param string $text
	 * @param bool   $bold
	 * @param bool   $underline
	 * @return string
	 */
	private function wrap_formatting( $text, $bold, $underline ) {
		if ( $bold ) {
			$text = '<strong>' . $text . '</strong>';
		}
		if ( $underline ) {
			$text = '<u>' . $text . '</u>';
		}
		return $text;
	}


	/**
	 * @param string $custom_id
	 * @param array  $faqs
	 * @return bool
	 */
	private function set_faqs( $custom_id, $faqs ) {
		if ( empty( $faqs ) || empty( $this->node_index[ $custom_id ]['settings']['tabs'] ) || ! is_array( $this->node_index[ $custom_id ]['settings']['tabs'] ) ) {
			return false;
		}

		foreach ( $this->node_index[ $custom_id ]['settings']['tabs'] as $t => $tab ) {
			if ( empty( $faqs[ $t ] ) ) {
			break;
			}
			if ( ! empty( $faqs[ $t ]['q'] ) ) {
				$this->node_index[ $custom_id ]['settings']['tabs'][ $t ]['tab_title'] = $this->phone_links( $faqs[ $t ]['q'] );
			}
			if ( ! empty( $faqs[ $t ]['a'] ) ) {
				$this->node_index[ $custom_id ]['settings']['tabs'][ $t ]['tab_content'] = '<p>' . $this->phone_links( $faqs[ $t ]['a'] ) . '</p>';
			}
		}

		return true;
	}

}
