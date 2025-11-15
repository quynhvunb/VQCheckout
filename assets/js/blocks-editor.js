/**
 * VQ Checkout Blocks Editor
 */
(function() {
	'use strict';

	const { __ } = window.wp.i18n;
	const { createElement } = window.wp.element;

	// Register block in editor (placeholder/preview)
	if (window.wp.blocks && window.wp.blocks.registerBlockType) {
		window.wp.blocks.registerBlockType('vqcheckout/address-fields', {
			title: __('VN Address Fields', 'vq-checkout'),
			description: __('Vietnamese address fields (Province, District, Ward)', 'vq-checkout'),
			category: 'woocommerce',
			icon: 'location-alt',
			attributes: {},
			edit: function() {
				return createElement(
					'div',
					{ className: 'vqcheckout-editor-placeholder' },
					createElement('p', null, __('VN Address Fields (Province, District, Ward)', 'vq-checkout')),
					createElement('p', { style: { fontSize: '12px', color: '#666' } },
						__('These fields will appear on the checkout block.', 'vq-checkout')
					)
				);
			},
			save: function() {
				return null;
			}
		});
	}

})();
