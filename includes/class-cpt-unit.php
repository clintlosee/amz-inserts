<?php
/**
 * Saved unit custom post type.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Cpt_Unit {

	public const POST_TYPE = 'amz_unit';
	public const META_DISPLAY = '_amz_display';
	public const META_COLUMNS = '_amz_columns';
	public const META_ITEMS = '_amz_items';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( self::class, 'column_content' ), 10, 2 );
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Amazon Inserts', 'amz-inserts' ),
					'singular_name'      => __( 'Unit', 'amz-inserts' ),
					'add_new'            => __( 'Add New', 'amz-inserts' ),
					'add_new_item'       => __( 'Add New Unit', 'amz-inserts' ),
					'edit_item'          => __( 'Edit Unit', 'amz-inserts' ),
					'new_item'           => __( 'New Unit', 'amz-inserts' ),
					'view_item'          => __( 'View Unit', 'amz-inserts' ),
					'search_items'       => __( 'Search Units', 'amz-inserts' ),
					'not_found'          => __( 'No units found.', 'amz-inserts' ),
					'not_found_in_trash' => __( 'No units found in Trash.', 'amz-inserts' ),
					'all_items'          => __( 'All Units', 'amz-inserts' ),
					'menu_name'          => __( 'Amazon Inserts', 'amz-inserts' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'show_in_admin_bar'   => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'menu_icon'           => 'dashicons-cart',
				'capability_type'     => 'post',
			)
		);
	}

	public static function display_types(): array {
		return array(
			'text'  => __( 'Text link', 'amz-inserts' ),
			'image' => __( 'Image', 'amz-inserts' ),
			'card'  => __( 'Card', 'amz-inserts' ),
			'grid'  => __( 'Grid', 'amz-inserts' ),
		);
	}

	public static function get_display( int $post_id ): string {
		$display = (string) get_post_meta( $post_id, self::META_DISPLAY, true );
		$types   = self::display_types();

		return isset( $types[ $display ] ) ? $display : 'card';
	}

	public static function get_columns( int $post_id ): int {
		$columns = (int) get_post_meta( $post_id, self::META_COLUMNS, true );
		if ( $columns < 2 || $columns > 4 ) {
			return 4;
		}

		return $columns;
	}

	public static function get_items( int $post_id ): array {
		$items = get_post_meta( $post_id, self::META_ITEMS, true );

		return is_array( $items ) ? $items : array();
	}

	public static function sanitize_items( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$items = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$url   = Amz_Inserts_Url::normalize( (string) ( $row['url'] ?? '' ) );
			$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
			$asin  = strtoupper( sanitize_text_field( (string) ( $row['asin'] ?? '' ) ) );

			if ( '' === $asin && '' !== $url ) {
				$asin = Amz_Inserts_Url::extract_asin( $url );
			}

			$image_url = Amz_Inserts_Url::normalize_image_url( (string) ( $row['image_url'] ?? $row['imageUrl'] ?? '' ) );

			if ( '' === $url && '' === $title && empty( $row['image_id'] ) && empty( $row['imageId'] ) && '' === $image_url ) {
				continue;
			}

			$items[] = array(
				'url'       => $url,
				'title'     => $title,
				'image_id'  => absint( $row['image_id'] ?? $row['imageId'] ?? 0 ),
				'image_url' => $image_url,
				'asin'      => $asin,
			);
		}

		return $items;
	}

	public static function shortcode_for( int $post_id ): string {
		return sprintf( '[amz_unit id="%d"]', $post_id );
	}

	public static function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['amz_display']   = __( 'Display', 'amz-inserts' );
				$new['amz_shortcode'] = __( 'Shortcode', 'amz-inserts' );
			}
		}

		return $new;
	}

	public static function column_content( string $column, int $post_id ): void {
		if ( 'amz_display' === $column ) {
			$types   = self::display_types();
			$display = self::get_display( $post_id );
			echo esc_html( $types[ $display ] ?? $display );
			return;
		}

		if ( 'amz_shortcode' === $column ) {
			$shortcode = self::shortcode_for( $post_id );
			printf(
				'<code class="amz-inserts-shortcode" data-shortcode="%1$s">%2$s</code>',
				esc_attr( $shortcode ),
				esc_html( $shortcode )
			);
		}
	}
}
