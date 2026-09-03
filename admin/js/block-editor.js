(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useRef = wp.element.useRef;
	var useEffect = wp.element.useEffect;
	var registerBlockType = wp.blocks.registerBlockType;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var Button = wp.components.Button;
	var RangeControl = wp.components.RangeControl;
	var ServerSideRender = wp.serverSideRender;
	if (ServerSideRender && ServerSideRender.default) {
		ServerSideRender = ServerSideRender.default;
	}

	function ItemEditor(props) {
		var item = props.item || {};
		var onChange = props.onChange;
		var onRemove = props.onRemove;
		var itemRef = useRef(item);
		itemRef.current = item;
		var statusState = useState('');
		var status = statusState[0];
		var setStatus = statusState[1];
		var fetchingState = useState(false);
		var fetching = fetchingState[0];
		var setFetching = fetchingState[1];
		var taggedState = useState('');
		var taggedUrl = taggedState[0];
		var setTaggedUrl = taggedState[1];
		var fetchFailed = __('Could not fetch details. Paste an image URL or select an image.', 'amz-inserts');

		function fetchFromUrl() {
			var url = (item.url || '').trim();
			if (!url) {
				setStatus(__('Enter an Amazon URL first.', 'amz-inserts'));
				return;
			}

			setFetching(true);
			setStatus(__('Fetching…', 'amz-inserts'));
			wp.apiFetch({
				path: '/amz-inserts/v1/preview',
				method: 'POST',
				data: { url: url },
			})
				.then(function (data) {
					setFetching(false);
					if (!data || data.ok === false) {
						setStatus((data && data.message) || fetchFailed);
						return;
					}

					var next = Object.assign({}, itemRef.current || {});
					if (data.tagged_url) {
						next.url = data.tagged_url;
						setTaggedUrl(data.tagged_url);
					}
					if (data.asin) {
						next.asin = data.asin;
					}
					if (data.title && !next.title) {
						next.title = data.title;
					}
					if (data.image_url && !next.imageUrl) {
						next.imageUrl = data.image_url;
					}
					onChange(next);

					var notes = [];
					if (!data.fetched) {
						notes.push(fetchFailed);
					}
					if (data.image_source === 'asin') {
						notes.push(__('Using the standard Amazon image for this ASIN.', 'amz-inserts'));
					}
					setStatus(notes.join(' '));
				})
				.catch(function () {
					setFetching(false);
					setStatus(fetchFailed);
				});
		}

		return el(
			'div',
			{ className: 'amz-inserts-item-editor', style: { marginBottom: '12px', paddingBottom: '12px', borderBottom: '1px solid #ddd' } },
			el(TextControl, {
				label: __('Amazon URL', 'amz-inserts'),
				value: item.url || '',
				onChange: function (url) {
					onChange(Object.assign({}, item, { url: url }));
				},
			}),
			el(
				'p',
				{ style: { marginTop: '0', marginBottom: '12px' } },
				el(
					Button,
					{
						variant: 'secondary',
						onClick: fetchFromUrl,
						disabled: fetching,
					},
					__('Fetch from URL', 'amz-inserts')
				),
				status
					? el(
							'span',
							{
								className: 'amz-item-fetch-status',
								style: { marginLeft: '8px' },
							},
							status
					  )
					: null
			),
			taggedUrl
				? el(
						'p',
						{
							className: 'description amz-item-tagged',
							style: { marginTop: '-4px', marginBottom: '12px', overflowWrap: 'anywhere' },
						},
						taggedUrl
				  )
				: null,
			el(TextControl, {
				label: __('Title', 'amz-inserts'),
				value: item.title || '',
				onChange: function (title) {
					onChange(Object.assign({}, item, { title: title }));
				},
			}),
			el(TextControl, {
				label: __('Image URL', 'amz-inserts'),
				help: __('Paste an Amazon product image address if you do not want to upload.', 'amz-inserts'),
				value: item.imageUrl || '',
				onChange: function (imageUrl) {
					onChange(Object.assign({}, item, { imageUrl: imageUrl }));
				},
			}),
			el(
				MediaUploadCheck,
				null,
				el(MediaUpload, {
					onSelect: function (media) {
						onChange(Object.assign({}, item, { imageId: media.id }));
					},
					allowedTypes: ['image'],
					value: item.imageId,
					render: function (obj) {
						return el(
							Button,
							{ variant: 'secondary', onClick: obj.open },
							item.imageId ? __('Replace uploaded image', 'amz-inserts') : __('Select image', 'amz-inserts')
						);
					},
				})
			),
			el(
				Button,
				{ isDestructive: true, variant: 'link', onClick: onRemove, style: { marginLeft: '8px' } },
				__('Remove product', 'amz-inserts')
			)
		);
	}

	registerBlockType('amz-inserts/insert', {
		apiVersion: 3,
		title: __('Amazon Insert', 'amz-inserts'),
		description: __('Insert a saved Amazon unit or a custom product link, image, card, or grid.', 'amz-inserts'),
		icon: 'cart',
		category: 'widgets',
		attributes: {
			mode: { type: 'string', default: 'saved' },
			unitId: { type: 'number', default: 0 },
			display: { type: 'string', default: 'card' },
			columns: { type: 'number', default: 4 },
			items: { type: 'array', default: [] },
		},
		supports: { html: false },
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({ className: 'amz-inserts-block' });
			var unitsState = useState([]);
			var units = unitsState[0];
			var setUnits = unitsState[1];
			var items = attributes.items || [];

			useEffect(function () {
				wp.apiFetch({ path: '/amz-inserts/v1/units' })
					.then(function (data) {
						setUnits(Array.isArray(data) ? data : []);
					})
					.catch(function () {
						setUnits([]);
					});
			}, []);

			var unitOptions = [{ label: __('Select a unit…', 'amz-inserts'), value: 0 }].concat(
				units.map(function (unit) {
					return { label: unit.title || '#' + unit.id, value: unit.id };
				})
			);

			var hasPreview =
				(attributes.mode === 'saved' && attributes.unitId) ||
				(attributes.mode === 'custom' && items.length > 0);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Amazon Insert', 'amz-inserts'), initialOpen: true },
						el(SelectControl, {
							label: __('Source', 'amz-inserts'),
							value: attributes.mode,
							options: [
								{ label: __('Saved unit', 'amz-inserts'), value: 'saved' },
								{ label: __('Custom', 'amz-inserts'), value: 'custom' },
							],
							onChange: function (mode) {
								setAttributes({ mode: mode });
							},
						}),
						attributes.mode === 'saved'
							? el(SelectControl, {
									label: __('Saved unit', 'amz-inserts'),
									value: attributes.unitId || 0,
									options: unitOptions,
									onChange: function (value) {
										setAttributes({ unitId: parseInt(value, 10) || 0 });
									},
							  })
							: null,
						attributes.mode === 'custom'
							? el(
									Fragment,
									null,
									el(SelectControl, {
										label: __('Display', 'amz-inserts'),
										value: attributes.display,
										options: [
											{ label: __('Text link', 'amz-inserts'), value: 'text' },
											{ label: __('Image', 'amz-inserts'), value: 'image' },
											{ label: __('Card', 'amz-inserts'), value: 'card' },
											{ label: __('Grid', 'amz-inserts'), value: 'grid' },
										],
										onChange: function (display) {
											setAttributes({ display: display });
										},
									}),
									attributes.display === 'grid'
										? el(RangeControl, {
												label: __('Columns', 'amz-inserts'),
												value: attributes.columns,
												min: 2,
												max: 4,
												onChange: function (columns) {
													setAttributes({ columns: columns });
												},
										  })
										: null,
									items.map(function (item, index) {
										return el(ItemEditor, {
											key: index,
											item: item,
											onChange: function (next) {
												var copy = items.slice();
												copy[index] = next;
												setAttributes({ items: copy });
											},
											onRemove: function () {
												setAttributes({
													items: items.filter(function (_item, i) {
														return i !== index;
													}),
												});
											},
										});
									}),
									el(
										Button,
										{
											variant: 'primary',
											onClick: function () {
												setAttributes({
													items: items.concat([{ url: '', title: '', imageId: 0, imageUrl: '', asin: '' }]),
												});
											},
										},
										__('Add product', 'amz-inserts')
									)
							  )
							: null
					)
				),
				el(
					'div',
					blockProps,
					hasPreview
						? el(ServerSideRender, {
								block: 'amz-inserts/insert',
								attributes: attributes,
						  })
						: el(
								'p',
								{ className: 'amz-inserts-placeholder' },
								__('Select a saved unit or add a custom product in the block settings.', 'amz-inserts')
						  )
				)
			);
		},
		save: function () {
			return null;
		},
	});
})(window.wp);
