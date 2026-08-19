/**
 * Renders a template to a standalone HTML preview.
 *
 *   node build/preview.mjs bt-adventure-tours > /tmp/preview.html
 *
 * This is an approximation of Elementor's output, not a substitute for it: it
 * reproduces the container flex model and the widget markup this kit actually
 * uses, loads the real theme stylesheets, and stops there. It exists for two
 * reasons — to check the landing CSS before an import rather than after one,
 * and to let someone see a page without a WordPress to import it into.
 *
 * Where it differs from Elementor, it differs by omission. A layout that looks
 * wrong here is wrong; a layout that looks right here is probably right.
 */

import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const THEMES = join(ROOT, '..', 'themes');

const slug = process.argv[2];
const mode = process.argv.includes('--light') ? 'light' : 'dark';
const showNotes = process.argv.includes('--notes');

if (!slug) {
	console.error('usage: node build/preview.mjs <template-slug> [--light] [--notes]');
	process.exit(1);
}

const source = ['templates', 'globals']
	.map((dir) => join(ROOT, dir, `${slug}.json`))
	.find((path) => {
		try { readFileSync(path); return true; } catch { return false; }
	});

if (!source) {
	console.error(`No template "${slug}" in templates/ or globals/.`);
	process.exit(1);
}

const doc = JSON.parse(readFileSync(source, 'utf8'));

/* --------------------------------------------------------------- markup */

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

function renderWidget(el) {
	const s = el.settings;
	const cls = s._css_classes ? ` ${s._css_classes}` : '';

	switch (el.widgetType) {
		case 'heading':
			return `<${s.header_size} class="elementor-heading-title${cls}">${esc(s.title)}</${s.header_size}>`;

		case 'text-editor':
			return `<div class="elementor-widget-text-editor${cls}">${s.editor}</div>`;

		case 'button':
			return `<a class="elementor-button${cls}" href="${esc(s.link.url)}">${esc(s.text)}<span class="arr" aria-hidden="true">→</span></a>`;

		case 'image':
			// Elementor's own placeholder, drawn rather than fetched.
			return `<div class="elementor-widget-image${cls}"><div class="bt-preview-placeholder" role="img" aria-label="${esc(s.image.alt)}"><span>image slot</span><small>${esc(s.image.alt)}</small></div></div>`;

		case 'icon-list':
			return `<div class="elementor-widget-icon-list${cls}"><ul class="elementor-icon-list-items">${
				s.icon_list.map((i) => {
					const label = `<span class="elementor-icon-list-text">${esc(i.text)}</span>`;
					return `<li class="elementor-icon-list-item">${i.link?.url ? `<a href="${esc(i.link.url)}">${label}</a>` : label}</li>`;
				}).join('')
			}</ul></div>`;

		case 'accordion':
			return `<div class="elementor-accordion${cls}">${
				s.tabs.map((t, index) => `
					<div class="elementor-accordion-item">
						<div class="elementor-tab-title"><a href="#">${esc(t.tab_title)}</a></div>
						<div class="elementor-tab-content"${index === 0 ? '' : ' style="display:none"'}>${t.tab_content}</div>
					</div>`).join('')
			}</div>`;

		case 'html':
			return s.html;

		case 'shortcode':
			return `<div class="bt-preview-stub"><code>${esc(s.shortcode)}</code><span>rendered by the Resource Downloads plugin — hidden until a PDF is attached</span></div>`;

		/* Theme widgets: server-rendered, so the preview shows what they are. */
		case 'bt-tour-grid':
			return `<div class="bt-preview-stub bt-preview-stub--dynamic"><code>bt-tour-grid</code><span>live query — ${
				s.wpistic_taxonomy ? `${esc(s.wpistic_taxonomy)} = "${esc(s.wpistic_term)}"` : 'all tours'
			}, ${s.wpistic_count} results, ${s.wpistic_columns} columns</span></div>`;

		case 'bt-destination-experiences':
			return `<div class="bt-preview-stub bt-preview-stub--dynamic"><code>bt-destination-experiences</code><span>live query — destination "${esc(s.wpistic_destination)}"</span></div>`;

		case 'bt-build-my-trip-cta':
			// The theme helper's real markup, so the band renders as it will.
			return `<section class="final"><div class="wrap">
				<span class="eyebrow center">The Invitation</span>
				<h2 class="final-h2">Tell us what you're <em>imagining</em>.</h2>
				<p class="final-sub">We design your journey, your way. 24-hour reply from Vientiane — no pressure, no upselling.</p>
				<div class="final-btns">
					<a class="btn btn-solid" href="/build-my-trip/">Build My Trip <span class="arr" aria-hidden="true">→</span></a>
					<a class="btn btn-ghost on-dark" href="#">WhatsApp Brother Tours</a>
				</div>
			</div></section>`;

		default:
			return `<div class="bt-preview-stub"><code>${esc(el.widgetType)}</code></div>`;
	}
}

/**
 * Elementor's responsive settings, reproduced.
 *
 * A container setting suffixed _tablet applies below Elementor's tablet
 * breakpoint and _mobile below its mobile one. Without this the preview shows
 * every row still side by side at 390px, which is exactly the failure it is
 * supposed to catch — an honest harness has to model the breakpoints or it
 * lies about the one viewport that matters most.
 */
const BREAKPOINTS = { tablet: 1024, mobile: 767 };
const responsiveRules = { tablet: [], mobile: [] };
const baseRules = [];

function collectResponsive(el) {
	const s = el.settings;
	for (const [suffix, _px] of Object.entries(BREAKPOINTS)) {
		const decls = [];
		if (s[`flex_direction_${suffix}`]) decls.push(`flex-direction:${s[`flex_direction_${suffix}`]}`);
		if (s[`flex_align_items_${suffix}`]) decls.push(`align-items:${s[`flex_align_items_${suffix}`]}`);
		if (s[`flex_gap_${suffix}`]) decls.push(`gap:${s[`flex_gap_${suffix}`].size}px`);
		if (decls.length) responsiveRules[suffix].push(`#e-${el.id}{${decls.join(';')}}`);
	}
}

function renderElement(el) {
	if (el.elType === 'widget') return renderWidget(el);

	const s = el.settings;
	collectResponsive(el);
	const children = (el.elements ?? []).map(renderElement).join('\n');

	/*
	 * Emitted as a rule rather than a style attribute. An inline style beats
	 * every media query regardless of order, so an inline flex-direction made
	 * this preview report that the hero stays two-column at 390px when the
	 * real page stacks — the harness inventing the exact failure it exists to
	 * detect.
	 */
	const decls = [
		'display:flex',
		`flex-direction:${s.flex_direction ?? 'column'}`,
		s.flex_wrap ? `flex-wrap:${s.flex_wrap}` : '',
		s.flex_align_items ? `align-items:${s.flex_align_items}` : '',
		s.flex_gap ? `gap:${s.flex_gap.size}px` : '',
	].filter(Boolean).join(';');
	baseRules.push(`#e-${el.id}{${decls}}`);

	return `<div id="e-${el.id}" class="e-con${s._css_classes ? ` ${s._css_classes}` : ''}">\n${children}\n</div>`;
}

const body = doc.content.map(renderElement).join('\n');

const baseCss = baseRules.join('\n');

const responsiveCss = Object.entries(BREAKPOINTS)
	.map(([suffix, px]) => responsiveRules[suffix].length
		? `@media (max-width:${px}px){${responsiveRules[suffix].join('')}}`
		: '')
	.filter(Boolean)
	.join('\n');

/* ----------------------------------------------------------------- page */

const css = [
	join(THEMES, 'wpistic', 'assets', 'css', 'tokens.css'),
	join(THEMES, 'wpistic', 'style.css'),
	join(THEMES, 'wpistic', 'assets', 'css', 'components.css'),
	join(THEMES, 'wpistic', 'assets', 'css', 'pages.css'),
	join(THEMES, 'brother-tours', 'assets', 'css', 'brand-tokens.css'),
	join(THEMES, 'brother-tours', 'assets', 'css', 'bt-landing.css'),
].map((path) => readFileSync(path, 'utf8')).join('\n\n');

process.stdout.write(`<!doctype html>
<html lang="en" data-theme="${mode}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(doc.title)} — preview</title>
<style>
${css}

/* ---- preview harness only; not part of the shipped stylesheet ---- */
body { background: var(--bg); color: var(--ink); margin: 0; font-family: var(--body); font-size: var(--body-base); }
.e-con { box-sizing: border-box; }
.e-con > * { min-width: 0; }
img, .bt-preview-placeholder { max-width: 100%; }
.bt-preview-placeholder {
	display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
	aspect-ratio: 4/5; background: var(--bg-raise); border: 1px dashed var(--rule-strong);
	font-family: var(--mono); font-size: 11px; letter-spacing: .16em; text-transform: uppercase; color: var(--ink-muted);
	text-align: center; padding: 20px;
}
.bt-preview-placeholder small { letter-spacing: .04em; text-transform: none; font-size: 12px; opacity: .75; }
.bt-preview-stub {
	display: flex; flex-direction: column; gap: 6px; padding: 22px 24px;
	border: 1px dashed var(--rule-strong); background: color-mix(in srgb, var(--ink) 4%, transparent);
	font-family: var(--mono); font-size: 12px; color: var(--ink-muted);
}
.bt-preview-stub code { font-size: 13px; color: var(--gold-ink, var(--gold)); letter-spacing: .06em; }
.bt-preview-stub--dynamic { border-color: var(--gold); }
.bt-preview-banner {
	position: sticky; top: 0; z-index: 50; padding: 10px 20px; background: var(--surface-dark); color: var(--on-dark-soft);
	font-family: var(--mono); font-size: 11px; letter-spacing: .16em; text-transform: uppercase;
}
${baseCss}
${responsiveCss}
${showNotes ? '.bt-landing-verify { display: block !important; margin: 12px 0; padding: 10px 14px; border-left: 3px solid var(--gold); background: color-mix(in srgb, var(--gold) 10%, transparent); font-family: var(--mono); font-size: 12px; line-height: 1.6; color: var(--ink); }' : ''}
</style>
</head>
<body class="${showNotes ? 'bt-show-verify' : ''}">
<div class="bt-preview-banner">Preview · ${esc(doc.title)} · ${mode} mode · theme header and footer omitted</div>
${body}
</body>
</html>
`);
