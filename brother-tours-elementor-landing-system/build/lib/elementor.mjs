/**
 * Elementor document primitives.
 *
 * An Elementor template file is a JSON document whose `content` is a tree of
 * elements. Only three element shapes exist here: containers (flex boxes),
 * widgets (leaves), and the document wrapper itself.
 *
 * Two rules govern everything below, and both come from the brief rather than
 * from taste:
 *
 * 1. No colour, font family or font size is written into widget settings.
 *    The site flips `data-theme` on <html> and every theme token follows. A
 *    hex value baked into Elementor settings does not follow, so a page styled
 *    that way looks correct in one mode and wrong in the other. Styling is
 *    applied by attaching the theme's own classes through `_css_classes`,
 *    which is also what "do not manually recreate legacy CSS variables if the
 *    new site already provides equivalent global tokens" asks for.
 *
 * 2. Element ids are derived, not random. `elementId()` hashes a stable path,
 *    so regenerating the kit produces byte-identical files and a diff shows
 *    real content changes rather than a fresh set of ids.
 */

import { createHash } from 'node:crypto';

/** Elementor ids are short lowercase hex. Uniqueness is per document. */
export function elementId(path) {
	return createHash('sha1').update(path).digest('hex').slice(0, 7);
}

/**
 * Guards against the one failure mode that deterministic ids introduce: two
 * elements built from the same path collide silently, and Elementor renders
 * only one of them. Every builder passes its path through here.
 */
export function makeIdFactory(documentSlug) {
	const seen = new Set();
	return (path) => {
		const full = `${documentSlug}/${path}`;
		if (seen.has(full)) {
			throw new Error(`Duplicate element path "${full}" — ids would collide.`);
		}
		seen.add(full);
		return elementId(full);
	};
}

/* --------------------------------------------------------------- elements */

/**
 * A flex container. Elementor 3.16+ and 4.x build pages from these; sections
 * and columns are the legacy shape and are not used here.
 */
export function container({ id, settings = {}, elements = [], isInner = false }) {
	return { id, elType: 'container', settings, elements, isInner };
}

export function widget({ id, widgetType, settings = {} }) {
	return { id, elType: 'widget', widgetType, settings, elements: [] };
}

/* ---------------------------------------------------------------- widgets */

export function heading(id, { text, tag = 'h2', classes = '', align = '' }) {
	const settings = { title: text, header_size: tag };
	if (classes) settings._css_classes = classes;
	if (align) settings.align = align;
	return widget({ id, widgetType: 'heading', settings });
}

/** `editor` takes HTML. Paragraphs must be wrapped or Elementor renders one blob. */
export function text(id, { html, classes = '' }) {
	const settings = { editor: html };
	if (classes) settings._css_classes = classes;
	return widget({ id, widgetType: 'text-editor', settings });
}

/**
 * Buttons carry the theme's own `.btn` classes rather than Elementor's colour
 * controls, so hover, focus ring, letter-spacing and dark mode all come from
 * the stylesheet the rest of the site already uses.
 *
 * `.btn` alone is the ghost treatment; `.btn--primary` is the solid gold one.
 * Not `.btn-solid` — that is the parent theme's name for it, and the child
 * theme's own `.btn` rule loads later and overrides its background, leaving a
 * button that looks exactly like the ghost beside it.
 */
export function button(id, { label, url, classes = 'btn', external = false }) {
	return widget({
		id,
		widgetType: 'button',
		settings: {
			text: label,
			link: { url, is_external: external ? 'on' : '', nofollow: '', custom_attributes: '' },
			_css_classes: classes,
		},
	});
}

/**
 * `image.url` is deliberately empty on every template. A hardcoded media URL
 * would either 404 on production or pin a staging domain into a published
 * page; an empty url makes Elementor render its own placeholder, which reads
 * as "choose an image" to whoever opens the template.
 */
export function imagePlaceholder(id, { alt = '', classes = '', size = 'large' } = {}) {
	return widget({
		id,
		widgetType: 'image',
		settings: {
			image: { url: '', id: '', alt, source: 'library', size: '' },
			image_size: size,
			_css_classes: classes,
		},
	});
}

export function iconList(id, { items, inline = false, classes = '' }) {
	return widget({
		id,
		widgetType: 'icon-list',
		settings: {
			view: inline ? 'inline' : 'traditional',
			icon_list: items.map((item, index) => ({
				_id: elementId(`${id}/item/${index}`),
				text: typeof item === 'string' ? item : item.text,
				selected_icon: { value: '', library: '' },
				link: typeof item === 'string' || !item.url
					? { url: '', is_external: '', nofollow: '' }
					: { url: item.url, is_external: '', nofollow: '' },
			})),
			_css_classes: classes,
		},
	});
}

/**
 * The classic accordion widget. Its `tabs` repeater shape has been stable
 * across every Elementor major version, which matters more here than being
 * current: this kit is imported into a site we cannot test against first, and
 * a FAQ that renders is worth more than a FAQ that is fashionable.
 *
 * `faq_schema` is left off on purpose — the site already emits FAQPage
 * structured data globally, and a second copy is a duplicate-schema warning.
 */
export function accordion(id, { items, classes = 'bt-landing-faq' }) {
	return widget({
		id,
		widgetType: 'accordion',
		settings: {
			tabs: items.map((item, index) => ({
				_id: elementId(`${id}/faq/${index}`),
				tab_title: item.q,
				tab_content: item.a,
			})),
			title_html_tag: 'h3',
			_css_classes: classes,
		},
	});
}

export function shortcode(id, { code, classes = '' }) {
	const settings = { shortcode: code };
	if (classes) settings._css_classes = classes;
	return widget({ id, widgetType: 'shortcode', settings });
}

/**
 * Raw markup, used only where a native widget cannot express the structure —
 * the editor-only verification notes and the numbered process steps.
 */
export function html(id, { markup, classes = '' }) {
	const settings = { html: markup };
	if (classes) settings._css_classes = classes;
	return widget({ id, widgetType: 'html', settings });
}

/** A theme widget: bt-tour-grid, bt-build-my-trip-cta, bt-destination-experiences. */
export function themeWidget(id, widgetType, settings = {}) {
	return widget({ id, widgetType, settings });
}

/* -------------------------------------------------------------- documents */

/**
 * `elementor_header_footer` is Elementor Full Width: the page body is
 * Elementor's, the header and footer stay the theme's. Canvas would strip
 * both and force every page to rebuild the navigation, which is the legacy
 * architecture this rebuild exists to remove.
 */
export const FULL_WIDTH_TEMPLATE = 'elementor_header_footer';

export function pageDocument({ title, content, pageSettings = {} }) {
	return {
		version: '0.4',
		title,
		type: 'page',
		content,
		page_settings: { template: FULL_WIDTH_TEMPLATE, ...pageSettings },
		metadata: {},
	};
}

export function sectionDocument({ title, content }) {
	return {
		version: '0.4',
		title,
		type: 'section',
		content,
		page_settings: [],
		metadata: {},
	};
}
