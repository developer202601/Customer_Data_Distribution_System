import './bootstrap';
import 'admin-lte/dist/js/adminlte.min.js';
import 'jquery/dist/jquery.min.js';

document.addEventListener('DOMContentLoaded', () => {
	const loader = document.getElementById('page-loader');
	let loaderSuppressed = false;

	const hideLoader = () => {
		if (!loader) {
			return;
		}
		loader.classList.add('page-loader--hidden');
	};

	const showLoader = () => {
		if (!loader || loaderSuppressed) {
			return;
		}
		loader.classList.remove('page-loader--hidden');
	};

	const suppressLoaderTemporarily = () => {
		loaderSuppressed = true;
		setTimeout(() => {
			loaderSuppressed = false;
		}, 1000);
	};

	const themeToggle = document.getElementById('theme-toggle');
	const THEME_STORAGE_KEY = 'cdds-theme-mode';
	const THEMES = {
		LIGHT: 'light',
		DARK: 'dark',
	};

	const applyTheme = (theme) => {
		document.body.classList.remove('theme-light', 'theme-dark');
		document.body.classList.add(`theme-${theme}`);
		if (themeToggle) {
			themeToggle.dataset.theme = theme;
			themeToggle.setAttribute('aria-pressed', theme === THEMES.DARK ? 'true' : 'false');
		}
	};

	const loadStoredTheme = () => {
		try {
			const stored = localStorage.getItem(THEME_STORAGE_KEY);
			if (stored === THEMES.DARK || stored === THEMES.LIGHT) {
				return stored;
			}
		} catch (err) {
			return null;
		}
		return null;
	};

	const saveTheme = (theme) => {
		try {
			localStorage.setItem(THEME_STORAGE_KEY, theme);
		} catch (err) {
			// ignore
		}
	};

	const detectPreferredTheme = () => {
		if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
			return THEMES.DARK;
		}
		return THEMES.LIGHT;
	};

	const toggleTheme = () => {
		const current = document.body.classList.contains('theme-dark') ? THEMES.DARK : THEMES.LIGHT;
		const next = current === THEMES.DARK ? THEMES.LIGHT : THEMES.DARK;
		applyTheme(next);
		saveTheme(next);
	};

	const initializeTheme = () => {
		const stored = loadStoredTheme();
		const initial = stored || detectPreferredTheme();
		applyTheme(initial);
	};

	if (themeToggle) {
		themeToggle.addEventListener('click', toggleTheme);
	}

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
			suppressLoaderTemporarily();
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
			suppressLoaderTemporarily();
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

	initializeTheme();
});
