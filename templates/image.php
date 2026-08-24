<div class="amz-inserts amz-inserts--image">
	<?php foreach ( $items as $item ) : ?>
		<a class="amz-inserts__image-link" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo Amz_Inserts_Renderer::link_atts(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
			if ( $item['image_html'] ) {
				echo $item['image_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image
			} elseif ( $item['title'] ) {
				echo esc_html( $item['title'] );
			}
			?>
		</a>
	<?php endforeach; ?>
</div>
