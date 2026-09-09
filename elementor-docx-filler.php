<?php
/**
 * Plugin Name: Word to Elementor WF
 * Description: Fill a bundled Elementor page template from a Word .docx outline (Heading 1 / 2 / 3) and create a draft page.
 * Version: 3.0.0
 * Author: Macky Villafuerte, Arden Guinto
 * Text Domain: word-to-elementor-wf
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: Elementor pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WTE_PLUGIN_FILE', __FILE__ );
define( 'WTE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once WTE_PLUGIN_DIR . 'includes/class-docx-parser.php';
require_once WTE_PLUGIN_DIR . 'includes/class-template-store.php';
require_once WTE_PLUGIN_DIR . 'includes/class-template-filler.php';
require_once WTE_PLUGIN_DIR . 'includes/class-page-creator.php';
require_once WTE_PLUGIN_DIR . 'includes/class-admin-page.php';

add_action( 'admin_menu', array( new WTE_Admin_Page(), 'register' ) );
