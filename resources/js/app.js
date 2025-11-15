import './bootstrap';
import 'admin-lte/dist/js/adminlte.min.js';
import 'jquery/dist/jquery.min.js';

document.addEventListener('DOMContentLoaded', () => {
	const loader = document.getElementById('page-loader');
	if (!loader) {
		return;
	}

	const hideLoader = () => loader.classList.add('page-loader--hidden');
	const showLoader = () => loader.classList.remove('page-loader--hidden');

	window.addEventListener('load', () => {
		// allow small delay so CSS/JS settle before hiding
		setTimeout(hideLoader, 200);
	});

	window.addEventListener('beforeunload', () => {
		showLoader();
	});

	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement)) {
			return;
		}
		if (form.matches('[data-loader-off]')) {
			return;
		}
		showLoader();
	}, true);

	document.addEventListener('click', (event) => {
		const link = event.target.closest('a[href]');
		if (!link) {
			return;
		}
		if (event.defaultPrevented) {
			return;
		}
		if (link.hasAttribute('data-loader-off')) {
			return;
		}
		if (link.getAttribute('target') === '_blank' || link.hasAttribute('download')) {
			return;
		}
		const href = link.getAttribute('href');
		if (!href || href.startsWith('#')) {
			return;
		}
		showLoader();
	}, true);
});
