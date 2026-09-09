<?php
/**
 * Parse a .docx outline into sectioned content for the Elementor template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTE_Docx_Parser {
	const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

	/**
	 * @param string $file_path Absolute path to a .docx file.
	 * @return array Parsed outline.
	 * @throws Exception
	 */
	public function parse( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Exception( __( 'PHP ZipArchive is required to read .docx files.', 'word-to-elementor-wf' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			throw new Exception( __( 'Could not open the uploaded .docx file.', 'word-to-elementor-wf' ) );
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml || '' === $xml ) {
			throw new Exception( __( 'The .docx file is missing document.xml.', 'word-to-elementor-wf' ) );
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument();
		$loaded   = $dom->loadXML( $xml );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			throw new Exception( __( 'Could not parse Word document XML.', 'word-to-elementor-wf' ) );
		}

		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'w', self::NS_W );

		$paragraphs = array();
		foreach ( $xpath->query( '//w:body/w:p' ) as $p_el ) {
			$text = $this->paragraph_text( $xpath, $p_el );
			if ( '' === $text ) {
				continue;
			}
			$paragraphs[] = array(
				'style' => $this->paragraph_style( $xpath, $p_el ),
				'text'  => $text,
			);
		}

		if ( empty( $paragraphs ) ) {
			throw new Exception( __( 'The Word document has no readable paragraphs.', 'word-to-elementor-wf' ) );
		}

		return $this->group_sections( $paragraphs );
	}

	/**
	 * @param DOMXPath $xpath
	 * @param DOMElement $p_el
	 */
	private function paragraph_text( $xpath, $p_el ) {
		$parts = array();
		foreach ( $xpath->query( './/w:t', $p_el ) as $t_el ) {
			$parts[] = $t_el->textContent;
		}
		return trim( preg_replace( '/\s+/u', ' ', implode( '', $parts ) ) );
	}

	/**
	 * @param DOMXPath $xpath
	 * @param DOMElement $p_el
	 */
	private function paragraph_style( $xpath, $p_el ) {
		$nodes = $xpath->query( './w:pPr/w:pStyle/@w:val', $p_el );
		if ( 0 === $nodes->length ) {
			return '';
		}
		return (string) $nodes->item( 0 )->nodeValue;
	}

	/**
	 * @param array $paragraphs
	 * @return array
	 */
	private function group_sections( $paragraphs ) {
		$title         = '';
		$intro_paras   = array();
		$current_key   = 'hero';
		$section_items = array(
			'services' => array(),
			'why'      => array(),
			'process'  => array(),
			'faq'      => array(),
			'closing'  => array(),
		);
		$headings = array(
			'services' => '',
			'why'      => '',
			'process'  => '',
			'faq'      => '',
			'closing'  => '',
		);
		$pending_h3 = null;

		foreach ( $paragraphs as $para ) {
			$level = $this->heading_level( $para['style'] );
			$text  = $para['text'];

			if ( 1 === $level ) {
				if ( '' === $title ) {
					$title = $this->strip_heading_prefix( $text );
				}
				$current_key = 'hero';
				$pending_h3  = null;
				continue;
			}

			if ( 2 === $level ) {
				$current_key = $this->match_h2( $text, $headings, $section_items );
				if ( isset( $headings[ $current_key ] ) ) {
					$headings[ $current_key ] = $this->strip_heading_prefix( $text );
				}
				$pending_h3 = null;
				continue;
			}

			if ( 3 === $level ) {
				$pending_h3 = $this->strip_heading_prefix( $text );
				if ( 'closing' === $current_key ) {
					if ( '' === $headings['closing'] ) {
						$headings['closing'] = $pending_h3;
					}
				}
				continue;
			}

			if ( 'hero' === $current_key ) {
				$intro_paras[] = $text;
				continue;
			}

			if ( 'closing' === $current_key ) {
				$section_items['closing'][] = $text;
				continue;
			}

			if ( null === $pending_h3 || ! isset( $section_items[ $current_key ] ) ) {
				continue;
			}

			$item = array(
				'title' => $pending_h3,
				'body'  => $text,
			);
			if ( 'faq' === $current_key ) {
				$item = array(
					'q' => $pending_h3,
					'a' => $text,
				);
			}
			$section_items[ $current_key ][] = $item;
			$pending_h3 = null;
		}

		return array(
			'title'             => $title,
			'intro'             => $intro_paras,
			'services_heading'  => $headings['services'],
			'services'          => array_slice( $section_items['services'], 0, 4 ),
			'why_heading'       => $headings['why'],
			'why'               => array_slice( $section_items['why'], 0, 6 ),
			'process_heading'   => $headings['process'] ? $headings['process'] : 'Our Process',
			'process'           => array_slice( $section_items['process'], 0, 6 ),
			'faq_heading'       => $headings['faq'],
			'faqs'              => array_slice( $section_items['faq'], 0, 6 ),
			'closing_heading'   => $headings['closing'],
			'closing'           => $section_items['closing'],
		);
	}

	/**
	 * @param string $style
	 * @return int 0, 1, 2, or 3
	 */
	private function heading_level( $style ) {
		if ( preg_match( '/heading\s*1/i', $style ) || '1' === $style ) {
			return 1;
		}
		if ( preg_match( '/heading\s*2/i', $style ) || '2' === $style ) {
			return 2;
		}
		if ( preg_match( '/heading\s*3/i', $style ) || '3' === $style ) {
			return 3;
		}
		return 0;
	}

	/**
	 * @param string $text
	 * @param array  $headings
	 * @param array  $section_items
	 * @return string services|why|process|faq|closing|hero
	 */
	private function match_h2( $text, $headings = array(), $section_items = array() ) {
		$key = $this->normalize_label( $this->strip_heading_prefix( $text ) );

		if ( false !== strpos( $key, 'service' ) ) {
			return 'services';
		}
		if ( false !== strpos( $key, 'why' ) ) {
			return 'why';
		}
		if ( false !== strpos( $key, 'process' ) ) {
			return 'process';
		}
		if ( false !== strpos( $key, 'faq' ) ) {
			return 'faq';
		}
		if ( $this->is_closing_label( $key ) ) {
			return 'closing';
		}

		$faq_started = ! empty( $headings['faq'] ) || ! empty( $section_items['faq'] );
		if ( $faq_started ) {
			return 'closing';
		}

		return 'hero';
	}

	/**
	 * @param string $key Normalized heading text.
	 */
	private function is_closing_label( $key ) {
		$needles = array(
			'closing',
			'conclusion',
			'cta',
			'call to action',
			'next step',
			'get started',
			'ready to',
			'contact',
			'get in touch',
		);
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $key, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $text
	 */
	private function strip_heading_prefix( $text ) {
		$stripped = preg_replace( '/^heading\s*[123]\s*:\s*/iu', '', $text );
		return trim( $stripped );
	}

	/**
	 * @param string $text
	 */
	private function normalize_label( $text ) {
		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z0-9]+/i', ' ', $text );
		return trim( $text );
	}
}
