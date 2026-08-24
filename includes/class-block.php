<?php
/**
 * Gutenberg block: saved unit or custom insert.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Block {

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function attributes(): array {
		return array(
			'mode'    => array(
				'type'    => 'string',
				'default' => 'saved',
			),
			'unitId'  => array(
				'type'    => 'number',
				'default' => 0,
			),
			'display' => array(
				'type'    => 'string',
				'default' => 'card',
			),
			'columns' => array(
				'type'    => 'number',
				'default' => 4,
			),
			'items'   => array(
				'type'    => 'array',
				'default' => array(),
			),
		);
	}

	public static function register(): void {
		wp_register_script(
			'amz-inserts-block-editor',
			AMZ_INSERTS_URL . 'admin/js/block-editor.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-server-side-render',
				'wp-api-fetch',
			),
			AMZ_INSERTS_VERSION,
			true
		);

		register_block_type(
			'amz-inserts/insert',
			array(
				'api_version'     => 3,
				'title'           => __( 'Amazon Insert', 'amz-inserts' ),
				'description'     => __( 'Insert a saved Amazon unit or a custom text link, image, card, or grid.', 'amz-inserts' ),
				'category'        => 'widgets',
				'icon'            => 'cart',
				'editor_script'   => 'amz-inserts-block-editor',
				'render_callback' => array( self::class, 'render' ),
				'attributes'      => self::attributes(),
				'supports'        => array(
					'html' => false,
				),
			)
		);
	}

	public static function render( array $attributes ): string {
		$mode = sanitize_key( (string) ( $attributes['mode'] ?? 'saved' ) );

		if ( 'custom' === $mode ) {
			$items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : array();
			return Amz_Inserts_Renderer::render(
				sanitize_key( (string) ( $attributes['display'] ?? 'card' ) ),
				$items,
				absint( $attributes['columns'] ?? 4 )
			);
		}

		return Amz_Inserts_Renderer::render_unit( absint( $attributes['unitId'] ?? 0 ) );
	}
}
