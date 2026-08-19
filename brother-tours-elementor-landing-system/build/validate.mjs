/**
 * Structural validation for the generated templates.
 *
 *   node build/validate.mjs
 *
 * These files are imported into a production WordPress install by hand, and a
 * malformed one fails there rather than here — usually by silently dropping
 * the element it could not read. So everything checkable without an Elementor
 * to test against is checked here: document shape, element shape, id
 * uniqueness, widget settings, and the content rules the brief imposes.
 */

import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

let failures = 0;
let checks = 0;

function fail(file, message) {
	console.error(`FAIL  ${file}: ${message}`);
	failures++;
}

function check(condition, file, message) {
	checks++;
	if (!condition) fail(file, message);
}

/* ------------------------------------------------------------ traversal */

function walk(element, visit, depth = 0) {
	visit(element, depth);
	for (const child of element.elements ?? []) walk(child, visit, depth + 1);
}

/* ------------------------------------------------- structural invariants */

const KNOWN_NATIVE = new Set([
	'heading', 'text-editor', 'button', 'image', 'icon-list',
	'accordion', 'shortcode', 'html', 'divider', 'spacer',
]);

const KNOWN_THEME = new Set([
	'bt-tour-grid', 'bt-build-my-trip-cta', 'bt-destination-experiences',
	'bt-related-tours', 'bt-reviews', 'bt-tour-faq', 'bt-destination-hero',
]);

function validateDocument(file, doc) {
	check(doc.version === '0.4', file, `unexpected version "${doc.version}"`);
	check(typeof doc.title === 'string' && doc.title.length > 0, file, 'missing title');
	check(['page', 'section'].includes(doc.type), file, `unexpected type "${doc.type}"`);
	check(Array.isArray(doc.content) && doc.content.length > 0, file, 'empty content');

	if (doc.type === 'page') {
		// Canvas would strip the production header and footer, which is the
		// legacy architecture this rebuild exists to remove.
		check(
			doc.page_settings?.template === 'elementor_header_footer',
			file,
			`page template must be elementor_header_footer, got "${doc.page_settings?.template}"`
		);
	}

	// A widget at the document root is dropped rather than rejected on import.
	for (const [index, top] of doc.content.entries()) {
		check(top.elType === 'container', file, `content[${index}] is "${top.elType}", must be a container`);
	}

	const ids = new Set();
	for (const top of doc.content) {
		walk(top, (element) => {
			const where = `${element.elType}${element.widgetType ? `/${element.widgetType}` : ''}`;

			check(typeof element.id === 'string' && /^[0-9a-f]{7}$/.test(element.id),
				file, `${where} has a malformed id "${element.id}"`);
			check(!ids.has(element.id), file, `duplicate element id "${element.id}" (${where})`);
			ids.add(element.id);

			check(element.settings && typeof element.settings === 'object' && !Array.isArray(element.settings),
				file, `${where} (${element.id}) settings must be an object`);
			check(Array.isArray(element.elements), file, `${where} (${element.id}) missing elements array`);

			if (element.elType === 'widget') {
				check(typeof element.widgetType === 'string' && element.widgetType.length > 0,
					file, `widget ${element.id} has no widgetType`);
				check(element.elements.length === 0, file, `widget ${element.id} must have no children`);
				check(KNOWN_NATIVE.has(element.widgetType) || KNOWN_THEME.has(element.widgetType),
					file, `unknown widgetType "${element.widgetType}" (${element.id})`);
			} else if (element.elType === 'container') {
				check(element.elements.length > 0, file, `container ${element.id} is empty`);
			} else {
				fail(file, `unexpected elType "${element.elType}" (${element.id})`);
			}

			validateWidgetSettings(file, element);
		});
	}
}

function validateWidgetSettings(file, element) {
	if (element.elType !== 'widget') return;
	const s = element.settings;
	const at = `${element.widgetType} (${element.id})`;

	switch (element.widgetType) {
		case 'heading':
			check(typeof s.title === 'string' && s.title.trim() !== '', file, `${at} has empty title`);
			check(['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'].includes(s.header_size),
				file, `${at} bad header_size "${s.header_size}"`);
			break;
		case 'text-editor':
			check(typeof s.editor === 'string' && s.editor.includes('<p>'), file, `${at} editor content is not wrapped in <p>`);
			break;
		case 'button':
			check(typeof s.text === 'string' && s.text.trim() !== '', file, `${at} has no label`);
			check(typeof s.link?.url === 'string' && s.link.url.startsWith('/'), file, `${at} link must be a root-relative path, got "${s.link?.url}"`);
			break;
		case 'accordion':
			check(Array.isArray(s.tabs) && s.tabs.length >= 3, file, `${at} needs at least 3 questions`);
			for (const tab of s.tabs ?? []) {
				check(/^[0-9a-f]{7}$/.test(tab._id ?? ''), file, `${at} repeater item has a bad _id`);
				check((tab.tab_title ?? '').trim() !== '', file, `${at} has an empty question`);
				check((tab.tab_content ?? '').includes('<p>'), file, `${at} answer is not wrapped in <p>`);
			}
			// The site emits FAQPage structured data globally; a second copy
			// from the widget would be a duplicate-schema warning.
			check(!('faq_schema' in s), file, `${at} must not enable faq_schema`);
			break;
		case 'icon-list':
			check(Array.isArray(s.icon_list) && s.icon_list.length > 0, file, `${at} is empty`);
			for (const item of s.icon_list ?? []) {
				check(/^[0-9a-f]{7}$/.test(item._id ?? ''), file, `${at} repeater item has a bad _id`);
				check((item.text ?? '').trim() !== '', file, `${at} has an empty item`);
			}
			break;
		case 'image':
			// A baked media URL either 404s on production or pins a staging host.
			check(s.image?.url === '', file, `${at} must ship with an empty image url`);
			break;
		case 'shortcode':
			check(/^\[bt_resource_download id="[a-z-]+"\]$/.test(s.shortcode ?? ''),
				file, `${at} unexpected shortcode "${s.shortcode}"`);
			break;
		default:
			break;
	}
}

/* ------------------------------------------------------- content rules */

/** Prices the brief named for removal, plus any bare price-like figure. */
const FORBIDDEN = [
	{ pattern: /\$\s?\d/, why: 'a price. Pricing is "confirmed on request" — no figures in templates.' },
	{ pattern: /only \d+ (seats?|places?|spots?) left/i, why: 'artificial scarcity.' },
	{ pattern: /ABTA/i, why: 'an accreditation claim that requires documentary evidence.' },
	{ pattern: /\bpicsum\b/i, why: 'a placeholder image service.' },
	{ pattern: /https?:\/\/(?!www\.brothertours\.com)/i, why: 'an absolute external URL — links must be root-relative.' },
	{ pattern: /wa\.me\/\d/, why: 'a hardcoded WhatsApp number. It lives in Theme Options.' },
	{ pattern: /\b\d+\s?[-–]\s?\d+ guests\b/i, why: 'an unverified group capacity.' },
];

/** The one non-note raw-markup block the kit ships. */
const ALLOWED_RAW_MARKUP = /^<ol class="bt-landing-steps">/;

const isVerifyNote = (element) =>
	element.widgetType === 'html' && (element.settings.html ?? '').includes('bt-landing-verify');

/**
 * Everything a visitor can read, and nothing else.
 *
 * Scanning the raw file instead would flag the verification notes, whose whole
 * job is to name the claims that must not be published — "do not restore the
 * $7,200 pricing" would read as a price. The notes are display:none on the
 * front end, so they are excluded here and audited separately below: an html
 * widget must either carry the note class or be the one known markup block,
 * which stops anyone hiding published copy from this scan by omitting a class.
 */
function visibleText(doc, file) {
	const parts = [];

	for (const top of doc.content) {
		walk(top, (element) => {
			if (element.elType !== 'widget') return;
			const s = element.settings;

			if (element.widgetType === 'html') {
				checks++;
				if (!isVerifyNote(element) && !ALLOWED_RAW_MARKUP.test(s.html ?? '')) {
					fail(file, `html widget ${element.id} is neither a verification note nor known markup`);
				}
				if (!isVerifyNote(element)) parts.push(s.html ?? '');
				return;
			}

			if (typeof s.title === 'string') parts.push(s.title);
			if (typeof s.editor === 'string') parts.push(s.editor);
			if (typeof s.text === 'string') parts.push(s.text);
			if (typeof s.shortcode === 'string') parts.push(s.shortcode);
			if (typeof s.link?.url === 'string') parts.push(s.link.url);
			if (typeof s.image?.url === 'string') parts.push(s.image.url);
			if (typeof s.image?.alt === 'string') parts.push(s.image.alt);

			for (const item of s.icon_list ?? []) {
				parts.push(item.text ?? '');
				if (item.link?.url) parts.push(item.link.url);
			}
			for (const tab of s.tabs ?? []) {
				parts.push(tab.tab_title ?? '', tab.tab_content ?? '');
			}
		});
	}

	return parts.join('\n');
}

function validateContentRules(file, doc) {
	const published = visibleText(doc, file);
	for (const { pattern, why } of FORBIDDEN) {
		checks++;
		const match = published.match(pattern);
		if (match) fail(file, `published copy contains ${why} (matched "${match[0]}")`);
	}
}

/* -------------------------------------------------------------- per-page */

function validatePage(file, doc, raw) {
	const widgets = [];
	for (const top of doc.content) walk(top, (e) => { if (e.elType === 'widget') widgets.push(e); });

	const types = widgets.map((w) => w.widgetType);

	// The dynamic tour query is the whole point of "data must be dynamic":
	// no page may hardcode a journey list.
	check(types.includes('bt-tour-grid'), file, 'has no bt-tour-grid — journeys must come from the live catalogue');
	check(types.includes('bt-build-my-trip-cta'), file, 'has no Final Invitation CTA');
	check(types.includes('accordion'), file, 'has no FAQ');
	check(types.filter((t) => t === 'shortcode').length === 1, file, 'must have exactly one resource shortcode');

	const h1s = widgets.filter((w) => w.widgetType === 'heading' && w.settings.header_size === 'h1');
	check(h1s.length === 1, file, `has ${h1s.length} H1 headings, expected exactly 1`);

	// Every unverified specific must be flagged where an editor will see it.
	check(raw.includes('REQUIRES BUSINESS VERIFICATION'), file, 'carries no verification notes');
}

/* ------------------------------------------------------------------ run */

for (const dir of ['templates', 'globals']) {
	for (const name of readdirSync(join(ROOT, dir)).sort()) {
		if (!name.endsWith('.json')) continue;
		const relative = join(dir, name);
		const raw = readFileSync(join(ROOT, relative), 'utf8');

		let doc;
		try {
			doc = JSON.parse(raw);
		} catch (error) {
			fail(relative, `invalid JSON — ${error.message}`);
			continue;
		}

		validateDocument(relative, doc);
		validateContentRules(relative, doc);
		if (dir === 'templates') validatePage(relative, doc, raw);
	}
}

if (failures) {
	console.error(`\n${failures} failure(s) across ${checks} checks.`);
	process.exit(1);
}
console.log(`OK  ${checks} checks passed.`);
