<?php
/**
 * Admin upload screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTE_Admin_Page {
	const SLUG = 'word-to-elementor-wf';

	public function register() {
		add_menu_page(
			__( 'Word to Elementor WF', 'word-to-elementor-wf' ),
			__( 'Word to Elementor WF', 'word-to-elementor-wf' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-media-document',
			58
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page_result     = null;
		$template_notice = '';
		$error           = '';

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wte_nonce'] ) ) {
			check_admin_referer( 'wte_admin', 'wte_nonce' );
			$action = isset( $_POST['wte_action'] ) ? sanitize_key( wp_unslash( $_POST['wte_action'] ) ) : '';
			try {
				if ( 'save_template' === $action ) {
					$this->handle_template_upload();
					$template_notice = __( 'Custom Elementor template saved. New pages will use this JSON.', 'word-to-elementor-wf' );
				} elseif ( 'reset_template' === $action ) {
					WTE_Template_Store::reset();
					$template_notice = __( 'Reverted to the bundled Elementor template.', 'word-to-elementor-wf' );
				} elseif ( 'create_page' === $action ) {
					$page_result = $this->handle_create_page();
				} elseif ( 'create_bulk_pages' === $action ) {
					$page_result = $this->handle_create_bulk_pages();
				}
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}
		}

		$elementor_ok = did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
		$using_custom = WTE_Template_Store::using_custom();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Word to Elementor WF', 'word-to-elementor-wf' ); ?></h1>
			<p><?php esc_html_e( 'Upload a .docx outline (Heading 1 / 2 / 3) to fill the Elementor template and create a draft page.', 'word-to-elementor-wf' ); ?></p>

			<?php if ( ! $elementor_ok ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Elementor must be installed and active.', 'word-to-elementor-wf' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( $template_notice ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $template_notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( is_array( $page_result ) && ! empty( $page_result['bulk'] ) ) : ?>
				<div class="notice <?php echo ! empty( $page_result['errors'] ) ? 'notice-warning' : 'notice-success'; ?>">
					<p>
						<?php
						echo esc_html(
							sprintf(
								__( 'Bulk import complete: %1$d of %2$d pages created.', 'word-to-elementor-wf' ),
								isset( $page_result['created'] ) ? (int) $page_result['created'] : 0,
								isset( $page_result['total'] ) ? (int) $page_result['total'] : 0
							)
						);
						?>
					</p>
					<?php if ( ! empty( $page_result['pages'] ) && is_array( $page_result['pages'] ) ) : ?>
						<ul>
							<?php foreach ( $page_result['pages'] as $bulk_page ) : ?>
								<?php
								$bulk_title         = isset( $bulk_page['title'] ) ? $bulk_page['title'] : __( 'Untitled page', 'word-to-elementor-wf' );
								$bulk_edit_url      = isset( $bulk_page['edit_url'] ) ? $bulk_page['edit_url'] : '';
								$bulk_elementor_url = isset( $bulk_page['elementor_url'] ) ? $bulk_page['elementor_url'] : '';
								?>
								<li>
									<?php if ( $bulk_edit_url ) : ?>
										<a href="<?php echo esc_url( $bulk_edit_url ); ?>"><?php echo esc_html( $bulk_title ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $bulk_title ); ?>
									<?php endif; ?>
									<?php if ( $bulk_elementor_url ) : ?>
										— <a href="<?php echo esc_url( $bulk_elementor_url ); ?>"><?php esc_html_e( 'Edit with Elementor', 'word-to-elementor-wf' ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $page_result['errors'] ) && is_array( $page_result['errors'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Files that could not be imported:', 'word-to-elementor-wf' ); ?></strong></p>
						<ul>
							<?php foreach ( $page_result['errors'] as $bulk_error ) : ?>
								<li><?php echo esc_html( ( isset( $bulk_error['file'] ) ? $bulk_error['file'] : __( 'Unknown file', 'word-to-elementor-wf' ) ) . ': ' . ( isset( $bulk_error['message'] ) ? $bulk_error['message'] : __( 'Unknown error', 'word-to-elementor-wf' ) ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php elseif ( is_array( $page_result ) && isset( $page_result['title'] ) ) : ?>
				<div class="notice notice-success">
					<p>
						<?php
						echo esc_html(
							sprintf(
								__( 'Draft page created: %s', 'word-to-elementor-wf' ),
								$page_result['title']
							)
						);
						?>
					</p>
					<p>
						<?php if ( ! empty( $page_result['edit_url'] ) ) : ?>
							<a href="<?php echo esc_url( $page_result['edit_url'] ); ?>"><?php esc_html_e( 'Edit page', 'word-to-elementor-wf' ); ?></a>
						<?php endif; ?>
						<?php if ( ! empty( $page_result['elementor_url'] ) ) : ?>
							|
							<a href="<?php echo esc_url( $page_result['elementor_url'] ); ?>"><?php esc_html_e( 'Edit with Elementor', 'word-to-elementor-wf' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>

			

			<hr />

			<h2><?php esc_html_e( 'Server upload limits', 'word-to-elementor-wf' ); ?></h2>
			<?php
			$wte_max_file_uploads    = ini_get( 'max_file_uploads' );
			$wte_upload_max_filesize = ini_get( 'upload_max_filesize' );
			$wte_post_max_size       = ini_get( 'post_max_size' );
			$wte_max_execution_time  = ini_get( 'max_execution_time' );
			$wte_max_input_time      = ini_get( 'max_input_time' );
			?>
			<table class="widefat striped" style="max-width: 760px; margin-bottom: 20px;">
				<thead><tr>
					<th><?php esc_html_e( 'PHP setting', 'word-to-elementor-wf' ); ?></th>
					<th><?php esc_html_e( 'Current value', 'word-to-elementor-wf' ); ?></th>
				</tr></thead>
				<tbody>
					<tr><td><code>max_file_uploads</code></td><td><strong><?php echo esc_html( $wte_max_file_uploads ? $wte_max_file_uploads : __( 'Not available', 'word-to-elementor-wf' ) ); ?></strong></td></tr>
					<tr><td><code>upload_max_filesize</code></td><td><?php echo esc_html( $wte_upload_max_filesize ? $wte_upload_max_filesize : __( 'Not available', 'word-to-elementor-wf' ) ); ?></td></tr>
					<tr><td><code>post_max_size</code></td><td><?php echo esc_html( $wte_post_max_size ? $wte_post_max_size : __( 'Not available', 'word-to-elementor-wf' ) ); ?></td></tr>
					<tr><td><code>max_execution_time</code></td><td><?php echo esc_html( $wte_max_execution_time ? $wte_max_execution_time . ' seconds' : __( 'Not available', 'word-to-elementor-wf' ) ); ?></td></tr>
					<tr><td><code>max_input_time</code></td><td><?php echo esc_html( $wte_max_input_time ? $wte_max_input_time . ' seconds' : __( 'Not available', 'word-to-elementor-wf' ) ); ?></td></tr>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'If max_file_uploads is 20, PHP may only accept 20 files from one bulk upload request.', 'word-to-elementor-wf' ); ?></p>

			<h2><?php esc_html_e( 'Bulk create pages from Word', 'word-to-elementor-wf' ); ?></h2>
			<p><?php esc_html_e( 'Select multiple .docx files. Each file becomes its own draft page using the same Elementor template and formatting options above.', 'word-to-elementor-wf' ); ?></p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wte_admin', 'wte_nonce' ); ?>
				<input type="hidden" name="wte_action" value="create_bulk_pages" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wte_bulk_docx"><?php esc_html_e( 'Word documents', 'word-to-elementor-wf' ); ?></label>
						</th>
						<td>
							<input type="file" id="wte_bulk_docx" name="wte_bulk_docx[]" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple required />
							<p class="description"><?php esc_html_e( 'Select as many .docx files as you want to process. The Heading 1 in each document becomes that page’s title unless a title override is used in the document workflow.', 'word-to-elementor-wf' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Publishing options', 'word-to-elementor-wf' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wte_bulk_publish" value="1" />
								<?php esc_html_e( 'Publish pages immediately after creation', 'word-to-elementor-wf' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When checked, successfully created pages will be published immediately instead of saved as drafts.', 'word-to-elementor-wf' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Formatting options', 'word-to-elementor-wf' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="wte_bulk_bold_phone_links" value="1" />
									<?php esc_html_e( 'Bold phone-number hyperlinks', 'word-to-elementor-wf' ); ?>
								</label><br />
								<label>
									<input type="checkbox" name="wte_bulk_underline_phone_links" value="1" />
									<?php esc_html_e( 'Underline phone-number hyperlinks', 'word-to-elementor-wf' ); ?>
								</label>
							</fieldset>
							<p>
								<label for="wte_bulk_format_words"><?php esc_html_e( 'Words/phrases to format:', 'word-to-elementor-wf' ); ?></label><br />
								<input type="text" class="large-text" id="wte_bulk_format_words" name="wte_bulk_format_words" value="" />
							</p>
							<p>
								<label>
									<input type="checkbox" name="wte_bulk_bold_words" value="1" />
									<?php esc_html_e( 'Bold these words/phrases', 'word-to-elementor-wf' ); ?>
								</label>
								&nbsp;&nbsp;
								<label>
									<input type="checkbox" name="wte_bulk_underline_words" value="1" />
									<?php esc_html_e( 'Underline these words/phrases', 'word-to-elementor-wf' ); ?>
								</label>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Create all draft pages', 'word-to-elementor-wf' ), 'primary', 'wte_bulk_submit', false, $elementor_ok ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Elementor template', 'word-to-elementor-wf' ); ?></h2>
			<p>
				<?php if ( $using_custom ) : ?>
					<strong><?php esc_html_e( 'Using: uploaded custom template.json', 'word-to-elementor-wf' ); ?></strong>
				<?php else : ?>
					<?php esc_html_e( 'Using: bundled AutomationTestTemplate2.0.json', 'word-to-elementor-wf' ); ?>
				<?php endif; ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Widgets are filled by Advanced → Attributes → data-customid (for example HeroH1, HeroP, Section2Content1). Native Elementor data-id values are ignored.', 'word-to-elementor-wf' ); ?>
			</p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wte_admin', 'wte_nonce' ); ?>
				<input type="hidden" name="wte_action" value="save_template" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wte_template"><?php esc_html_e( 'Template JSON', 'word-to-elementor-wf' ); ?></label>
						</th>
						<td>
							<input type="file" id="wte_template" name="wte_template" accept=".json,application/json" required />
							<p class="description"><?php esc_html_e( 'Export the page from Elementor (JSON) after setting data-customid on each fillable widget.', 'word-to-elementor-wf' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save custom template', 'word-to-elementor-wf' ), 'secondary', 'wte_save_template', false ); ?>
			</form>
			<?php if ( $using_custom ) : ?>
				<form method="post" style="margin-top: 8px;">
					<?php wp_nonce_field( 'wte_admin', 'wte_nonce' ); ?>
					<input type="hidden" name="wte_action" value="reset_template" />
					<?php submit_button( __( 'Use bundled template', 'word-to-elementor-wf' ), 'delete', 'wte_reset_template', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Create one draft Elementor page for every uploaded .docx file.
	 *
	 * @return array
	 * @throws Exception
	 */
	private function handle_create_bulk_pages() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) && ! did_action( 'elementor/loaded' ) ) {
			throw new Exception( __( 'Elementor must be installed and active.', 'word-to-elementor-wf' ) );
		}

		if ( empty( $_FILES['wte_bulk_docx']['tmp_name'] ) || ! is_array( $_FILES['wte_bulk_docx']['tmp_name'] ) ) {
			throw new Exception( __( 'Please select one or more .docx files.', 'word-to-elementor-wf' ) );
		}

		$format_words = isset( $_POST['wte_bulk_format_words'] )
			? sanitize_text_field( wp_unslash( $_POST['wte_bulk_format_words'] ) )
			: '';

		$publish_immediately = ! empty( $_POST['wte_bulk_publish'] );

		$format_options = array(
			'bold_phone_links'      => ! empty( $_POST['wte_bulk_bold_phone_links'] ),
			'underline_phone_links' => ! empty( $_POST['wte_bulk_underline_phone_links'] ),
			'bold_words'            => ! empty( $_POST['wte_bulk_bold_words'] ),
			'underline_words'       => ! empty( $_POST['wte_bulk_underline_words'] ),
			'format_words'          => array_filter( array_map( 'trim', explode( ',', $format_words ) ) ),
		);

		$parser  = new WTE_Docx_Parser();
		$creator = new WTE_Page_Creator();
		$pages   = array();
		$errors  = array();
		$total   = count( $_FILES['wte_bulk_docx']['tmp_name'] );

		foreach ( $_FILES['wte_bulk_docx']['tmp_name'] as $i => $tmp_path ) {
			$name = isset( $_FILES['wte_bulk_docx']['name'][ $i ] )
				? sanitize_file_name( wp_unslash( $_FILES['wte_bulk_docx']['name'][ $i ] ) )
				: 'Document ' . ( $i + 1 );

			try {
				if ( empty( $tmp_path ) ) {
					throw new Exception( __( 'The upload is empty.', 'word-to-elementor-wf' ) );
				}

				if ( ! empty( $_FILES['wte_bulk_docx']['error'][ $i ] ) ) {
					throw new Exception( __( 'The file upload failed.', 'word-to-elementor-wf' ) );
				}

				$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
				if ( 'docx' !== $ext ) {
					throw new Exception( __( 'Only .docx files are supported.', 'word-to-elementor-wf' ) );
				}

				$outline = $parser->parse( $tmp_path );
				if ( '' === $outline['title'] ) {
					throw new Exception( __( 'The document is missing a Heading 1 page title.', 'word-to-elementor-wf' ) );
				}

				$filler = new WTE_Template_Filler( $format_options );
				$filled = $filler->fill( $outline );
				$post_id = $creator->create( $filled, $outline['title'], $publish_immediately );

				$elementor_url = '';
				if ( class_exists( '\Elementor\Plugin' ) ) {
					$elementor_url = admin_url( 'post.php?post=' . $post_id . '&action=elementor' );
				}

				$pages[] = array(
					'title'         => $outline['title'],
					'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
					'elementor_url' => $elementor_url,
				);
			} catch ( Exception $e ) {
				$errors[] = array(
					'file'    => $name,
					'message' => $e->getMessage(),
				);
			}
		}

		return array(
			'bulk'    => true,
			'created' => count( $pages ),
			'total'   => $total,
			'pages'   => $pages,
			'errors'  => $errors,
		);
	}

	/**
	 * @throws Exception
	 */
	private function handle_template_upload() {
		if ( empty( $_FILES['wte_template'] ) || empty( $_FILES['wte_template']['tmp_name'] ) ) {
			throw new Exception( __( 'Please upload an Elementor template .json file.', 'word-to-elementor-wf' ) );
		}

		$file = $_FILES['wte_template'];
		if ( ! empty( $file['error'] ) ) {
			throw new Exception( __( 'The template upload failed.', 'word-to-elementor-wf' ) );
		}

		$name = isset( $file['name'] ) ? $file['name'] : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'json' !== $ext ) {
			throw new Exception( __( 'Only .json template files are supported.', 'word-to-elementor-wf' ) );
		}

		WTE_Template_Store::save_upload( $file['tmp_name'] );
	}

	/**
	 * @return array{title:string,edit_url:string,elementor_url:string}
	 * @throws Exception
	 */
	private function handle_create_page() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) && ! did_action( 'elementor/loaded' ) ) {
			throw new Exception( __( 'Elementor must be installed and active.', 'word-to-elementor-wf' ) );
		}

		if ( empty( $_FILES['wte_docx'] ) || empty( $_FILES['wte_docx']['tmp_name'] ) ) {
			throw new Exception( __( 'Please upload a .docx file.', 'word-to-elementor-wf' ) );
		}

		$file = $_FILES['wte_docx'];
		if ( ! empty( $file['error'] ) ) {
			throw new Exception( __( 'The file upload failed.', 'word-to-elementor-wf' ) );
		}

		$name = isset( $file['name'] ) ? $file['name'] : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'docx' !== $ext ) {
			throw new Exception( __( 'Only .docx files are supported.', 'word-to-elementor-wf' ) );
		}

		$parser  = new WTE_Docx_Parser();
		$outline = $parser->parse( $file['tmp_name'] );

		if ( '' === $outline['title'] ) {
			throw new Exception( __( 'The document is missing a Heading 1 page title.', 'word-to-elementor-wf' ) );
		}

		$override   = isset( $_POST['wte_page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wte_page_title'] ) ) : '';
		$page_title = '' !== $override ? $override : $outline['title'];

		$format_words = isset( $_POST['wte_format_words'] )
			? sanitize_text_field( wp_unslash( $_POST['wte_format_words'] ) )
			: '';

		$format_options = array(
			'bold_phone_links'      => ! empty( $_POST['wte_bold_phone_links'] ),
			'underline_phone_links' => ! empty( $_POST['wte_underline_phone_links'] ),
			'bold_words'            => ! empty( $_POST['wte_bold_words'] ),
			'underline_words'       => ! empty( $_POST['wte_underline_words'] ),
			'format_words'          => array_filter( array_map( 'trim', explode( ',', $format_words ) ) ),
		);

		$filler = new WTE_Template_Filler( $format_options );
		$filled = $filler->fill( $outline );

		$creator = new WTE_Page_Creator();
		$post_id = $creator->create( $filled, $page_title );

		$elementor_url = '';
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor_url = admin_url( 'post.php?post=' . $post_id . '&action=elementor' );
		}

		return array(
			'title'         => $page_title,
			'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
			'elementor_url' => $elementor_url,
		);
	}
}
