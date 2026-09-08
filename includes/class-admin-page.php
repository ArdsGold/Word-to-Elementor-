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

		$result = null;
		$error  = '';

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wte_nonce'] ) ) {
			check_admin_referer( 'wte_create_page', 'wte_nonce' );
			try {
				$result = $this->handle_submit();
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}
		}

		$elementor_ok = did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Word to Elementor WF', 'word-to-elementor-wf' ); ?></h1>
			<p><?php esc_html_e( 'Upload a .docx outline (Heading 1 / 2 / 3) to fill the bundled Elementor page template and create a draft page.', 'word-to-elementor-wf' ); ?></p>

			<?php if ( ! $elementor_ok ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Elementor must be installed and active.', 'word-to-elementor-wf' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( $result ) : ?>
				<div class="notice notice-success">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: page title */
								__( 'Draft page created: %s', 'word-to-elementor-wf' ),
								$result['title']
							)
						);
						?>
					</p>
					<p>
						<a href="<?php echo esc_url( $result['edit_url'] ); ?>"><?php esc_html_e( 'Edit page', 'word-to-elementor-wf' ); ?></a>
						<?php if ( ! empty( $result['elementor_url'] ) ) : ?>
							|
							<a href="<?php echo esc_url( $result['elementor_url'] ); ?>"><?php esc_html_e( 'Edit with Elementor', 'word-to-elementor-wf' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wte_create_page', 'wte_nonce' ); ?>
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
				</table>
				<?php submit_button( __( 'Create draft page', 'word-to-elementor-wf' ), 'primary', 'wte_submit', false, $elementor_ok ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array{title:string,edit_url:string,elementor_url:string}
	 * @throws Exception
	 */
	private function handle_submit() {
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

		$filler = new WTE_Template_Filler();
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
