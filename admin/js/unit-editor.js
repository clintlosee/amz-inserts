(function ($) {
	'use strict';

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			var input = document.createElement('textarea');
			input.value = text;
			input.setAttribute('readonly', '');
			input.style.position = 'absolute';
			input.style.left = '-9999px';
			document.body.appendChild(input);
			input.select();
			try {
				document.execCommand('copy') ? resolve() : reject();
			} catch (err) {
				reject(err);
			}
			document.body.removeChild(input);
		});
	}

	$(document).on('click', '.amz-inserts-copy', function (event) {
		event.preventDefault();
		var $btn = $(this);
		var text = String($btn.attr('data-shortcode') || $btn.text() || '').trim();
		if (!text) {
			return;
		}

		copyText(text).then(function () {
			var prev = $btn.attr('title');
			$btn.addClass('is-copied');
			$btn.attr('title', (window.amzInsertsAdmin && amzInsertsAdmin.i18n && amzInsertsAdmin.i18n.copied) || 'Copied');
			window.setTimeout(function () {
				$btn.removeClass('is-copied');
				$btn.attr('title', prev);
			}, 1200);
		}).catch(function () {});
	});

	function nextIndex() {
		var max = -1;
		$('#amz-inserts-items .amz-inserts-item').each(function () {
			$(this)
				.find('[name]')
				.each(function () {
					var match = $(this)
						.attr('name')
						.match(/amz_items\[(\d+)\]/);
					if (match) {
						max = Math.max(max, parseInt(match[1], 10));
					}
				});
		});
		return max + 1;
	}

	function reindexNames($row, index) {
		$row.find('[name]').each(function () {
			var name = $(this).attr('name');
			if (name) {
				$(this).attr('name', name.replace('__i__', String(index)).replace(/amz_items\[\d+\]/, 'amz_items[' + index + ']'));
			}
		});
	}

	$(document).on('change', 'input[name="amz_display"]', function () {
		$('.amz-inserts-columns').prop('hidden', $(this).val() !== 'grid');
	});

	$('#amz-inserts-add-item').on('click', function () {
		var template = document.getElementById('amz-inserts-item-template');
		if (!template) {
			return;
		}
		var $row = $(template.innerHTML);
		reindexNames($row, nextIndex());
		$('#amz-inserts-items').append($row);
	});

	$(document).on('click', '.amz-item-remove', function () {
		var $items = $('#amz-inserts-items .amz-inserts-item');
		if ($items.length < 2) {
			$(this).closest('.amz-inserts-item').find('input[type="text"], input[type="url"], input[type="hidden"]').val('');
			$(this).closest('.amz-inserts-item').find('.amz-item-thumb').empty();
			return;
		}
		$(this).closest('.amz-inserts-item').remove();
	});

	function setThumb($row, src) {
		if (src && /^https?:\/\//i.test(src)) {
			$row.find('.amz-item-thumb').html('<img src="' + src.replace(/"/g, '&quot;') + '" alt="" />');
		} else {
			$row.find('.amz-item-thumb').empty();
		}
	}

	$(document).on('click', '.amz-item-image', function (event) {
		event.preventDefault();
		var $row = $(this).closest('.amz-inserts-item');
		var frame = wp.media({
			title: amzInsertsAdmin.i18n.selectImage,
			button: { text: amzInsertsAdmin.i18n.useImage },
			multiple: false,
			library: { type: 'image' },
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			$row.find('.amz-item-image-id').val(attachment.id);
			var src = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
			setThumb($row, src);
		});
		frame.open();
	});

	$(document).on('input', '.amz-item-image-url', function () {
		var $row = $(this).closest('.amz-inserts-item');
		$row.find('.amz-item-image-id').val('');
		setThumb($row, $.trim($(this).val()));
	});

	$(document).on('click', '.amz-item-fetch', function (event) {
		event.preventDefault();
		var $row = $(this).closest('.amz-inserts-item');
		var $status = $row.find('.amz-item-fetch-status');
		var url = $.trim($row.find('.amz-item-url').val());
		if (!url) {
			$status.text(amzInsertsAdmin.i18n.invalidUrl);
			return;
		}

		$status.text(amzInsertsAdmin.i18n.fetching);
		wp.apiFetch({
			path: amzInsertsAdmin.previewPath,
			method: 'POST',
			data: { url: url },
		})
			.then(function (data) {
				if (!data || data.ok === false) {
					$status.text((data && data.message) || amzInsertsAdmin.i18n.fetchFailed);
					return;
				}
				if (data.tagged_url) {
					$row.find('.amz-item-url').val(data.tagged_url);
					$row.find('.amz-item-tagged').text(data.tagged_url).prop('hidden', false);
				}
				if (data.asin) {
					$row.find('.amz-item-asin').val(data.asin);
				}
				if (data.title && !$row.find('.amz-item-title').val()) {
					$row.find('.amz-item-title').val(data.title);
				}
				if (data.image_url && !$row.find('.amz-item-image-url').val()) {
					$row.find('.amz-item-image-url').val(data.image_url);
					if (!$row.find('.amz-item-image-id').val()) {
						setThumb($row, data.image_url);
					}
				}

				var notes = [];
				if (!data.fetched) {
					notes.push(amzInsertsAdmin.i18n.fetchFailed);
				}
				if (data.image_source === 'asin') {
					notes.push(amzInsertsAdmin.i18n.imageFromAsin);
				}
				$status.text(notes.join(' '));
			})
			.catch(function () {
				$status.text(amzInsertsAdmin.i18n.fetchFailed);
			});
	});
})(jQuery);
