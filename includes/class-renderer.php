<?php
/**
 * Shared front-end HTML for shortcodes and blocks.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Renderer {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ) );
		add_filter( 'style_loader_src', array( self::class, 'keep_cache_bust' ), 9999, 2 );
	}

	public static function enqueue(): void {
		$path = AMZ_INSERTS_DIR . 'public/css/amz-inserts.css';
		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_style(
			'amz-inserts',
			AMZ_INSERTS_URL . 'public/css/amz-inserts.css',
			array(),
			(string) filemtime( $path )
		);
		wp_add_inline_style( 'amz-inserts', (string) file_get_contents( $path ) );
	}

	/**
	 * SiteGround minifies this to amz-inserts.min.css and drops ?ver=.
	 * Re-attach mtime so Cloudflare does not serve a year-old copy.
	 */
	public static function keep_cache_bust( string $src, string $handle ): string {
		if ( 'amz-inserts' !== $handle ) {
			return $src;
		}

		$path = AMZ_INSERTS_DIR . 'public/css/amz-inserts.css';
		if ( ! is_readable( $path ) ) {
			return $src;
		}

		return add_query_arg( 'ver', (string) filemtime( $path ), $src );
	}

	public static function render_unit( int $post_id ): string {
		if ( $post_id <= 0 || Amz_Inserts_Cpt_Unit::POST_TYPE !== get_post_type( $post_id ) ) {
			return '';
		}

		if ( 'publish' !== get_post_status( $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}

		return self::render(
			Amz_Inserts_Cpt_Unit::get_display( $post_id ),
			Amz_Inserts_Cpt_Unit::get_items( $post_id ),
			Amz_Inserts_Cpt_Unit::get_columns( $post_id )
		);
	}

	public static function render( string $display, array $items, int $columns = 4 ): string {
		$types = Amz_Inserts_Cpt_Unit::display_types();
		if ( ! isset( $types[ $display ] ) ) {
			$display = 'card';
		}

		if ( $columns < 2 || $columns > 4 ) {
			$columns = 4;
		}

		$items = self::prepare_items( $items, 'image' === $display ? 'large' : 'medium' );
		if ( empty( $items ) ) {
			return '';
		}

		if ( in_array( $display, array( 'text', 'image', 'card' ), true ) ) {
			$items = array_slice( $items, 0, 1 );
		}

		$template = AMZ_INSERTS_DIR . 'templates/' . $display . '.php';
		if ( ! is_readable( $template ) ) {
			return '';
		}

		ob_start();
		if ( 'card' === $display ) {
			echo '<div class="amz-inserts amz-inserts--card">';
		}
		include $template;
		if ( 'card' === $display ) {
			echo '</div>';
		}
		$html = (string) ob_get_clean();

		$disclosure = self::disclosure_html();
		if ( '' !== $disclosure ) {
			$html .= $disclosure;
		}

		return $html;
	}

	public static function prepare_items( array $items, string $image_size = 'medium' ): array {
		$prepared = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$url       = Amz_Inserts_Url::normalize( (string) ( $item['url'] ?? '' ) );
			$image_id  = absint( $item['image_id'] ?? $item['imageId'] ?? 0 );
			$image_url = Amz_Inserts_Url::normalize_image_url( (string) ( $item['image_url'] ?? $item['imageUrl'] ?? '' ) );
			$title     = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$asin      = strtoupper( sanitize_text_field( (string) ( $item['asin'] ?? '' ) ) );

			if ( '' === $url ) {
				continue;
			}

			if ( '' === $asin ) {
				$asin = Amz_Inserts_Url::extract_asin( $url );
			}

			$prepared[] = array(
				'url'        => Amz_Inserts_Url::with_tag( $url ),
				'title'      => $title,
				'image_id'   => $image_id,
				'image_url'  => $image_url,
				'asin'       => $asin,
				'image_html' => self::image_html( $image_id, $image_url, $title, $image_size, $asin ),
			);
		}

		return $prepared;
	}

	public static function image_html( int $image_id, string $image_url, string $title, string $image_size, string $asin = '' ): string {
		if ( $image_id ) {
			$html = wp_get_attachment_image(
				$image_id,
				$image_size,
				false,
				array(
					'alt' => $title,
				)
			);
			if ( $html ) {
				return $html;
			}
		}

		$src = Amz_Inserts_Url::normalize_image_url( $image_url );
		if ( '' === $src ) {
			$src = Amz_Inserts_Url::asin_image_url( $asin );
		}

		if ( '' === $src ) {
			return '';
		}

		return sprintf(
			'<img src="%1$s" alt="%2$s" loading="lazy" decoding="async" />',
			esc_url( $src ),
			esc_attr( $title )
		);
	}

	public static function link_atts(): string {
		return 'rel="nofollow sponsored noopener" target="_blank"';
	}

	private static function disclosure_html(): string {
		if ( ! (int) Amz_Inserts_Settings::get( 'show_disclosure', 0 ) ) {
			return '';
		}

		$text = trim( (string) Amz_Inserts_Settings::get( 'disclosure', '' ) );
		if ( '' === $text ) {
			return '';
		}

		return '<p class="amz-inserts__disclosure">' . esc_html( $text ) . '</p>';
	}
}
