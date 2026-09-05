<?php
/**
 * [amz_unit id="123"] and [amz_link] shortcodes.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Shortcode {

	public static function init(): void {
		add_shortcode( 'amz_unit', array( self::class, 'render' ) );
		add_shortcode( 'amz_link', array( self::class, 'render_link' ) );
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

	/**
	 * One-off Amazon link. Default display is a text link.
	 *
	 * [amz_link url="https://www.amazon.com/dp/B0EXAMPLE1" title="Widget"]
	 * [amz_link asin="B0EXAMPLE1" title="Widget" display="button"]
	 */
	public static function render_link( $atts ): string {
		$atts = shortcode_atts(
			array(
				'url'       => '',
				'asin'      => '',
				'title'     => '',
				'display'   => 'text',
				'image_url' => '',
				'image_id'  => 0,
				'cta'       => '',
			),
			$atts,
			'amz_link'
		);

		return Amz_Inserts_Renderer::render_link( $atts );
	}
}
