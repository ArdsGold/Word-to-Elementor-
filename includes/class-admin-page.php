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

			<?php if ( $page_result ) : ?>
				<div class="notice notice-success">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: page title */
								__( 'Draft page created: %s', 'word-to-elementor-wf' ),
								$page_result['title']
							)
						);
						?>
					</p>
					<p>
						<a href="<?php echo esc_url( $page_result['edit_url'] ); ?>"><?php esc_html_e( 'Edit page', 'word-to-elementor-wf' ); ?></a>
						<?php if ( ! empty( $page_result['elementor_url'] ) ) : ?>
							|
							<a href="<?php echo esc_url( $page_result['elementor_url'] ); ?>"><?php esc_html_e( 'Edit with Elementor', 'word-to-elementor-wf' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Create page from Word', 'word-to-elementor-wf' ); ?></h2>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wte_admin', 'wte_nonce' ); ?>
				<input type="hidden" name="wte_action" value="create_page" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wte_docx"><?php esc_html_e( 'Word document', 'word-to-elementor-wf' ); ?></label>
						</th>
						<td>
							<input type="file" id="wte_docx" name="wte_docx" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required />
							<p class="description"><?php esc_html_e( 'Use Heading 1 for the page title, Heading 2 for sections (Services, Why Choose Us, Process, FAQ, Closing), and Heading 3 for items.', 'word-to-elementor-wf' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wte_page_title"><?php esc_html_e( 'Page title', 'word-to-elementor-wf' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="wte_page_title" name="wte_page_title" value="" />
							<p class="description"><?php esc_html_e( 'Optional. Defaults to the Word Heading 1.', 'word-to-elementor-wf' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Formatting options', 'word-to-elementor-wf' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="wte_bold_phone_links" value="1" />
									<?php esc_html_e( 'Bold phone-number hyperlinks', 'word-to-elementor-wf' ); ?>
								</label><br />
								<label>
									<input type="checkbox" name="wte_underline_phone_links" value="1" />
									<?php esc_html_e( 'Underline phone-number hyperlinks', 'word-to-elementor-wf' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wte_format_words"><?php esc_html_e( 'Words to format', 'word-to-elementor-wf' ); ?></label>
						</th>
						<td>
							<input type="text" class="large-text" id="wte_format_words" name="wte_format_words" value="" />
							<p class="description"><?php esc_html_e( 'Enter words or phrases separated by commas. Example: roof repair, emergency service, licensed.', 'word-to-elementor-wf' ); ?></p>
							<p>
								<label>
									<input type="checkbox" name="wte_bold_words" value="1" />
									<?php esc_html_e( 'Bold these words/phrases', 'word-to-elementor-wf' ); ?>
								</label>
								&nbsp;&nbsp;
								<label>
									<input type="checkbox" name="wte_underline_words" value="1" />
									<?php esc_html_e( 'Underline these words/phrases', 'word-to-elementor-wf' ); ?>
								</label>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Create draft page', 'word-to-elementor-wf' ), 'primary', 'wte_submit', false, $elementor_ok ? array() : array( 'disabled' => 'disabled' ) ); ?>
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
