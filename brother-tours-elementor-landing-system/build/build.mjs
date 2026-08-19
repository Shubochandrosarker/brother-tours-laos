/**
 * Builds the Elementor template kit.
 *
 *   node build/build.mjs          write templates/ and globals/
 *   node build/build.mjs --check  verify the committed files match (CI)
 *
 * Output is deterministic: same input, byte-identical files. That is what
 * makes `--check` meaningful and what keeps a content edit showing up in a
 * diff as a content edit rather than as a thousand changed ids.
 */

import { mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { container, makeIdFactory, pageDocument, sectionDocument } from './lib/elementor.mjs';
import { SECTION_BUILDERS, verify } from './lib/sections.mjs';
import { PAGES, SECTION_ORDER } from './content/pages.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const CHECK = process.argv.includes('--check');

/* --------------------------------------------------------------- pages */

/** Sections a page has no honest content for are omitted, not emptied. */
const OPTIONAL = new Set(['destinations']);

function buildPage(page) {
	const makeId = makeIdFactory(page.slug);
	const content = [];

	for (const name of SECTION_ORDER) {
		const key = name === 'whyCards' ? 'why'
			: name === 'featuredJourneys' ? 'journeys'
			: name === 'planningGuide' ? 'planning'
			: name === 'quickAnswer' ? 'quickAnswer'
			: name === 'localInsight' ? 'insight'
			: name === 'relatedContent' ? 'related'
			: name === 'pdfResource' ? 'resourceId'
			: name;

		if (OPTIONAL.has(name) && !page[key]) continue;
		content.push(SECTION_BUILDERS[name](makeId, page));
	}

	return pageDocument({ title: page.title, content });
}

/* ------------------------------------------------------------- globals */

/**
 * The reusable sections, exported on their own so a change to the hero or the
 * invitation is made once and re-inserted, rather than edited ten times.
 *
 * They are built from the first page that uses each, then stripped of that
 * page's copy — the structure is the deliverable here, not the words.
 */
const GLOBAL_SPECS = [
	{
		file: 'bt-landing-hero',
		title: 'BT – Landing Hero',
		build(makeId) {
			return SECTION_BUILDERS.hero(makeId, {
				hero: {
					eyebrow: 'Eyebrow',
					h1: 'Page heading',
					lede: 'One-line positioning statement.',
					description: 'Two or three sentences saying what this page offers and who it is for.',
					badges: ['Lao-led', 'Private journeys', 'Licensed local guides'],
					imageAlt: 'Hero image for this page',
				},
			});
		},
	},
	{
		file: 'bt-pdf-resource-cta',
		title: 'BT – PDF Resource CTA',
		build(makeId) {
			return SECTION_BUILDERS.pdfResource(makeId, { resourceId: 'lcr-guide' });
		},
	},
	{
		file: 'bt-final-invitation',
		title: 'BT – Final Invitation',
		build(makeId) {
			return SECTION_BUILDERS.finalInvitation(makeId, { ctaContext: '' });
		},
	},
	{
		file: 'bt-faq',
		title: 'BT – FAQ Accordion',
		build(makeId) {
			return SECTION_BUILDERS.faq(makeId, {
				faq: {
					heading: 'Frequently asked',
					items: [
						{ q: 'First question', a: '<p>Answer. Keep it to two or three sentences.</p>' },
						{ q: 'Second question', a: '<p>Answer.</p>' },
						{ q: 'Third question', a: '<p>Answer.</p>' },
					],
				},
			});
		},
	},
	{
		file: 'bt-journey-cards',
		title: 'BT – Journey Cards',
		build(makeId) {
			return SECTION_BUILDERS.featuredJourneys(makeId, {
				journeys: {
					heading: 'Current journeys',
					intro: 'Live from the catalogue. Price confirmed on request.',
					taxonomy: '',
					term: '',
					count: 6,
				},
			});
		},
	},
	{
		file: 'bt-trust-strip',
		title: 'BT – Trust Strip',
		build(makeId) {
			return SECTION_BUILDERS.howWePlanIt(makeId);
		},
	},
	{
		/*
		 * The popup is not an Elementor template. Elementor's Popup builder is
		 * a Pro feature, and this site's popup is already implemented by the
		 * Brother Tours Resource Downloads plugin — one controller, ten
		 * resources, trigger rules in a filter. Exporting a second popup as a
		 * template would be the "ten separate popup codebases" the brief
		 * rules out, arriving as one file instead of ten.
		 *
		 * What ships here is a stub that says so, so nobody goes looking for
		 * a missing file.
		 */
		file: 'bt-pdf-popup',
		title: 'BT – PDF Download Popup (see plugin)',
		build(makeId) {
			return container({
				id: makeId('popup-note/wrap'),
				settings: { content_width: 'full', _css_classes: 'section tight bt-landing-note' },
				elements: [verify(makeId('popup-note'),
					'the download popup is not an Elementor template. It is implemented once in the Brother Tours Resource Downloads plugin and appears automatically on any page whose resource has a PDF attached. Trigger timing is tuned via the btrd_trigger_config filter; content comes from the btrd_resources registry. Nothing needs to be inserted into a page.')],
			});
		},
	},
];

function buildGlobal(spec) {
	const makeId = makeIdFactory(spec.file);
	return sectionDocument({ title: spec.title, content: [spec.build(makeId)] });
}

/* -------------------------------------------------------------- stylesheet */

/**
 * The landing stylesheet has one home — the theme — and is mirrored into the
 * kit so the kit is self-contained for anyone importing templates before the
 * theme is deployed. Mirrored rather than rewritten: `--check` fails if the
 * two drift, so there is never a question of which copy is right.
 */
const THEME_CSS = join(ROOT, '..', 'themes', 'brother-tours', 'assets', 'css', 'bt-landing.css');

function mirroredStylesheet() {
	const source = readFileSync(THEME_CSS, 'utf8');
	return `/* Generated by build/build.mjs from themes/brother-tours/assets/css/bt-landing.css.\n   Edit the theme copy, then re-run the build. */\n\n${source}`;
}

/* ---------------------------------------------------------------- write */

/** Trailing newline so the files behave in git and in editors. */
const serialise = (doc) => `${JSON.stringify(doc, null, '\t')}\n`;

const outputs = new Map();
for (const page of PAGES) {
	outputs.set(join('templates', `${page.slug}.json`), serialise(buildPage(page)));
}
for (const spec of GLOBAL_SPECS) {
	outputs.set(join('globals', `${spec.file}.json`), serialise(buildGlobal(spec)));
}
outputs.set(join('assets', 'bt-landing.css'), mirroredStylesheet());

if (CHECK) {
	let stale = 0;
	for (const [relative, expected] of outputs) {
		let actual = null;
		try {
			actual = readFileSync(join(ROOT, relative), 'utf8');
		} catch {
			console.error(`MISSING  ${relative}`);
			stale++;
			continue;
		}
		if (actual !== expected) {
			console.error(`STALE    ${relative}`);
			stale++;
		}
	}

	// A file nobody generates any more is as wrong as a stale one.
	for (const dir of ['templates', 'globals', 'assets']) {
		for (const name of readdirSync(join(ROOT, dir))) {
			if (/\.(json|css)$/.test(name) && !outputs.has(join(dir, name))) {
				console.error(`ORPHAN   ${join(dir, name)}`);
				stale++;
			}
		}
	}

	if (stale) {
		console.error(`\n${stale} file(s) out of date. Run: node build/build.mjs`);
		process.exit(1);
	}
	console.log(`OK  ${outputs.size} template files match the generator.`);
} else {
	for (const [relative, contents] of outputs) {
		const target = join(ROOT, relative);
		mkdirSync(dirname(target), { recursive: true });
		writeFileSync(target, contents);
	}
	console.log(`Wrote ${outputs.size} files (${PAGES.length} pages, ${GLOBAL_SPECS.length} globals, 1 stylesheet).`);
}
