<?php
/**
 * Plugin bootstrap.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Plugin {

	public static function init(): void {
		Amz_Inserts_Settings::init();
		Amz_Inserts_Cpt_Unit::init();
		Amz_Inserts_Unit_Editor::init();
		Amz_Inserts_Shortcode::init();
		Amz_Inserts_Fetch::init();
		Amz_Inserts_Block::init();
		Amz_Inserts_Renderer::init();
	}

	public static function activate(): void {
		Amz_Inserts_Cpt_Unit::register();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
