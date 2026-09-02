<?php
/**
 * Copy Amazon product images into the Media Library so units keep working
 * when a hotlinked image URL rots.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Image {

	public const META_SOURCE_HASH = '_amz_inserts_source_hash';
	public const META_SOURCE_URL  = '_amz_inserts_source_url';
	public const META_ASIN        = '_amz_inserts_asin';

	/**
	 * Amazon serves a placeholder a few dozen bytes long for unknown ASINs.
	 */
	private const MIN_BYTES = 1024;

	private const MAX_BYTES = 8388608;

	public static function enabled(): bool {
		return (bool) apply_filters( 'amz_inserts_sideload_images', true );
	}

	/**
	 * Best-effort sideload for every item that has no attachment yet.
	 *
	 * @param array $items Sanitized items.
	 */
	public static function ensure_items( array $items, int $post_id = 0 ): array {
		if ( ! self::enabled() || ! current_user_can( 'upload_files' ) ) {
			return $items;
		}

		foreach ( $items as $index => $item ) {
			if ( is_array( $item ) ) {
				$items[ $index ] = self::ensure_item( $item, $post_id );
			}
		}

		return $items;
	}

	/**
	 * @param array $item Sanitized item.
	 */
	public static function ensure_item( array $item, int $post_id = 0 ): array {
		if ( absint( $item['image_id'] ?? 0 ) > 0 ) {
			return $item;
		}

		$asin   = (string) ( $item['asin'] ?? '' );
		$source = self::source_url( (string) ( $item['image_url'] ?? '' ), $asin );
		if ( '' === $source ) {
			return $item;
		}

		$attachment_id = self::sideload( $source, (string) ( $item['title'] ?? '' ), $asin, $post_id );
		if ( $attachment_id > 0 ) {
			$item['image_id'] = $attachment_id;
		}

		return $item;
	}

	/**
	 * The remote address worth importing: the product photo on the item, else
	 * Amazon's ASIN image endpoint. An image hosted somewhere other than Amazon
	 * was chosen deliberately, so leave it alone rather than replacing it with
	 * an ASIN import.
	 */
	public static function source_url( string $image_url, string $asin ): string {
		$image_url = Amz_Inserts_Url::normalize_image_url( $image_url );
		if ( '' !== $image_url ) {
			return Amz_Inserts_Url::is_amazon_image_url( $image_url ) ? $image_url : '';
		}

		return Amz_Inserts_Url::asin_image_url( $asin );
	}

	/**
	 * Downloads an Amazon image and adds it to the Media Library. Total by
	 * design: a broken image must never cost an editor their unit.
	 *
	 * @return int Attachment ID, or 0 when anything went wrong.
	 */
	public static function sideload( string $url, string $title = '', string $asin = '', int $post_id = 0 ): int {
		try {
			return self::import( $url, $title, $asin, $post_id );
		} catch ( Throwable ) {
			return 0;
		}
	}

	private static function import( string $url, string $title, string $asin, int $post_id ): int {
		if ( ! self::enabled() || ! current_user_can( 'upload_files' ) ) {
			return 0;
		}

		$url = Amz_Inserts_Url::normalize_image_url( $url );
		if ( '' === $url || ! Amz_Inserts_Url::is_amazon_image_url( $url ) ) {
			return 0;
		}

		$existing = self::find_existing( $url );
		if ( $existing > 0 ) {
			return $existing;
		}

		if ( self::recently_failed( $url ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$download = self::download( $url, $asin );
		if ( is_wp_error( $download ) ) {
			self::remember_failure( $url );
			return 0;
		}

		$file_array = array(
			'name'     => $download['name'],
			'tmp_name' => $download['file'],
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id, self::description( $title, $asin ) );
		if ( is_wp_error( $attachment_id ) ) {
			self::cleanup( $download['file'] );
			self::remember_failure( $url );
			return 0;
		}

		$attachment_id = (int) $attachment_id;
		update_post_meta( $attachment_id, self::META_SOURCE_HASH, md5( $url ) );
		update_post_meta( $attachment_id, self::META_SOURCE_URL, $url );

		if ( '' !== $asin ) {
			update_post_meta( $attachment_id, self::META_ASIN, $asin );
		}

		if ( '' !== $title ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
		}

		return $attachment_id;
	}

	/**
	 * @return array{file:string,name:string}|WP_Error
	 */
	private static function download( string $url, string $asin ): array|WP_Error {
		$tmp = wp_tempnam( $url );
		if ( ! $tmp ) {
			return new WP_Error( 'amz_inserts_tmp', __( 'Could not create a temporary file.', 'amz-inserts' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'redirection'         => 3,
				'stream'              => true,
				'filename'            => $tmp,
				'limit_response_size' => self::MAX_BYTES,
				'user-agent'          => 'Mozilla/5.0 (compatible; AmazonInserts/1.0; +https://wordpress.org/)',
			)
		);

		if ( is_wp_error( $response ) ) {
			self::cleanup( $tmp );
			return $response;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			self::cleanup( $tmp );
			return new WP_Error( 'amz_inserts_http', __( 'The image could not be downloaded.', 'amz-inserts' ) );
		}

		$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		$type = trim( explode( ';', $type )[0] );
		if ( ! str_starts_with( $type, 'image/' ) ) {
			self::cleanup( $tmp );
			return new WP_Error( 'amz_inserts_type', __( 'That address did not return an image.', 'amz-inserts' ) );
		}

		$size = file_exists( $tmp ) ? (int) filesize( $tmp ) : 0;
		if ( $size < self::MIN_BYTES || $size > self::MAX_BYTES ) {
			self::cleanup( $tmp );
			return new WP_Error( 'amz_inserts_size', __( 'The image was empty or too large.', 'amz-inserts' ) );
		}

		return array(
			'file' => $tmp,
			'name' => self::filename( $url, $asin, $type ),
		);
	}

	private static function filename( string $url, string $asin, string $mime ): string {
		$extensions = array(
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
		);
		$extension  = $extensions[ $mime ] ?? 'jpg';

		if ( '' !== $asin ) {
			$base = 'amazon-' . strtolower( $asin );
		} else {
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$base = pathinfo( $path, PATHINFO_FILENAME );
			$base = sanitize_title( (string) $base );
		}

		if ( '' === $base ) {
			$base = 'amazon-product-image';
		}

		return sanitize_file_name( $base . '.' . $extension );
	}

	private static function description( string $title, string $asin ): string {
		if ( '' !== $title ) {
			return $title;
		}

		if ( '' !== $asin ) {
			/* translators: %s: Amazon ASIN. */
			return sprintf( __( 'Amazon product %s', 'amz-inserts' ), $asin );
		}

		return __( 'Amazon product image', 'amz-inserts' );
	}

	private static function find_existing( string $url ): int {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_SOURCE_HASH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => md5( $url ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return empty( $ids ) ? 0 : (int) $ids[0];
	}

	/**
	 * Keeps a dead image URL from being re-fetched on every save.
	 */
	private static function recently_failed( string $url ): bool {
		return (bool) get_transient( self::failure_key( $url ) );
	}

	private static function remember_failure( string $url ): void {
		$ttl = (int) apply_filters( 'amz_inserts_sideload_retry_delay', 6 * HOUR_IN_SECONDS, $url );
		if ( $ttl > 0 ) {
			set_transient( self::failure_key( $url ), 1, $ttl );
		}
	}

	private static function failure_key( string $url ): string {
		return 'amz_inserts_dl_fail_' . md5( $url );
	}

	private static function cleanup( string $file ): void {
		if ( '' !== $file && file_exists( $file ) ) {
			wp_delete_file( $file );
		}
	}
}
