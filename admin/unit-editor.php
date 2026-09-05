<?php
/**
 * Unit editor metabox: display type, columns, product repeater.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Unit_Editor {

	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'metaboxes' ) );
		add_action( 'save_post_' . Amz_Inserts_Cpt_Unit::POST_TYPE, array( self::class, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function metaboxes(): void {
		add_meta_box(
			'amz_inserts_unit',
			__( 'Products', 'amz-inserts' ),
			array( self::class, 'render' ),
			Amz_Inserts_Cpt_Unit::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'amz_inserts_shortcode',
			__( 'Insert', 'amz-inserts' ),
			array( self::class, 'render_shortcode' ),
			Amz_Inserts_Cpt_Unit::POST_TYPE,
			'side',
			'high'
		);
	}

	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || Amz_Inserts_Cpt_Unit::POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'amz-inserts-admin',
			AMZ_INSERTS_URL . 'admin/css/unit-editor.css',
			array(),
			AMZ_INSERTS_VERSION
		);

		$deps = array( 'jquery' );
		if ( 'edit.php' !== $hook ) {
			wp_enqueue_media();
			$deps[] = 'wp-api-fetch';
		}

		wp_enqueue_script(
			'amz-inserts-unit-editor',
			AMZ_INSERTS_URL . 'admin/js/unit-editor.js',
			$deps,
			AMZ_INSERTS_VERSION,
			true
		);
		wp_localize_script(
			'amz-inserts-unit-editor',
			'amzInsertsAdmin',
			array(
				'previewPath' => '/amz-inserts/v1/preview',
				'i18n'        => array(
					'selectImage'   => __( 'Select image', 'amz-inserts' ),
					'useImage'      => __( 'Use image', 'amz-inserts' ),
					'fetching'      => __( 'Fetching…', 'amz-inserts' ),
					'fetchFailed'   => __( 'Could not fetch details. Paste an image URL or select an image.', 'amz-inserts' ),
					'invalidUrl'    => __( 'Enter an Amazon URL first.', 'amz-inserts' ),
					'imageFromAsin' => __( 'Using the standard Amazon image for this ASIN.', 'amz-inserts' ),
					'copied'        => __( 'Copied', 'amz-inserts' ),
				),
			)
		);
	}

	public static function render_shortcode( WP_Post $post ): void {
		if ( 'auto-draft' === $post->post_status || $post->ID <= 0 ) {
			echo '<p>' . esc_html__( 'Save this unit to get a shortcode.', 'amz-inserts' ) . '</p>';
			return;
		}

		echo '<p>' . Amz_Inserts_Cpt_Unit::shortcode_copy_markup( (int) $post->ID ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper
		echo '<p class="description">' . esc_html__( 'Click to copy. Paste this into a classic post, or choose this unit from the Amazon Insert block.', 'amz-inserts' ) . '</p>';
		if ( current_user_can( 'manage_options' ) ) {
			echo '<p class="description">' . wp_kses(
				sprintf(
					/* translators: %s: settings page URL */
					__( 'Need a one-off link without a unit? Use <code>[amz_link asin="B0XXXXXXXX"]</code> with the product ASIN. Examples are on <a href="%s">Settings</a>.', 'amz-inserts' ),
					esc_url( admin_url( 'edit.php?post_type=' . Amz_Inserts_Cpt_Unit::POST_TYPE . '&page=amz-inserts-settings' ) )
				),
				array(
					'code' => array(),
					'a'    => array( 'href' => array() ),
				)
			) . '</p>';
		} else {
			echo '<p class="description">' . wp_kses(
				__( 'Need a one-off link without a unit? Use <code>[amz_link asin="B0XXXXXXXX"]</code> with the 10-character product ASIN (not an amzn.to short link).', 'amz-inserts' ),
				array( 'code' => array() )
			) . '</p>';
		}
		Amz_Inserts_Cpt_Unit::render_usage_summary( (int) $post->ID );
	}

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'amz_inserts_unit', 'amz_inserts_unit_nonce' );

		$display   = Amz_Inserts_Cpt_Unit::get_display( (int) $post->ID );
		$columns   = Amz_Inserts_Cpt_Unit::get_columns( (int) $post->ID );
		$items     = Amz_Inserts_Cpt_Unit::get_items( (int) $post->ID );
		$cta_label = Amz_Inserts_Cpt_Unit::get_cta_label( (int) $post->ID );
		if ( empty( $items ) ) {
			$items = array(
				array(
					'url'       => '',
					'title'     => '',
					'image_id'  => 0,
					'image_url' => '',
					'asin'      => '',
				),
			);
		}
		?>
		<p>
			<strong><?php esc_html_e( 'Display', 'amz-inserts' ); ?></strong>
		</p>
		<p class="amz-inserts-display">
			<?php foreach ( Amz_Inserts_Cpt_Unit::display_types() as $value => $label ) : ?>
				<label>
					<input type="radio" name="amz_display" value="<?php echo esc_attr( $value ); ?>" <?php checked( $display, $value ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</p>
		<p class="amz-inserts-columns" <?php echo 'grid' === $display ? '' : 'hidden'; ?>>
			<label for="amz_columns"><strong><?php esc_html_e( 'Max columns', 'amz-inserts' ); ?></strong></label>
			<select name="amz_columns" id="amz_columns">
				<?php foreach ( array( 2, 3, 4 ) as $n ) : ?>
					<option value="<?php echo esc_attr( (string) $n ); ?>" <?php selected( $columns, $n ); ?>><?php echo esc_html( (string) $n ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( '2 columns on phones, then 3, then this many on large screens.', 'amz-inserts' ); ?></span>
		</p>
		<p>
			<label for="amz_cta_label"><strong><?php esc_html_e( 'CTA label', 'amz-inserts' ); ?></strong></label>
			<input type="text" class="widefat" name="amz_cta_label" id="amz_cta_label" value="<?php echo esc_attr( $cta_label ); ?>" />
			<span class="description"><?php esc_html_e( 'Optional. Leave empty to use the default CTA label from Settings.', 'amz-inserts' ); ?></span>
		</p>

		<div id="amz-inserts-items">
			<?php foreach ( $items as $index => $item ) : ?>
				<?php self::item_row( (int) $index, $item ); ?>
			<?php endforeach; ?>
		</div>
		<p>
			<button type="button" class="button" id="amz-inserts-add-item"><?php esc_html_e( 'Add product', 'amz-inserts' ); ?></button>
		</p>
		<template id="amz-inserts-item-template">
			<?php
			self::item_row(
				'__i__',
				array(
					'url'       => '',
					'title'     => '',
					'image_id'  => 0,
					'image_url' => '',
					'asin'      => '',
				)
			);
			?>
		</template>
		<?php
	}

	/**
	 * @param int|string $index
	 * @param array      $item
	 */
	private static function item_row( $index, array $item ): void {
		$image_id  = absint( $item['image_id'] ?? 0 );
		$image_url = (string) ( $item['image_url'] ?? '' );
		$thumb     = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail' ) : '';
		if ( ! $thumb && $image_url ) {
			$thumb = sprintf( '<img src="%s" alt="" />', esc_url( $image_url ) );
		}
		$tagged = ! empty( $item['url'] ) ? Amz_Inserts_Url::with_tag( (string) $item['url'] ) : '';
		?>
		<div class="amz-inserts-item">
			<p>
				<label><?php esc_html_e( 'Amazon URL', 'amz-inserts' ); ?></label>
				<input type="url" class="widefat amz-item-url" name="amz_items[<?php echo esc_attr( (string) $index ); ?>][url]" value="<?php echo esc_attr( (string) ( $item['url'] ?? '' ) ); ?>" />
			</p>
			<p>
				<button type="button" class="button amz-item-fetch"><?php esc_html_e( 'Fetch from URL', 'amz-inserts' ); ?></button>
				<span class="amz-item-fetch-status"></span>
			</p>
			<?php if ( $tagged ) : ?>
				<p class="description amz-item-tagged"><?php echo esc_html( $tagged ); ?></p>
			<?php else : ?>
				<p class="description amz-item-tagged" hidden></p>
			<?php endif; ?>
			<p>
				<label><?php esc_html_e( 'Title', 'amz-inserts' ); ?></label>
				<input type="text" class="widefat amz-item-title" name="amz_items[<?php echo esc_attr( (string) $index ); ?>][title]" value="<?php echo esc_attr( (string) ( $item['title'] ?? '' ) ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Image URL', 'amz-inserts' ); ?></label>
				<input type="url" class="widefat amz-item-image-url" name="amz_items[<?php echo esc_attr( (string) $index ); ?>][image_url]" value="<?php echo esc_attr( $image_url ); ?>" placeholder="https://" />
				<span class="description"><?php esc_html_e( 'Paste an Amazon product image address, or use Select image to upload.', 'amz-inserts' ); ?></span>
			</p>
			<p class="amz-item-image-row">
				<input type="hidden" class="amz-item-image-id" name="amz_items[<?php echo esc_attr( (string) $index ); ?>][image_id]" value="<?php echo esc_attr( (string) $image_id ); ?>" />
				<input type="hidden" class="amz-item-asin" name="amz_items[<?php echo esc_attr( (string) $index ); ?>][asin]" value="<?php echo esc_attr( (string) ( $item['asin'] ?? '' ) ); ?>" />
				<span class="amz-item-thumb"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attachment html or escaped img ?></span>
				<button type="button" class="button amz-item-image"><?php esc_html_e( 'Select image', 'amz-inserts' ); ?></button>
				<button type="button" class="button-link amz-item-remove"><?php esc_html_e( 'Remove', 'amz-inserts' ); ?></button>
			</p>
		</div>
		<?php
	}

	public static function save( int $post_id ): void {
		if ( ! isset( $_POST['amz_inserts_unit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['amz_inserts_unit_nonce'] ) ), 'amz_inserts_unit' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$display = sanitize_key( wp_unslash( $_POST['amz_display'] ?? 'card' ) );
		if ( ! isset( Amz_Inserts_Cpt_Unit::display_types()[ $display ] ) ) {
			$display = 'card';
		}

		$columns = absint( $_POST['amz_columns'] ?? 4 );
		if ( $columns < 2 || $columns > 4 ) {
			$columns = 4;
		}

		$items     = Amz_Inserts_Cpt_Unit::sanitize_items( wp_unslash( $_POST['amz_items'] ?? array() ) );
		$cta_label = sanitize_text_field( wp_unslash( $_POST['amz_cta_label'] ?? '' ) );

		$items = Amz_Inserts_Fetch::expand_item_urls( $items );
		$items = Amz_Inserts_Image::ensure_items( $items, $post_id );

		update_post_meta( $post_id, Amz_Inserts_Cpt_Unit::META_DISPLAY, $display );
		update_post_meta( $post_id, Amz_Inserts_Cpt_Unit::META_COLUMNS, $columns );
		update_post_meta( $post_id, Amz_Inserts_Cpt_Unit::META_ITEMS, $items );
		update_post_meta( $post_id, Amz_Inserts_Cpt_Unit::META_CTA_LABEL, $cta_label );
	}
}
