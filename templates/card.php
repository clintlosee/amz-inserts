<?php
$item = $item ?? $items[0];
?>
<a class="amz-inserts__card" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo Amz_Inserts_Renderer::link_atts(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php
	if ( $item['image_html'] ) {
		echo $item['image_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image
	}
	?>
	<?php if ( $item['title'] !== '' ) : ?>
		<span class="amz-inserts__title"><?php echo esc_html( $item['title'] ); ?></span>
	<?php endif; ?>
</a>
