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
	public const META_CTA_LABEL = '_amz_cta_label';

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

	public static function get_cta_label( int $post_id ): string {
		return sanitize_text_field( (string) get_post_meta( $post_id, self::META_CTA_LABEL, true ) );
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

	public static function shortcode_copy_markup( int $post_id ): string {
		$shortcode = self::shortcode_for( $post_id );

		return sprintf(
			'<button type="button" class="button-link amz-inserts-copy" data-shortcode="%1$s" title="%3$s"><code class="amz-inserts-shortcode">%2$s</code></button>',
			esc_attr( $shortcode ),
			esc_html( $shortcode ),
			esc_attr__( 'Click to copy', 'amz-inserts' )
		);
	}

	public static function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['amz_display']   = __( 'Display', 'amz-inserts' );
				$new['amz_shortcode'] = __( 'Shortcode', 'amz-inserts' );
				$new['amz_used']      = __( 'Used in', 'amz-inserts' );
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
			echo self::shortcode_copy_markup( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper
			return;
		}

		if ( 'amz_used' === $column ) {
			self::render_usage_summary( $post_id, true );
		}
	}

	/**
	 * Best-effort posts/pages that contain this unit's shortcode or block.
	 *
	 * @return array{count:int,posts:array<int,array{id:int,title:string,type:string,status:string,edit:string}>}
	 */
	public static function usage_for( int $unit_id, int $list_limit = 8 ): array {
		$posts = self::usage_index()[ $unit_id ] ?? array();

		return array(
			'count' => count( $posts ),
			'posts' => array_slice( $posts, 0, max( 0, $list_limit ) ),
		);
	}

	public static function render_usage_summary( int $unit_id, bool $compact = false ): void {
		$usage = self::usage_for( $unit_id, $compact ? 5 : 8 );
		if ( $usage['count'] < 1 ) {
			echo $compact ? '&mdash;' : '<p class="description">' . esc_html__( 'Not used in any posts yet.', 'amz-inserts' ) . '</p>';
			return;
		}

		if ( $compact ) {
			$names = array();
			foreach ( $usage['posts'] as $row ) {
				$names[] = $row['title'];
			}
			$label = sprintf(
				/* translators: %s: number of posts */
				_n( '%s post', '%s posts', $usage['count'], 'amz-inserts' ),
				number_format_i18n( $usage['count'] )
			);
			printf(
				'<span title="%1$s">%2$s</span>',
				esc_attr( implode( ', ', $names ) ),
				esc_html( $label )
			);
			return;
		}

		echo '<p><strong>' . esc_html__( 'Used in', 'amz-inserts' ) . '</strong></p><ul class="amz-inserts-used-in">';
		foreach ( $usage['posts'] as $row ) {
			$label = $row['title'];
			if ( 'publish' !== $row['status'] ) {
				$label .= ' (' . $row['status'] . ')';
			}
			if ( '' !== $row['edit'] ) {
				printf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( $row['edit'] ),
					esc_html( $label )
				);
			} else {
				printf( '<li>%s</li>', esc_html( $label ) );
			}
		}
		if ( $usage['count'] > count( $usage['posts'] ) ) {
			printf(
				'<li>%s</li>',
				esc_html(
					sprintf(
						/* translators: %s: remaining post count */
						__( 'and %s more', 'amz-inserts' ),
						number_format_i18n( $usage['count'] - count( $usage['posts'] ) )
					)
				)
			);
		}
		echo '</ul>';
		echo '<p class="description">' . esc_html__( 'Approximate: posts that contain this shortcode or an Amazon Insert block for this unit.', 'amz-inserts' ) . '</p>';
	}

	/**
	 * Unit IDs referenced in post content via shortcode or the Amazon Insert block.
	 *
	 * @return int[]
	 */
	public static function unit_ids_in_content( string $content ): array {
		$ids = array();

		if ( preg_match_all( '/\[amz_unit\s[^\]]*?\bid=["\']?(\d+)/', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$ids[] = (int) $id;
			}
		}

		if ( preg_match_all( '/wp:amz-inserts\/insert\b[^>]*"unitId"\s*:\s*(\d+)/', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$ids[] = (int) $id;
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );

		return $ids;
	}

	/**
	 * @return array<int, array<int, array{id:int,title:string,type:string,status:string,edit:string}>>
	 */
	private static function usage_index(): array {
		static $index = null;
		if ( null !== $index ) {
			return $index;
		}

		$index = array();
		global $wpdb;

		// ponytail: LIKE scan of matching post_content, cap 500 hits; relationship table if this gets slow.
		$rows = $wpdb->get_results(
			"SELECT ID, post_title, post_type, post_status, post_content
			FROM {$wpdb->posts}
			WHERE post_status IN ('publish','draft','pending','private','future')
			AND post_type NOT IN ('revision','nav_menu_item','attachment','amz_unit','customize_changeset','oembed_cache','user_request','wp_template','wp_template_part','wp_global_styles','wp_navigation','wp_font_family','wp_font_face')
			AND (post_content LIKE '%[amz_unit %' OR post_content LIKE '%wp:amz-inserts/insert%')
			LIMIT 500"
		);

		if ( ! is_array( $rows ) ) {
			return $index;
		}

		foreach ( $rows as $row ) {
			$unit_ids = self::unit_ids_in_content( (string) $row->post_content );
			if ( empty( $unit_ids ) ) {
				continue;
			}

			$title = (string) $row->post_title;
			if ( '' === $title ) {
				$title = __( '(no title)', 'amz-inserts' );
			}

			$entry = array(
				'id'     => (int) $row->ID,
				'title'  => $title,
				'type'   => (string) $row->post_type,
				'status' => (string) $row->post_status,
				'edit'   => (string) get_edit_post_link( (int) $row->ID, 'raw' ),
			);

			foreach ( $unit_ids as $unit_id ) {
				$index[ $unit_id ][] = $entry;
			}
		}

		return $index;
	}
}
