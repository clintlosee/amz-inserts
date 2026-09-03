<?php
/**
 * REST helpers: list units and best-effort product preview from a URL.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Fetch {

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			'amz-inserts/v1',
			'/units',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( self::class, 'can_edit' ),
				'callback'            => array( self::class, 'units' ),
			)
		);

		register_rest_route(
			'amz-inserts/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( self::class, 'can_edit' ),
				'callback'            => array( self::class, 'preview' ),
				'args'                => array(
					'url' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	public static function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function units(): WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => Amz_Inserts_Cpt_Unit::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$data = array();
		foreach ( $posts as $post ) {
			$data[] = array(
				'id'    => (int) $post->ID,
				'title' => $post->post_title,
			);
		}

		return rest_ensure_response( $data );
	}

	public static function preview( WP_REST_Request $request ): WP_REST_Response {
		$url = Amz_Inserts_Url::normalize( (string) $request->get_param( 'url' ) );
		if ( '' === $url ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'fetched' => false,
					'message' => __( 'That is not a recognized Amazon URL.', 'amz-inserts' ),
				)
			);
		}

		$asin         = Amz_Inserts_Url::extract_asin( $url );
		$tagged_url   = Amz_Inserts_Url::with_tag( $url );
		$title        = '';
		$image_url    = '';
		$image_source = '';
		$fetched      = false;

		$remote = self::request( $url );
		if ( ! is_wp_error( $remote ) ) {
			$html      = (string) wp_remote_retrieve_body( $remote );
			$final_url = self::effective_url( $remote, $url );
			$final_url = Amz_Inserts_Url::normalize( $final_url );

			// If final URL after redirects is not an Amazon URL, discard fetched data
			if ( '' === $final_url ) {
				$html      = '';
				$final_url = $url;
			} else {
				if ( '' === $asin ) {
					$asin = Amz_Inserts_Url::extract_asin( $final_url );
				}
			}

			if ( '' !== $html ) {
				$title = self::meta_content( $html, 'og:title' );
				if ( '' === $title ) {
					$title = self::page_title( $html );
				}

				if ( '' === $asin ) {
					$asin = self::asin_from_html( $html );
				}

				$image_url = Amz_Inserts_Url::normalize_image_url( self::meta_content( $html, 'og:image' ) );
				if ( '' !== $image_url ) {
					$image_source = 'og';
					if ( '' === $asin ) {
						$asin = Amz_Inserts_Url::extract_asin( $image_url );
					}
				}

				$fetched = ( '' !== $title || '' !== $image_url );
			}
		}

		if ( '' === $image_url && '' !== $asin ) {
			$image_url    = Amz_Inserts_Url::asin_image_url( $asin );
			$image_source = '' !== $image_url ? 'asin' : '';
		}

		return rest_ensure_response(
			array(
				'ok'           => true,
				'fetched'      => $fetched || '' !== $image_url,
				'url'          => $url,
				'tagged_url'   => $tagged_url,
				'asin'         => $asin,
				'title'        => $title,
				'image_id'     => 0,
				'image_url'    => $image_url,
				'image_source' => $image_source,
			)
		);
	}

	/**
	 * Last resort ASIN lookup for short links whose path carries no ASIN.
	 */
	private static function asin_from_html( string $html ): string {
		$patterns = array(
			'#<link[^>]+rel=[\'"]canonical[\'"][^>]+href=[\'"]([^\'"]+)[\'"]#i',
			'#<meta[^>]+(?:property|name)=[\'"]og:url[\'"][^>]+content=[\'"]([^\'"]+)[\'"]#i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $html, $matches ) ) {
				$asin = Amz_Inserts_Url::extract_asin( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( '' !== $asin ) {
					return $asin;
				}
			}
		}

		if ( preg_match( '#data-asin=[\'"]([A-Z0-9]{10})[\'"]#i', $html, $matches ) ) {
			return strtoupper( $matches[1] );
		}

		return '';
	}

	private static function request( string $url ): array|WP_Error {
		return wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 5,
				'user-agent'  => 'Mozilla/5.0 (compatible; AmazonInserts/1.0; +https://wordpress.org/)',
			)
		);
	}

	private static function effective_url( array $response, string $fallback ): string {
		if ( isset( $response['http_response'] ) && is_object( $response['http_response'] ) && method_exists( $response['http_response'], 'get_response_object' ) ) {
			$object = $response['http_response']->get_response_object();
			if ( is_object( $object ) && ! empty( $object->url ) ) {
				return (string) $object->url;
			}
		}

		return $fallback;
	}

	private static function meta_content( string $html, string $property ): string {
		$quoted = preg_quote( $property, '#' );
		$patterns = array(
			'#<meta[^>]+(?:property|name)=[\'"]' . $quoted . '[\'"][^>]+content=[\'"]([^\'"]*)[\'"]#i',
			'#<meta[^>]+content=[\'"]([^\'"]*)[\'"][^>]+(?:property|name)=[\'"]' . $quoted . '[\'"]#i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $html, $matches ) ) {
				return self::clean_text( $matches[1] );
			}
		}

		return '';
	}

	private static function page_title( string $html ): string {
		if ( preg_match( '#<title[^>]*>([^<]+)</title>#i', $html, $matches ) ) {
			return self::clean_text( $matches[1] );
		}

		return '';
	}

	private static function clean_text( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = wp_strip_all_tags( $text );

		return sanitize_text_field( $text );
	}
}
