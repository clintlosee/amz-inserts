<div class="amz-inserts amz-inserts--text">
	<?php foreach ( $items as $item ) : ?>
		<a class="amz-inserts__link" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo Amz_Inserts_Renderer::link_atts(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $item['title'] !== '' ? $item['title'] : $item['url'] ); ?></a>
	<?php endforeach; ?>
</div>
