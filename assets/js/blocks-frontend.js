/**
 * VQ Checkout Blocks Frontend
 */
(function() {
	'use strict';

	const { registerCheckoutBlock } = window.wc.blocksCheckout;
	const { createElement, useState, useEffect } = window.wp.element;
	const { SelectControl } = window.wp.components;
	const { __ } = window.wp.i18n;

	/**
	 * VN Address Fields Block Component
	 */
	const VNAddressBlock = ({ checkoutExtensionData }) => {
		const { setExtensionData } = checkoutExtensionData;

		const [provinces, setProvinces] = useState([]);
		const [districts, setDistricts] = useState([]);
		const [wards, setWards] = useState([]);

		const [selectedProvince, setSelectedProvince] = useState('');
		const [selectedDistrict, setSelectedDistrict] = useState('');
		const [selectedWard, setSelectedWard] = useState('');
		const [selectedGender, setSelectedGender] = useState('');

		const [loading, setLoading] = useState(false);

		// Load provinces on mount
		useEffect(() => {
			loadProvinces();
		}, []);

		// Update extension data when fields change
		useEffect(() => {
			setExtensionData('vqcheckout', {
				province: selectedProvince,
				district: selectedDistrict,
				ward: selectedWard,
				gender: selectedGender
			});
		}, [selectedProvince, selectedDistrict, selectedWard, selectedGender, setExtensionData]);

		const loadProvinces = async () => {
			try {
				const response = await fetch(`${window.vqCheckoutBlocks.restUrl}/address/provinces`);
				const data = await response.json();
				setProvinces(data);
			} catch (error) {
				console.error('Load provinces error:', error);
			}
		};

		const loadDistricts = async (provinceCode) => {
			if (!provinceCode) {
				setDistricts([]);
				setWards([]);
				return;
			}

			setLoading(true);
			try {
				const response = await fetch(
					`${window.vqCheckoutBlocks.restUrl}/address/districts?province=${provinceCode}`
				);
				const data = await response.json();
				setDistricts(data);
				setWards([]);
				setSelectedDistrict('');
				setSelectedWard('');
			} catch (error) {
				console.error('Load districts error:', error);
			} finally {
				setLoading(false);
			}
		};

		const loadWards = async (districtCode) => {
			if (!districtCode) {
				setWards([]);
				return;
			}

			setLoading(true);
			try {
				const response = await fetch(
					`${window.vqCheckoutBlocks.restUrl}/address/wards?district=${districtCode}`
				);
				const data = await response.json();
				setWards(data);
				setSelectedWard('');
			} catch (error) {
				console.error('Load wards error:', error);
			} finally {
				setLoading(false);
			}
		};

		const handleProvinceChange = (value) => {
			setSelectedProvince(value);
			loadDistricts(value);
		};

		const handleDistrictChange = (value) => {
			setSelectedDistrict(value);
			loadWards(value);
		};

		const handleWardChange = (value) => {
			setSelectedWard(value);
		};

		const handleGenderChange = (value) => {
			setSelectedGender(value);
		};

		const provinceOptions = [
			{ value: '', label: window.vqCheckoutBlocks.i18n.selectProvince },
			...provinces.map(p => ({ value: p.code, label: p.name_with_type }))
		];

		const districtOptions = [
			{ value: '', label: window.vqCheckoutBlocks.i18n.selectDistrict },
			...districts.map(d => ({ value: d.code, label: d.name_with_type }))
		];

		const wardOptions = [
			{ value: '', label: window.vqCheckoutBlocks.i18n.selectWard },
			...wards.map(w => ({ value: w.code, label: w.name_with_type }))
		];

		const genderOptions = [
			{ value: '', label: '-- Chọn --' },
			{ value: 'anh', label: window.vqCheckoutBlocks.i18n.anh },
			{ value: 'chi', label: window.vqCheckoutBlocks.i18n.chi }
		];

		return createElement(
			'div',
			{ className: 'wc-block-components-vqcheckout-address' },
			createElement(SelectControl, {
				label: window.vqCheckoutBlocks.i18n.gender,
				value: selectedGender,
				options: genderOptions,
				onChange: handleGenderChange,
				className: 'vqcheckout-gender-select'
			}),
			createElement(SelectControl, {
				label: window.vqCheckoutBlocks.i18n.province,
				value: selectedProvince,
				options: provinceOptions,
				onChange: handleProvinceChange,
				required: true,
				className: 'vqcheckout-province-select'
			}),
			createElement(SelectControl, {
				label: window.vqCheckoutBlocks.i18n.district,
				value: selectedDistrict,
				options: districtOptions,
				onChange: handleDistrictChange,
				disabled: !selectedProvince || loading,
				required: true,
				className: 'vqcheckout-district-select'
			}),
			createElement(SelectControl, {
				label: window.vqCheckoutBlocks.i18n.ward,
				value: selectedWard,
				options: wardOptions,
				onChange: handleWardChange,
				disabled: !selectedDistrict || loading,
				required: true,
				className: 'vqcheckout-ward-select'
			})
		);
	};

	// Register the block
	if (registerCheckoutBlock) {
		registerCheckoutBlock({
			metadata: {
				name: 'vqcheckout/address-fields',
				parent: ['woocommerce/checkout-billing-address-block']
			},
			component: VNAddressBlock
		});
	}

})();
