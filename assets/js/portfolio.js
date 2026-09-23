(function () {
	'use strict';
	const dialog = document.querySelector('#preview');
	const clinicalSample = document.querySelector('#clinical .sample-site');
	const heroSample = document.querySelector('#hero-sample');
	let activeStyle = '';
	if (!dialog || !clinicalSample || !heroSample) return;
	heroSample.append(clinicalSample.cloneNode(true));

	const industryLabels = {
		Essential: ['Local businesses', 'shops · trades · services'],
		Clinical: ['Health practices', 'medical · dental · therapy'],
		Hospitality: ['Places to eat and stay', 'restaurant · café · hotel'],
		Wellness: ['Wellbeing and movement', 'studio · bodywork · retreat'],
		Modern: ['Digital companies', 'technology · SaaS · agency'],
		Elegant: ['Professional expertise', 'law · finance · advisory'],
		Gallery: ['Creative portfolios', 'art · architecture · photography'],
		Amplified: ['Music and live projects', 'band · venue · label'],
	};

	document.querySelectorAll('.style-card').forEach((card) => {
		const labels = industryLabels[card.dataset.style];
		if (!labels) return;
		const heading = card.querySelector('.style-top h3');
		const skin = document.createElement('small');
		skin.textContent = card.dataset.style;
		heading.replaceChildren(skin, document.createTextNode(labels[0]));
		card.querySelector('.style-top > span').textContent = labels[1];
	});

	function choose(style) {
		const choice = document.querySelector('#style-choice');
		choice.value = style;
		if (dialog.open) dialog.close();
		document.querySelector('#contact').scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
		choice.focus({ preventScroll: true });
	}

	function openGuide(card) {
		activeStyle = card.dataset.style;
		document.querySelector('#preview-title').textContent = activeStyle + ' style guide';
		document.querySelector('#modal-heading').textContent = activeStyle;
		document.querySelector('#modal-description').textContent = card.dataset.description;
		document.querySelector('#modal-best').textContent = card.dataset.best;
		document.querySelector('#modal-feel').textContent = card.dataset.feel;
		document.querySelector('#modal-pages').textContent = card.dataset.pages;
		document.querySelector('#modal-sample').replaceChildren(card.querySelector('.sample-site').cloneNode(true));
		const palette = document.querySelector('#modal-palette');
		palette.replaceChildren(...card.dataset.palette.split('|').map((color) => {
			const chip = document.createElement('i');
			chip.style.background = color;
			chip.title = color;
			return chip;
		}));
		dialog.showModal();
	}

	document.querySelectorAll('.style-card').forEach((card) => card.querySelectorAll('[data-preview]').forEach((button) => button.addEventListener('click', () => openGuide(card))));
	document.querySelector('.close').addEventListener('click', () => dialog.close());
	dialog.addEventListener('click', (event) => {
		if (event.target !== dialog) return;
		const rect = dialog.getBoundingClientRect();
		if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
	});
	document.querySelector('#preview-choose').addEventListener('click', () => choose(activeStyle));
	document.querySelector('#enquiry').addEventListener('submit', (event) => {
		event.preventDefault();
		const values = new FormData(event.currentTarget);
		const subject = 'Website enquiry from ' + values.get('name');
		const body = ['Hi Alex,', '', 'My name is ' + values.get('name') + '.', 'Email: ' + values.get('email'), 'Business or current website: ' + (values.get('business') || 'Not provided'), 'Direction: ' + values.get('style'), '', 'What I need the website to help with:', String(values.get('message'))].join('\n');
		document.querySelector('.form-result').textContent = 'Your email app should open with the message prepared. Nothing has been sent yet.';
		window.location.href = 'mailto:hello@impactflow.com?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
	});
}());

