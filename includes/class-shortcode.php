<?php
/**
 * [amz_unit id="123"] shortcode.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Shortcode {

	public static function init(): void {
		add_shortcode( 'amz_unit', array( self::class, 'render' ) );
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'amz_unit'
		);

		return Amz_Inserts_Renderer::render_unit( absint( $atts['id'] ) );
	}
}
