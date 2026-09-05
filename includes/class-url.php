<?php
/**
 * Amazon URL parsing and associate-tag injection.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Url {

	/**
	 * Hosts we will store and output as affiliate links.
	 *
	 * @return string[]
	 */
	public static function allowed_host_suffixes(): array {
		return array(
			'amazon.com',
			'amazon.co.uk',
			'amazon.ca',
			'amazon.de',
			'amazon.fr',
			'amazon.it',
			'amazon.es',
			'amazon.co.jp',
			'amazon.in',
			'amazon.com.au',
			'amazon.com.mx',
			'amazon.com.br',
			'amazon.nl',
			'amazon.se',
			'amazon.pl',
			'amazon.sg',
			'amazon.ae',
			'amazon.sa',
			'amazon.com.be',
			'amazon.eg',
			'amzn.to',
			'amzn.com',
			'a.co',
		);
	}

	/**
	 * Image CDN hosts Amazon serves product photos from.
	 *
	 * @return string[]
	 */
	public static function allowed_image_host_suffixes(): array {
		return array_merge(
			array(
				'media-amazon.com',
				'ssl-images-amazon.com',
				'images-amazon.com',
			),
			self::allowed_host_suffixes()
		);
	}

	public static function is_amazon_url( string $url ): bool {
		return self::host_matches( $url, self::allowed_host_suffixes() );
	}

	/**
	 * True for image addresses we are willing to download and sideload.
	 */
	public static function is_amazon_image_url( string $url ): bool {
		return self::host_matches( $url, self::allowed_image_host_suffixes() );
	}

	/**
	 * @param string[] $suffixes
	 */
	private static function host_matches( string $url, array $suffixes ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$host = strtolower( $host );
		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		foreach ( $suffixes as $suffix ) {
			if ( $host === $suffix || str_ends_with( $host, '.' . $suffix ) ) {
				return true;
			}
		}

		return false;
	}

	public static function extract_asin( string $url ): string {
		$patterns = array(
			'#/(?:dp|gp/product|gp/aw/d|gp/offer-listing|product|exec/obidos/ASIN|exec/obidos/tg/detail/-)/([A-Z0-9]{10})#i',
			'#[?&]asin=([A-Z0-9]{10})#i',
			'#/images/P/([A-Z0-9]{10})[._]#i',
			'#/([B][A-Z0-9]{9})(?:[/?]|$)#i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				return strtoupper( $matches[1] );
			}
		}

		return '';
	}

	public static function with_tag( string $url, string $tag = '' ): string {
		if ( '' === $tag ) {
			$tag = (string) Amz_Inserts_Settings::get( 'associate_tag', '' );
		}

		$tag = sanitize_text_field( $tag );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $url;
		}

		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		if ( '' !== $tag && empty( $query['tag'] ) ) {
			$query['tag'] = $tag;
		}

		$scheme   = $parts['scheme'] ?? 'https';
		$host     = $parts['host'];
		$path     = $parts['path'] ?? '';
		$fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
		$built    = $scheme . '://' . $host . $path;

		if ( ! empty( $query ) ) {
			$built .= '?' . http_build_query( $query );
		}

		return $built . $fragment;
	}

	public static function normalize( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$url = esc_url_raw( $url );
		if ( '' === $url || ! self::is_amazon_url( $url ) ) {
			return '';
		}

		return $url;
	}

	public static function normalize_image_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * 10-character ASIN or empty.
	 */
	public static function sanitize_asin( string $asin ): string {
		$asin = preg_replace( '/[^A-Z0-9]/', '', strtoupper( $asin ) ) ?? '';

		return 10 === strlen( $asin ) ? $asin : '';
	}

	public static function asin_image_url( string $asin ): string {
		$asin = self::sanitize_asin( $asin );
		if ( '' === $asin ) {
			return '';
		}

		return 'https://m.media-amazon.com/images/P/' . $asin . '.01._SCLZZZZZZZ_.jpg';
	}

	/**
	 * Product URL for an ASIN on amazon.com. There is no marketplace setting.
	 */
	public static function from_asin( string $asin ): string {
		$asin = self::sanitize_asin( $asin );
		if ( '' === $asin ) {
			return '';
		}

		return 'https://www.amazon.com/dp/' . $asin;
	}
}
