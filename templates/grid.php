<div class="amz-inserts amz-inserts--grid" style="--amz-cols: <?php echo esc_attr( (string) $columns ); ?>">
	<?php foreach ( $items as $item ) : ?>
		<?php include __DIR__ . '/card.php'; ?>
	<?php endforeach; ?>
</div>
