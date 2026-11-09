/* * Experiencia de app: progreso de navegación, aparición de tarjetas
 * y tablas legibles en celular.
 * ---------------------------------------------------------------------- */
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const navProgress = document.createElement('div');
navProgress.className = 'nav-progress';
navProgress.setAttribute('aria-hidden', 'true');
document.body.append(navProgress);

const startNavProgress = () => {
	navProgress.classList.remove('is-active');
	void navProgress.offsetWidth;
	navProgress.classList.add('is-active');
};
window.addEventListener('pageshow', () => navProgress.classList.remove('is-active'));

document.addEventListener('click', (event) => {
	const link = event.target.closest?.('a[href]');
	if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
	if (link.target && link.target !== '_self') return;
	if (link.hasAttribute('download') || link.getAttribute('href').startsWith('#')) return;
	const url = new URL(link.href, window.location.href);
	if (url.origin !== window.location.origin || /\.(svg|pdf|csv|xlsx|zip)$/i.test(url.pathname) || url.pathname.includes('/export')) return;
	if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
	startNavProgress();
});

document.addEventListener('submit', (event) => {
	window.setTimeout(() => {
		const form = event.target;
		if (!event.defaultPrevented && form instanceof HTMLFormElement && !form.matches('[data-offline-form]')) startNavProgress();
	}, 0);
});

if (!prefersReducedMotion && 'IntersectionObserver' in window) {
	const revealObserver = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (!entry.isIntersecting) return;
			entry.target.classList.add('is-visible');
			revealObserver.unobserve(entry.target);
		});
	}, { rootMargin: '0px 0px -40px 0px', threshold: 0.05 });

	document.querySelectorAll('.main-content :is(.stat, .dashboard-kpi, .form-panel, .detail-panel, .history-panel, .side-panel, .table-wrap, .filter-bar, .dashboard-panel, .empty-state)').forEach((element, index) => {
		element.classList.add('ui-reveal');
		element.style.setProperty('--reveal-delay', `${Math.min(index % 6, 5) * 60}ms`);
		revealObserver.observe(element);
	});
}

document.querySelectorAll('.data-table').forEach((table) => {
	const headers = [...table.querySelectorAll('thead th')].map((header) => header.textContent.trim());
	if (!headers.length) return;
	table.querySelectorAll('tbody tr').forEach((row) => {
		[...row.children].forEach((cell, index) => {
			if (!cell.hasAttribute('data-label')) cell.setAttribute('data-label', headers[index] ?? '');
		});
	});
	table.classList.add('is-stackable');
});
