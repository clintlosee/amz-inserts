<div class="amz-inserts amz-inserts--button">
	<?php foreach ( $items as $item ) : ?>
		<a class="amz-inserts__cta" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo Amz_Inserts_Renderer::link_atts(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $cta_label ); ?></a>
	<?php endforeach; ?>
</div>
