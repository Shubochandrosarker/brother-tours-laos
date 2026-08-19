/**
 * The twelve landing-page sections, as builders.
 *
 * Section order follows the brief's recommended structure. Every page uses the
 * same spine so a visitor who reads two of these pages recognises the second
 * one, and so a fix to a section is a fix to ten pages rather than one.
 *
 * Layout comes from the theme, not from Elementor's spacing controls. A
 * section wrapper carries `.section` (vertical rhythm, from --section-pad) and
 * its inner carries `.wrap` (max-width 1280, side padding, centring). Both are
 * already responsive and already mode-aware, so the alternative — a few dozen
 * responsive padding keys per section, per breakpoint — would be more JSON
 * describing worse behaviour.
 */

import {
	accordion,
	button,
	container,
	heading,
	html,
	iconList,
	imagePlaceholder,
	shortcode,
	text,
	themeWidget,
} from './elementor.mjs';

/* ----------------------------------------------------------- scaffolding */

/** Full-bleed section wrapper. `tone` picks the theme's own band treatments. */
function section(id, { classes = '', tone = '', children }) {
	const toneClass = tone === 'sand' ? ' section-sand' : tone === 'dark' ? ' section-navy' : '';
	return container({
		id,
		settings: {
			content_width: 'full',
			_css_classes: `section${toneClass} ${classes}`.trim(),
		},
		elements: children,
	});
}

/** The centred 1280px measure. */
function wrap(id, children, { classes = '', gap = 28 } = {}) {
	return container({
		id,
		settings: {
			content_width: 'full',
			flex_direction: 'column',
			flex_gap: { unit: 'px', size: gap, column: String(gap), row: String(gap), isLinked: true },
			_css_classes: `wrap ${classes}`.trim(),
		},
		elements: children,
	});
}

/** A row that becomes a column on tablet and below. */
function row(id, children, { classes = '', gap = 40, align = 'flex-start' } = {}) {
	return container({
		id,
		settings: {
			content_width: 'full',
			flex_direction: 'row',
			flex_direction_tablet: 'column',
			flex_align_items: align,
			flex_gap: { unit: 'px', size: gap, column: String(gap), row: String(gap), isLinked: true },
			_css_classes: `bt-landing-row ${classes}`.trim(),
		},
		elements: children,
	});
}

function col(id, children, { classes = '', gap = 16 } = {}) {
	return container({
		id,
		settings: {
			content_width: 'full',
			flex_direction: 'column',
			flex_gap: { unit: 'px', size: gap, column: String(gap), row: String(gap), isLinked: true },
			_css_classes: `bt-landing-col ${classes}`.trim(),
		},
		elements: children,
	});
}

/**
 * An editor-only note. `.bt-landing-verify` is display:none on the front end
 * and visible inside the Elementor editor, so an unverified claim is loud to
 * whoever owns the page and invisible to whoever reads it. This is how the
 * brief's "mark it REQUIRES BUSINESS VERIFICATION" rule is honoured without
 * publishing the marking.
 */
export function verify(id, message) {
	return html(id, {
		markup: `<p class="bt-landing-verify"><strong>REQUIRES BUSINESS VERIFICATION</strong> — ${message}</p>`,
	});
}

const eyebrow = (id, label) => heading(id, { text: label, tag: 'p', classes: 'eyebrow' });

/* ------------------------------------------------------------ 01 · hero */

export function hero(makeId, page) {
	const p = 'hero';
	return section(makeId(p), {
		classes: 'bt-landing-hero',
		children: [
			wrap(makeId(`${p}/wrap`), [
				row(makeId(`${p}/row`), [
					col(makeId(`${p}/copy`), [
						eyebrow(makeId(`${p}/eyebrow`), page.hero.eyebrow),
						heading(makeId(`${p}/h1`), { text: page.hero.h1, tag: 'h1', classes: 'hero-v2-h1' }),
						text(makeId(`${p}/lede`), { html: `<p>${page.hero.lede}</p>`, classes: 'hero-v2-lede' }),
						text(makeId(`${p}/sub`), { html: `<p>${page.hero.description}</p>`, classes: 'hero-v2-sub' }),
						...(page.hero.badges?.length
							? [iconList(makeId(`${p}/badges`), {
								items: page.hero.badges,
								inline: true,
								classes: 'bt-landing-badges',
							})]
							: []),
						row(makeId(`${p}/ctas`), [
							/*
							 * btn--primary, not btn-solid. The child theme
							 * redefines .btn as ghost-gold in brand-tokens.css,
							 * which loads after the parent's .btn-solid and
							 * overrides its background — so a .btn-solid button
							 * renders identical to its ghost neighbour and the
							 * hero loses its primary action entirely. Caught by
							 * rendering the template rather than reading it.
							 *
							 * The ghost treatment is .btn's default, so the
							 * secondary needs no modifier at all.
							 */
							button(makeId(`${p}/cta-primary`), {
								label: 'Build My Trip',
								url: '/build-my-trip/',
								classes: 'btn btn--primary',
							}),
							button(makeId(`${p}/cta-secondary`), {
								label: 'Message Brother Tours',
								url: '/contact/',
								classes: 'btn',
							}),
						], { classes: 'bt-landing-ctas', gap: 14, align: 'center' }),
						/*
						 * The WhatsApp number lives in Theme Options
						 * (wpistic_contact('whatsapp')), not in this repository,
						 * so it is not written into a template. The Final
						 * Invitation section renders the real one automatically.
						 */
						verify(makeId(`${p}/cta-note`),
							'the secondary hero CTA points at /contact/. Swap the href to the WhatsApp link if this page should open WhatsApp directly — the Final Invitation section at the foot of the page already uses the configured number.'),
					], { classes: 'bt-landing-hero__copy' }),
					col(makeId(`${p}/media`), [
						imagePlaceholder(makeId(`${p}/image`), {
							alt: page.hero.imageAlt,
							classes: 'bt-landing-hero__image',
						}),
						verify(makeId(`${p}/image-note`),
							`choose the hero image from the media library. Suggested subject: ${page.hero.imageAlt}. Do not reuse legacy stock or Picsum placeholders.`),
					], { classes: 'bt-landing-hero__media' }),
				], { gap: 56 }),
			], { gap: 0 }),
		],
	});
}

/* ---------------------------------------------------- 02 · quick answer */

/**
 * The block an AI answer engine or a featured snippet can lift whole. Kept
 * short and literal on purpose: it answers "what is this and who is it for"
 * in the first two sentences, before any persuasion starts.
 */
export function quickAnswer(makeId, page) {
	const p = 'quick-answer';
	return section(makeId(p), {
		tone: 'sand',
		classes: 'bt-landing-quick tight',
		children: [
			wrap(makeId(`${p}/wrap`), [
				eyebrow(makeId(`${p}/eyebrow`), 'In short'),
				heading(makeId(`${p}/h2`), { text: page.quickAnswer.question, tag: 'h2' }),
				text(makeId(`${p}/body`), {
					html: page.quickAnswer.paragraphs.map((s) => `<p>${s}</p>`).join('\n'),
					classes: 'bt-landing-quick__body',
				}),
			], { gap: 18 }),
		],
	});
}

/* ------------------------------------------------------------- 03 · why */

export function whyCards(makeId, page) {
	const p = 'why';
	return section(makeId(p), {
		classes: 'bt-landing-why',
		children: [
			wrap(makeId(`${p}/wrap`), [
				eyebrow(makeId(`${p}/eyebrow`), page.why.eyebrow),
				heading(makeId(`${p}/h2`), { text: page.why.heading, tag: 'h2' }),
				...(page.why.intro ? [text(makeId(`${p}/intro`), { html: `<p>${page.why.intro}</p>` })] : []),
				container({
					id: makeId(`${p}/grid`),
					settings: {
						content_width: 'full',
						flex_direction: 'row',
						flex_wrap: 'wrap',
						flex_gap: { unit: 'px', size: 24, column: '24', row: '24', isLinked: true },
						_css_classes: 'bt-landing-cards',
					},
					elements: page.why.cards.map((card, index) =>
						col(makeId(`${p}/card/${index}`), [
							heading(makeId(`${p}/card/${index}/h3`), {
								text: card.title,
								tag: 'h3',
								classes: 'bt-landing-card__title',
							}),
							text(makeId(`${p}/card/${index}/body`), {
								html: `<p>${card.body}</p>`,
								classes: 'bt-landing-card__body',
							}),
						], { classes: 'bt-landing-card', gap: 10 })
					),
				}),
			]),
		],
	});
}

/* ------------------------------------------- 04 · featured journeys (live) */

/**
 * The theme's own tour query widget. Nothing about the tour list is written
 * into this template: no titles, no durations, no prices. If a journey is
 * retired in WordPress it leaves this page the same day, which is the whole
 * point of the brief's "data must be dynamic" rule.
 *
 * The widget renders its own empty state when a filter matches nothing, so a
 * term slug that does not exist degrades to a visible message rather than a
 * broken grid.
 */
export function featuredJourneys(makeId, page) {
	const p = 'journeys';
	const query = page.journeys;
	return section(makeId(p), {
		tone: 'sand',
		classes: 'bt-landing-journeys',
		children: [
			wrap(makeId(`${p}/wrap`), [
				eyebrow(makeId(`${p}/eyebrow`), 'Current journeys'),
				heading(makeId(`${p}/h2`), { text: query.heading, tag: 'h2' }),
				...(query.intro ? [text(makeId(`${p}/intro`), { html: `<p>${query.intro}</p>` })] : []),
				themeWidget(makeId(`${p}/grid`), 'bt-tour-grid', {
					wpistic_taxonomy: query.taxonomy ?? '',
					wpistic_term: query.term ?? '',
					wpistic_count: query.count ?? 6,
					wpistic_columns: '3',
					wpistic_columns_tablet: '2',
					wpistic_columns_mobile: '1',
				}),
				...(query.term
					? [verify(makeId(`${p}/term-note`),
						`this grid filters ${query.taxonomy} = "${query.term}". Confirm that term slug exists in WordPress — if it does not, the grid renders "No tours match this filter yet" instead of journeys.`)]
					: []),
				button(makeId(`${p}/all`), {
					label: 'See all journeys',
					url: '/tours/',
					classes: 'btn',
				}),
			]),
		],
	});
}

/* --------------------------------------------- 05 · destinations (optional) */

export function destinations(makeId, page) {
	const p = 'destinations';
	const spec = page.destinations;
	return section(makeId(p), {
		classes: 'bt-landing-destinations',
		children: [
			wrap(makeId(`${p}/wrap`), [
				eyebrow(makeId(`${p}/eyebrow`), 'Where this goes'),
				heading(makeId(`${p}/h2`), { text: spec.heading, tag: 'h2' }),
				...(spec.intro ? [text(makeId(`${p}/intro`), { html: `<p>${spec.intro}</p>` })] : []),
				...(spec.destinationSlug
					? [
						themeWidget(makeId(`${p}/experiences`), 'bt-destination-experiences', {
							wpistic_destination: spec.destinationSlug,
							wpistic_count: spec.count ?? 6,
						}),
						verify(makeId(`${p}/slug-note`),
							`this pulls experiences for destination "${spec.destinationSlug}". Confirm the destination exists, or clear the field to show all.`),
					]
					: []),
				...(spec.places?.length
					? [iconList(makeId(`${p}/places`), { items: spec.places, classes: 'bt-landing-places' })]
					: []),
			]),
		],
	});
}

/* --------------------------------------------------- 06 · planning guide */

export function planningGuide(makeId, page) {
	const p = 'planning';
	const spec = page.planning;
	return section(makeId(p), {
		tone: 'sand',
		classes: 'bt-landing-planning',
		children: [
			wrap(makeId(`${p}/wrap`), [
				eyebrow(makeId(`${p}/eyebrow`), 'Planning guide'),
				heading(makeId(`${p}/h2`), { text: spec.heading, tag: 'h2' }),
				container({
					id: makeId(`${p}/grid`),
					settings: {
						content_width: 'full',
						flex_direction: 'row',
						flex_wrap: 'wrap',
						flex_gap: { unit: 'px', size: 32, column: '32', row: '32', isLinked: true },
						_css_classes: 'bt-landing-planning__grid',
					},
					elements: spec.items.map((item, index) =>
						col(makeId(`${p}/item/${index}`), [
							heading(makeId(`${p}/item/${index}/h3`), {
								text: item.label,
								tag: 'h3',
								classes: 'bt-landing-planning__label',
							}),
							text(makeId(`${p}/item/${index}/body`), { html: `<p>${item.body}</p>` }),
						], { classes: 'bt-landing-planning__item', gap: 8 })
					),
				}),
				...(spec.note ? [verify(makeId(`${p}/note`), spec.note)] : []),
			]),
		],
	});
}

/* ------------------------------------------------------ 07 · pdf resource */

/**
 * One shortcode, resolved by the resource-downloads plugin. The plugin holds
 * the guide's title, benefits, cover and file, so this section says nothing
 * that could drift out of step with the guide itself — and renders nothing at
 * all until a PDF is actually attached, rather than offering a download that
 * does not exist.
 */
export function pdfResource(makeId, page) {
	const p = 'resource';
	return section(makeId(p), {
		classes: 'bt-landing-resource',
		children: [
			wrap(makeId(`${p}/wrap`), [
				shortcode(makeId(`${p}/shortcode`), {
					code: `[bt_resource_download id="${page.resourceId}"]`,
				}),
				verify(makeId(`${p}/note`),
					`this renders the "${page.resourceId}" guide. It stays invisible to visitors until a PDF is attached via the btrd_resources filter — see the Brother Tours Resource Downloads plugin README.`),
			], { gap: 0 }),
		],
	});
}

/* ------------------------------------------------------- 08 · local insight */

export function localInsight(makeId, page) {
	const p = 'insight';
	const spec = page.insight;
	return section(makeId(p), {
		tone: 'dark',
		classes: 'bt-landing-insight',
		children: [
			wrap(makeId(`${p}/wrap`), [
				eyebrow(makeId(`${p}/eyebrow`), spec.eyebrow ?? "Ken's note"),
				text(makeId(`${p}/body`), {
					html: spec.paragraphs.map((s) => `<p>${s}</p>`).join('\n'),
					classes: 'bt-landing-insight__body',
				}),
				text(makeId(`${p}/attribution`), {
					html: '<p>Ken FJ Her — founder, Brother Tours. Licensed Lao National Tour Guide since 2010; founded Brother Tours in 2018.</p>',
					classes: 'bt-landing-insight__attribution',
				}),
			], { gap: 20 }),
		],
	});
}

/* ---------------------------------------------------- 09 · how we plan it */

/**
 * The same five steps on every page, because it is the same process on every
 * page. Marked-up as an ordered list so it reads correctly to a screen reader
 * and to a crawler, rather than as five styled divs.
 */
const PROCESS_STEPS = [
	['Tell us about your trip', 'Dates, pace, who is travelling, what you want the days to feel like.'],
	['Brother Tours designs the journey', 'A route built around your answers, not assembled from a package shelf.'],
	['Review and refine', 'Change anything. Most journeys go through two or three rounds before they settle.'],
	['Human confirmation', 'Every element is confirmed with the people who will actually deliver it.'],
	['Travel Laos', 'Your guide, your driver, your itinerary — and someone reachable in Vientiane throughout.'],
];

export function howWePlanIt(makeId) {
	const p = 'process';
	const steps = PROCESS_STEPS.map(
		([title, body], index) =>
			`<li class="bt-landing-step"><span class="bt-landing-step__n">${String(index + 1).padStart(2, '0')}</span><span class="bt-landing-step__title">${title}</span><span class="bt-landing-step__body">${body}</span></li>`
	).join('');

	return section(makeId(p), {
		classes: 'bt-landing-process',
		children: [
			wrap(makeId(`${p}/wrap`), [
				eyebrow(makeId(`${p}/eyebrow`), 'How Brother Tours plans it'),
				heading(makeId(`${p}/h2`), { text: 'Five steps, one conversation', tag: 'h2' }),
				html(makeId(`${p}/steps`), {
					markup: `<ol class="bt-landing-steps">${steps}</ol>`,
				}),
			]),
		],
	});
}

/* ------------------------------------------------------------- 10 · faq */

export function faq(makeId, page) {
	const p = 'faq';
	return section(makeId(p), {
		tone: 'sand',
		classes: 'bt-landing-faq-section',
		children: [
			wrap(makeId(`${p}/wrap`), [
				eyebrow(makeId(`${p}/eyebrow`), 'Questions'),
				heading(makeId(`${p}/h2`), { text: page.faq.heading ?? 'Frequently asked', tag: 'h2' }),
				accordion(makeId(`${p}/accordion`), { items: page.faq.items }),
				...(page.faq.note ? [verify(makeId(`${p}/note`), page.faq.note)] : []),
			]),
		],
	});
}

/* -------------------------------------------------- 11 · related content */

export function relatedContent(makeId, page) {
	const p = 'related';
	return section(makeId(p), {
		classes: 'bt-landing-related tight',
		children: [
			wrap(makeId(`${p}/wrap`), [
				eyebrow(makeId(`${p}/eyebrow`), 'Keep reading'),
				iconList(makeId(`${p}/links`), {
					items: page.related,
					classes: 'bt-landing-related__links',
				}),
			], { gap: 16 }),
		],
	});
}

/* ------------------------------------------------- 12 · final invitation */

/**
 * The theme widget, not a rebuilt CTA. It renders the locked invitation copy
 * and both buttons, and it reads the WhatsApp number from Theme Options — so
 * changing that number changes ten landing pages without touching one of them.
 */
export function finalInvitation(makeId, page) {
	/*
	 * Wrapped rather than returned bare: a document's top-level children must
	 * be containers, and a widget sitting at the root is the kind of thing an
	 * importer drops silently rather than rejects loudly.
	 *
	 * The widget renders its own full-bleed `.final` band, so the wrapper adds
	 * no padding of its own — no `.section` class here.
	 */
	return container({
		id: makeId('final-invitation/wrap'),
		settings: { content_width: 'full', _css_classes: 'bt-landing-invitation' },
		elements: [
			themeWidget(makeId('final-invitation'), 'bt-build-my-trip-cta', {
				wpistic_context: page.ctaContext ?? '',
			}),
		],
	});
}

export const SECTION_BUILDERS = {
	hero,
	quickAnswer,
	whyCards,
	featuredJourneys,
	destinations,
	planningGuide,
	pdfResource,
	localInsight,
	howWePlanIt,
	faq,
	relatedContent,
	finalInvitation,
};
