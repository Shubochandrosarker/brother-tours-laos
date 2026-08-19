/**
 * The ten landing pages.
 *
 * What is written here and what is not
 * ------------------------------------
 * Three kinds of statement appear below, and only three.
 *
 *   1. Facts about Brother Tours confirmed by the current site and the client
 *      brief: Ken founded the company in 2018 and has been a licensed Lao
 *      National Tour Guide since 2010; journeys are private and Lao-led;
 *      pricing is confirmed on request; honeymoon journeys typically run 7-12
 *      nights across 2-3 destinations; Kong Lor is an approximately 7.5 km
 *      underground river passage.
 *
 *   2. General geography and seasonality of Laos, which belongs to the country
 *      rather than to the company: where places are, when the dry and green
 *      seasons fall, what the Mekong and the Nam Ou do, that Luang Prabang and
 *      the Plain of Jars are UNESCO-listed, that the Lao-China Railway opened
 *      in December 2021.
 *
 *   3. Structure with the specifics deliberately absent, carrying a `verify`
 *      note that shows only inside the Elementor editor.
 *
 * Nothing else. No price, no departure date, no group size, no cancellation
 * term, no hotel or airline partner, no accreditation, no review, no seat
 * count. The legacy pages were full of those and every one of them would have
 * to be defended. Where a page needs such a fact to be complete, it has a
 * verification note instead, and the page reads perfectly well without it.
 *
 * The legacy prices the brief explicitly listed for removal -- $299, $799,
 * $1,299, $1,899, $1,699-$4,499, $7,200, $9,500, $15,000 -- appear nowhere in
 * this kit.
 */

/** Canonical routes, read from themes/brother-tours/functions.php. */
const R = {
	tours: '/tours/',
	destinations: '/destinations/',
	about: '/about/',
	contact: '/contact/',
	build: '/build-my-trip/',
	journal: '/journal/',
	faq: '/faq/',
	visa: '/visa-guide/',
	whenToVisit: '/when-to-visit/',
};

/* ========================================================================= */

export const PAGES = [
	/* --------------------------------------------------- A. Adventure Tours */
	{
		slug: 'bt-adventure-tours',
		title: 'BT – Adventure Tours Landing',
		resourceId: 'adventure-planner',
		ctaContext: 'Adventure Tours',
		hero: {
			eyebrow: 'Adventure travel',
			h1: 'Laos on foot, on water, and underground',
			lede: 'Trekking, rivers, caves and limestone — at a pace that leaves room for the place.',
			description:
				'Brother Tours builds private adventure journeys across Laos: forest treks out of the northern hills, kayaking and slow river days, and the long dark passage through Kong Lor. Designed around your fitness and your appetite, not a fixed departure list.',
			badges: ['Lao-led', 'Private journeys', 'Licensed local guides'],
			imageAlt: 'A trekking route through northern Laos limestone country',
		},
		quickAnswer: {
			question: 'What is a Brother Tours adventure journey, and who is it for?',
			paragraphs: [
				'An adventure journey is a private, guided trip through the active side of Laos — walking, paddling, cycling or caving — built for one group at a time rather than sold as a scheduled departure. A licensed Lao guide travels with you throughout.',
				'It suits travellers who are comfortable on their feet for a few hours a day and would rather spend an afternoon on a river than in a queue. Families travel this way often; so do people in their sixties. Pace is a setting, not a fixed grade.',
			],
		},
		why: {
			eyebrow: 'Why here',
			heading: 'What Laos gives an adventure traveller',
			intro:
				'Laos is mountainous, forested and threaded with rivers, and it is quiet in a way its neighbours have stopped being. That quiet is the reason to come.',
			cards: [
				{ title: 'Limestone and caves', body: 'The karst country around Vang Vieng and Thakhek is riddled with caves and rivers. Kong Lor runs roughly 7.5 km straight through a mountain, by boat, in the dark.' },
				{ title: 'Rivers you can travel on', body: 'The Mekong and the Nam Ou are working roads as much as scenery. A river day is transport, landscape and village life at once.' },
				{ title: 'Northern trekking', body: 'Forest and ridge walking out of Luang Prabang and Nong Khiaw, with village stays where they are genuinely welcome rather than staged.' },
				{ title: 'The Bolaven Plateau', body: 'Cooler, higher, and green: coffee country, waterfalls and back roads in the far south.' },
				{ title: 'Room to move', body: 'Trails and rivers here are rarely crowded. Most days you will meet more water buffalo than other travellers.' },
				{ title: 'One group at a time', body: 'Your itinerary is yours. Nobody is added to it, and it changes when you want it to change.' },
			],
		},
		journeys: {
			heading: 'Adventure journeys currently offered',
			intro: 'Live from the Brother Tours catalogue. Price confirmed on request.',
			taxonomy: 'tour_category',
			term: 'adventure',
			count: 6,
		},
		destinations: {
			heading: 'Where adventure journeys go',
			intro: 'Most itineraries combine two or three of these rather than crossing the whole country.',
			places: [
				{ text: 'Vang Vieng — caves, lagoons and the Nam Song', url: R.destinations },
				{ text: 'Kong Lor and Thakhek — the underground river and the loop', url: R.destinations },
				{ text: 'Nong Khiaw and the Nam Ou — northern trekking and river days', url: R.destinations },
				{ text: 'Luang Prabang — the usual base for northern routes', url: R.destinations },
				{ text: 'The Bolaven Plateau — southern highlands and waterfalls', url: R.destinations },
				{ text: 'Si Phan Don (4,000 Islands) — the slow southern Mekong', url: R.destinations },
			],
		},
		planning: {
			heading: 'Planning an adventure trip to Laos',
			items: [
				{ label: 'How long', body: 'Eight to fourteen days is the comfortable range for an adventure route. Under a week works if you stay in one region rather than crossing the country.' },
				{ label: 'When to go', body: 'November to February is dry and cool — the easiest walking weather. March to May is hot. The green season, roughly June to October, brings full rivers and dramatic light, with some trails harder going and some roads slower.' },
				{ label: 'Pace', body: 'Active days are usually three to six hours of walking or paddling, with the rest of the day unstructured. Consecutive hard days are a choice, not a default.' },
				{ label: 'Getting around', body: 'Private vehicle between regions, boats where the river is the better road, and the Lao-China Railway on the Vientiane–Luang Prabang corridor where it saves a long drive.' },
				{ label: 'Who it suits', body: 'Anyone in ordinary health who walks regularly. Tell us honestly what you are happy with and the route is built to it — including where a rest day should fall.' },
				{ label: 'What to bring', body: 'Broken-in walking shoes, a dry bag for river days, and long sleeves for the cave. Everything technical is provided.' },
			],
			note: 'confirm which activities Brother Tours currently operates directly versus coordinates with local partners, and whether any require a minimum group size.',
		},
		insight: {
			paragraphs: [
				'The mistake most people make with an adventure trip here is booking too much of it. Laos rewards the day you did not plan — the village you walk into at the wrong hour, the boatman who suggests a different landing.',
				'So we build in slack deliberately. If a trek is going well we keep going; if the river is high we change the plan that morning. That only works when the guide is local, licensed and knows who to call.',
			],
		},
		faq: {
			heading: 'Adventure travel in Laos — common questions',
			items: [
				{ q: 'How fit do I need to be?', a: '<p>For most routes, comfortable walking for three to five hours with breaks. Tell us what a normal week of exercise looks like for you and the itinerary is set to that. Harder options exist; none are compulsory.</p>' },
				{ q: 'Can we travel with children?', a: '<p>Yes, and families do this often. Distances shorten, river and cave days usually stay, and the pace changes more than the destinations do.</p>' },
				{ q: 'Is the green season worth avoiding?', a: '<p>No — it is worth understanding. Roughly June to October the landscape is at its best and rivers are full. Some trails get slippery and some roads slow down. We route around the worst of it rather than cancelling the season.</p>' },
				{ q: 'What is Kong Lor actually like?', a: '<p>A boat journey of roughly 7.5 km through a mountain, in the dark, on an underground river. Cool, loud, and genuinely unlike anywhere else in Southeast Asia.</p>' },
				{ q: 'Do we share the trip with strangers?', a: '<p>No. Journeys are private to your group.</p>' },
				{ q: 'How is pricing handled?', a: '<p>Price is confirmed on request, once the route is settled. There is no package rate because there is no package.</p>' },
			],
		},
		related: [
			{ text: 'All Brother Tours journeys', url: R.tours },
			{ text: 'Destinations across Laos', url: R.destinations },
			{ text: 'When to visit Laos', url: R.whenToVisit },
			{ text: 'Central Laos — Kong Lor and the karst', url: '/central-laos/' },
			{ text: 'Laos Signature Tours', url: '/laos-signature-tours/' },
		],
	},

	/* ------------------------------------------------------- B. Central Laos */
	{
		slug: 'bt-central-laos',
		title: 'BT – Central Laos Landing',
		resourceId: 'central-laos-guide',
		ctaContext: 'Central Laos',
		hero: {
			eyebrow: 'Destination',
			h1: 'Central Laos',
			lede: 'The capital, the karst, and a river that runs through a mountain.',
			description:
				'Vientiane, Vang Vieng, Thakhek and Kong Lor sit within a few hours of one another along the country\'s middle. It is the most accessible part of Laos and, oddly, the least travelled.',
			badges: ['Lao-led', 'Private journeys', 'Rail-accessible'],
			imageAlt: 'Limestone karst landscape in central Laos',
		},
		quickAnswer: {
			question: 'What is Central Laos, and why go there?',
			paragraphs: [
				'Central Laos is the belt of the country running from Vientiane through Vang Vieng down to Thakhek and Kong Lor. It holds the capital, the most dramatic limestone landscape in the country, and the Kong Lor cave — an underground river passage of roughly 7.5 km that you travel by boat.',
				'It suits travellers with a week or so who want landscape and quiet without long transfers, and anyone combining a first trip to Laos with the Lao-China Railway, which runs through the region.',
			],
		},
		why: {
			eyebrow: 'Why Central Laos',
			heading: 'Four reasons this is the part people skip',
			intro: 'Most first itineraries run Luang Prabang and then south. The middle of the country is the part worth slowing down for.',
			cards: [
				{ title: 'Kong Lor Cave', body: 'A boat journey of roughly 7.5 km on an underground river, straight through a limestone mountain. There is nothing else like it in the region.' },
				{ title: 'Vang Vieng, changed', body: 'The party era is over. What is left is the landscape that was always the point: karst towers, lagoons, caves and the Nam Song.' },
				{ title: 'Vientiane at its own pace', body: 'A capital that still closes for lunch. Temples, the Mekong waterfront, French-era streets and genuinely good food.' },
				{ title: 'The Thakhek loop', body: 'Limestone, caves and back roads through some of the emptiest country in Laos, drivable in three or four days.' },
			],
		},
		journeys: {
			heading: 'Journeys through Central Laos',
			intro: 'Live from the catalogue. Price confirmed on request.',
			taxonomy: 'tour_destination',
			term: 'central-laos',
			count: 6,
		},
		destinations: {
			heading: 'The places',
			places: [
				{ text: 'Vientiane — the capital and the Mekong waterfront', url: R.destinations },
				{ text: 'Vang Vieng — karst, caves, lagoons and the Nam Song', url: R.destinations },
				{ text: 'Kong Lor — the 7.5 km underground river', url: R.destinations },
				{ text: 'Thakhek — the loop, and the way south', url: R.destinations },
			],
		},
		planning: {
			heading: 'Planning a Central Laos trip',
			items: [
				{ label: 'How long', body: 'Five to eight days covers the region properly. Four is enough for Vientiane and Vang Vieng alone; Kong Lor needs its own overnight.' },
				{ label: 'When to go', body: 'November to February is dry and cool. Kong Lor is best when water levels allow the full passage — that varies through the year, and we check it before confirming the day.' },
				{ label: 'Getting there', body: 'The Lao-China Railway serves the Vientiane–Vang Vieng–Luang Prabang corridor and turns what were half-day drives into short hops. Kong Lor and Thakhek are reached by road.' },
				{ label: 'Pace', body: 'Short transfers make this an easy region to travel slowly. Two nights per stop is usually better than three stops in four days.' },
				{ label: 'Combining it', body: 'Central Laos pairs naturally with Luang Prabang to the north, or continues south towards the Bolaven Plateau and Si Phan Don.' },
				{ label: 'Suitability', body: 'Undemanding. Kong Lor involves boat travel and some walking on wet rock; everything else is gentle.' },
			],
			note: 'confirm current Kong Lor access conditions and any seasonal closure, and confirm which railway segments Brother Tours currently books on a client\'s behalf.',
		},
		insight: {
			paragraphs: [
				'People give Vang Vieng one night because of what they read about it a decade ago. That is the wrong length. Give it two and go up a mountain in the morning, and it becomes the best-looking place in the country.',
				'Kong Lor deserves its own overnight rather than a rushed day trip. Arriving the evening before means going through the cave early, when the river is quiet and the boatmen are not working around a schedule.',
			],
		},
		faq: {
			heading: 'Central Laos — common questions',
			items: [
				{ q: 'Is Vang Vieng still a party town?', a: '<p>No. That period ended years ago. It is now a landscape destination — caving, kayaking, climbing, ballooning and viewpoints.</p>' },
				{ q: 'How long does Kong Lor take?', a: '<p>The passage is roughly 7.5 km each way by boat and takes the better part of a morning with stops. Getting there is the longer part of the day, which is why we build an overnight around it.</p>' },
				{ q: 'Can we use the railway?', a: '<p>The Lao-China Railway, open since December 2021, runs through this corridor and makes Vientiane–Vang Vieng–Luang Prabang short. We advise on whether it beats driving for your particular route.</p>' },
				{ q: 'Is Central Laos good for a first trip to Laos?', a: '<p>Yes — short transfers, a capital city, the best karst in the country and a genuinely unusual cave, all within a few hours of each other.</p>' },
				{ q: 'What does it cost?', a: '<p>Price is confirmed on request once the route and standard of accommodation are settled.</p>' },
			],
		},
		related: [
			{ text: 'Adventure journeys in Laos', url: '/adventure-tours/' },
			{ text: 'All destinations', url: R.destinations },
			{ text: 'When to visit Laos', url: R.whenToVisit },
			{ text: 'Lao-China Railway e-ticket guide', url: '/lcr-e-ticket-guide/' },
			{ text: 'Laos Signature Tours', url: '/laos-signature-tours/' },
		],
	},

	/* --------------------------------------- C. Founder-Hosted Signature */
	{
		slug: 'bt-founder-hosted',
		title: 'BT – Founder Hosted Journeys Landing',
		resourceId: 'founder-hosted-guide',
		ctaContext: 'Founder-Hosted Journey',
		hero: {
			eyebrow: 'Hosted by Ken',
			h1: 'Founder-hosted signature journeys',
			lede: 'A small number of journeys each year, travelled with the person who built the company.',
			description:
				'Ken FJ Her founded Brother Tours in 2018 and has been a licensed Lao National Tour Guide since 2010. On a founder-hosted journey he travels with you — not as an escort, but as the person who knows the households, the abbots and the boatmen by name.',
			badges: ['Hosted by the founder', 'Lao-born', 'Licensed since 2010'],
			imageAlt: 'Ken FJ Her with travellers in a Lao village',
		},
		quickAnswer: {
			question: 'What is a founder-hosted journey, and how is it different?',
			paragraphs: [
				'A founder-hosted journey is a private Brother Tours itinerary on which Ken FJ Her travels with you personally. The difference is access and interpretation: relationships built over a decade of guiding, and the context that turns a temple visit into an explanation.',
				'It is not a more expensive version of the same trip. It is the trip where the person answering your questions is the person who chose every element of it.',
			],
		},
		why: {
			eyebrow: 'The difference',
			heading: 'What "hosted" actually means here',
			intro: 'Not luxury by price. Luxury by who is standing next to you.',
			cards: [
				{ title: 'Lao-born knowledge', body: 'Ken grew up in this country and has guided it professionally since 2010. The reading of a place is first-hand, not researched.' },
				{ title: 'Relationships, not bookings', body: 'Doors open because of who is asking. Households, monasteries and boatmen respond to a familiar face differently than to an agency.' },
				{ title: 'Context as you go', body: 'What you are looking at, why it is there, and what it means to the people who use it — explained as it happens rather than read off a board.' },
				{ title: 'Flexibility in the moment', body: 'A host who owns the company can change the day at breakfast. There is nobody to ask.' },
			],
		},
		journeys: {
			heading: 'Founder-hosted journeys',
			intro: 'Live from the catalogue. If only one founder-hosted journey is currently offered, one is what shows here — the list is never padded.',
			taxonomy: 'tour_category',
			term: 'founder-hosted',
			count: 3,
		},
		planning: {
			heading: 'Planning a founder-hosted journey',
			items: [
				{ label: 'How long', body: 'These journeys tend to run longer than a standard itinerary, because their value is in unhurried days rather than covered ground.' },
				{ label: 'When', body: 'Hosting depends on Ken\'s availability as well as the season. The earlier the conversation starts, the more the dates can be shaped around you.' },
				{ label: 'Who it suits', body: 'Travellers who have been somewhere before and want to understand the next place rather than tick it. Often returning guests; often small family groups.' },
				{ label: 'Pace', body: 'Slow. Two or three bases, long days in each, and the freedom to stay when somewhere is working.' },
				{ label: 'How to start', body: 'Tell us the shape of the trip you are imagining. Availability is confirmed by a person, not a calendar widget.' },
			],
			note: 'confirm how many founder-hosted journeys run per year, the guest capacity per journey, whether specific dates are published, and the deposit and cancellation terms. The legacy page claimed "12 departures per host per year", "6-8 guests" and fixed cancellation percentages — none of those are reproduced here and none should be published until confirmed in the current business system.',
		},
		insight: {
			eyebrow: 'From Ken',
			paragraphs: [
				'I started Brother Tours in 2018 after eight years of guiding other people\'s itineraries. The reason was simple: the best days were always the ones nobody had planned, and I could not build those into someone else\'s schedule.',
				'When I host a journey I am not adding a layer of service. I am removing the layer between you and the country — the second-hand explanation, the fixed route, the guide who is following a brief. You get the version I would travel myself.',
			],
		},
		faq: {
			heading: 'Founder-hosted journeys — common questions',
			items: [
				{ q: 'Does Ken travel for the whole journey?', a: '<p>Yes — that is what makes it founder-hosted. If a journey is only partly hosted, it is described that way before you book.</p>' },
				{ q: 'Is this a group trip?', a: '<p>No. Founder-hosted journeys are private to your party.</p>' },
				{ q: 'Is it more expensive?', a: '<p>Hosting is a real cost and is reflected in the price, which is confirmed on request once the itinerary is settled.</p>' },
				{ q: 'What if Ken is not available for our dates?', a: '<p>Then we say so, and offer either different dates or the same journey with a senior licensed guide. We do not substitute quietly.</p>' },
				{ q: 'What qualifies Ken as a guide?', a: '<p>He has held a Lao National Tour Guide licence since 2010 and founded Brother Tours in 2018.</p>' },
			],
			note: 'confirm whether partial hosting is offered and how substitution is handled contractually before publishing the "what if Ken is not available" answer.',
		},
		related: [
			{ text: 'Laos Signature Tours — the wider collection', url: '/laos-signature-tours/' },
			{ text: 'About Brother Tours', url: R.about },
			{ text: 'Private luxury travel in Laos', url: '/luxury-laos-tours/' },
			{ text: 'All journeys', url: R.tours },
		],
	},

	/* ------------------------------------------------- D. Honeymoon Packages */
	{
		slug: 'bt-honeymoon',
		title: 'BT – Honeymoon Landing',
		resourceId: 'honeymoon-guide',
		ctaContext: 'Honeymoon',
		hero: {
			eyebrow: 'Honeymoons',
			h1: 'A honeymoon built around two people',
			lede: 'Not a package with your names on it.',
			description:
				'Brother Tours does not sort honeymooners into tiers. We ask what the two of you actually want the days to feel like, and design the journey from there — typically seven to twelve nights across two or three places rather than a rush through many.',
			badges: ['Private throughout', 'Lao-led', 'No fixed packages'],
			imageAlt: 'A private river moment on the Mekong at dusk',
		},
		quickAnswer: {
			question: 'What does a Brother Tours honeymoon in Laos look like?',
			paragraphs: [
				'A private, unhurried journey of roughly seven to twelve nights, usually across two or three destinations, with private transfers, carefully chosen places to stay, and deliberate stretches of time without a guide.',
				'It suits couples who would rather have long mornings in one beautiful place than a photograph from six. Every element is chosen for you specifically; there is no bronze, silver or gold.',
			],
		},
		why: {
			eyebrow: 'How we do it',
			heading: 'Five things that make a honeymoon here work',
			cards: [
				{ title: 'Fewer places, longer stays', body: 'Two or three destinations across seven to twelve nights. Packing once and staying put is the luxury.' },
				{ title: 'Genuine privacy', body: 'Private guide, private vehicle, private boat where it matters. Nobody is added to your day.' },
				{ title: 'Time without us', body: 'Guide-free afternoons are designed in, not squeezed out. Some of the trip should be nobody\'s but yours.' },
				{ title: 'Places chosen for the two of you', body: 'Accommodation picked for the couple travelling rather than a standard tier — quiet over grand, where those conflict.' },
				{ title: 'Slower itinerary design', body: 'No 6am departures unless you want one. Days start when you start.' },
			],
		},
		journeys: {
			heading: 'Journeys couples travel most',
			intro: 'Live from the catalogue — a starting point to shape, not a menu to pick from. Price confirmed on request.',
			taxonomy: 'tour_category',
			term: 'honeymoon',
			count: 6,
		},
		destinations: {
			heading: 'Where honeymoons tend to go',
			intro: 'Most journeys combine two or three of these.',
			places: [
				{ text: 'Luang Prabang — the UNESCO-listed old town, temples and the Mekong', url: R.destinations },
				{ text: 'Nong Khiaw and the Nam Ou — river, limestone and near-total quiet', url: R.destinations },
				{ text: 'The Mekong — private boat days and slow river travel', url: R.destinations },
				{ text: 'Si Phan Don (4,000 Islands) — the southern river, hammock pace', url: R.destinations },
				{ text: 'The Bolaven Plateau — cool highlands, coffee and waterfalls', url: R.destinations },
			],
		},
		planning: {
			heading: 'Planning a honeymoon in Laos',
			items: [
				{ label: 'How long', body: 'Seven to twelve nights is the range that works. Under a week and it becomes a tour; beyond twelve nights most couples add a beach elsewhere in the region.' },
				{ label: 'When to go', body: 'November to February is dry and cool, and the most popular. March to May is hot. The green season, roughly June to October, is quieter and dramatic, with warm rain that usually falls in the afternoon rather than all day.' },
				{ label: 'How many places', body: 'Two or three. Every additional destination costs a half-day of travel that would otherwise be yours.' },
				{ label: 'Getting around', body: 'Private transfers throughout, private boat on river sections, and short flights or the railway where a drive would eat a day.' },
				{ label: 'Guide time', body: 'A guide for what benefits from one, and none for what does not. You choose the balance; most couples land near half.' },
				{ label: 'Celebrations', body: 'Anniversaries, proposals and quiet dinners can be arranged. Tell us and it is handled without turning into a performance.' },
			],
			note: 'confirm which specific properties Brother Tours currently works with before naming any hotel, and confirm what honeymoon extras (private dining, in-room arrangements, transfers) are actually offered and at what cost.',
		},
		insight: {
			paragraphs: [
				'The honeymoons that go wrong are the over-planned ones. Couples arrive exhausted from a wedding and are handed a schedule that starts at seven.',
				'So we design the first two days almost empty. Sleep, eat well, walk somewhere beautiful without a plan. The journey proper starts on day three, and by then you actually want it.',
			],
		},
		faq: {
			heading: 'Honeymoons in Laos — common questions',
			items: [
				{ q: 'Do you sell honeymoon packages?', a: '<p>No. Journeys are designed around the two people travelling. Price is confirmed on request once the itinerary is settled.</p>' },
				{ q: 'How many nights should we plan?', a: '<p>Seven to twelve, usually across two or three destinations. Fewer places, longer in each.</p>' },
				{ q: 'Is Laos a good honeymoon destination?', a: '<p>If you want quiet, landscape and genuine privacy, yes. If you want beach resorts and nightlife, it pairs better as the first half of a trip that ends elsewhere in the region.</p>' },
				{ q: 'Will we have a guide with us all the time?', a: '<p>Only where you want one. Guide-free time is part of the design.</p>' },
				{ q: 'Can we combine Laos with another country?', a: '<p>Yes — Laos-led routes into Vietnam, Cambodia or Thailand are common. See our Indochina journeys.</p>' },
				{ q: 'When is the best time of year?', a: '<p>November to February for dry, cool weather. The green season is quieter and greener, with afternoon rain.</p>' },
			],
		},
		related: [
			{ text: 'Private luxury travel in Laos', url: '/luxury-laos-tours/' },
			{ text: 'Laos + Indochina journeys', url: '/indochina-tours/' },
			{ text: 'When to visit Laos', url: R.whenToVisit },
			{ text: 'All destinations', url: R.destinations },
		],
	},

	/* ------------------------------------------------- E. Indochina Tours */
	{
		slug: 'bt-indochina',
		title: 'BT – Indochina Tours Landing',
		resourceId: 'indochina-planner',
		ctaContext: 'Indochina',
		hero: {
			eyebrow: 'Multi-country journeys',
			h1: 'Laos-led journeys across Indochina',
			lede: 'One operator who genuinely knows one country, coordinating the rest properly.',
			description:
				'Brother Tours is a Lao company. Laos is where our expertise is first-hand and our relationships are our own. When a journey continues into Vietnam, Cambodia or Thailand, we plan and coordinate it with selected partners — and we say which is which.',
			badges: ['Lao-led', 'Private routing', 'Cross-border planning'],
			imageAlt: 'A border crossing landscape between Laos and its neighbours',
		},
		quickAnswer: {
			question: 'What is a Laos-led Indochina journey?',
			paragraphs: [
				'A private multi-country trip planned from Laos outward. The Lao portion is operated by Brother Tours directly; onward sections in Vietnam, Cambodia or Thailand are arranged with selected regional partners under one itinerary and one point of contact.',
				'It suits travellers with two to four weeks who want more than one country without handing the trip to a mass-market operator that treats Laos as a two-night stopover.',
			],
		},
		why: {
			eyebrow: 'The approach',
			heading: 'Why start from Laos',
			intro: 'Most regional itineraries treat Laos as filler between Hanoi and Siem Reap. Ours is built the other way round.',
			cards: [
				{ title: 'Depth where we have it', body: 'In Laos we are not a booking agent. We know the guides, the roads and the seasons because we live here.' },
				{ title: 'Honest about the rest', body: 'Outside Laos we coordinate with selected partners, and we tell you that rather than implying we run everything.' },
				{ title: 'One itinerary, one contact', body: 'Borders, transfers and handovers are planned as a single journey. You are not passed between agencies at each frontier.' },
				{ title: 'Private routing', body: 'No fixed regional circuit. The route follows what you want to see and how long you have.' },
			],
		},
		journeys: {
			heading: 'Multi-country journeys',
			intro: 'Live from the catalogue. Price confirmed on request.',
			taxonomy: 'tour_category',
			term: 'indochina',
			count: 6,
		},
		planning: {
			heading: 'Planning a multi-country journey',
			items: [
				{ label: 'How long', body: 'Two weeks for two countries; three or more for a genuine three-country route. Anything shorter turns into airports.' },
				{ label: 'Common pairings', body: 'Laos and Vietnam, Laos and Cambodia, Laos and northern Thailand. Which works best depends on your dates and where you fly into.' },
				{ label: 'Borders', body: 'Overland crossings are possible on several routes and are often the more interesting way to travel. Some are slow. We advise where flying is simply better.' },
				{ label: 'Seasons differ', body: 'The region does not share one weather pattern. A month that is ideal in Luang Prabang can be wrong in central Vietnam, and the route order is set accordingly.' },
				{ label: 'Visas', body: 'Requirements differ per country and per nationality. We flag what applies to you; see also our visa guide.' },
				{ label: 'Pace', body: 'Two or three nights minimum per stop. The most common planning mistake here is one more country.' },
			],
			note: 'confirm exactly which countries Brother Tours currently coordinates, which partners operate each, and what is contractually promised at each border handover. Do not publish any partner, airline or hotel name that is not confirmed. The legacy page carried $1,699-$4,499 package pricing — not reproduced here and not to be restored without verification.',
		},
		insight: {
			paragraphs: [
				'People ask us to add a fourth country and we usually talk them out of it. Three weeks across four countries is a travel schedule, not a journey — most of it is spent moving.',
				'The better version is two countries properly, with the Lao section long enough to slow down in. That is the part nobody else does well, and it is the part people remember.',
			],
		},
		faq: {
			heading: 'Indochina journeys — common questions',
			items: [
				{ q: 'Does Brother Tours operate in Vietnam and Cambodia directly?', a: '<p>Laos is where we operate directly. Elsewhere we plan the journey and coordinate it with selected regional partners. We are explicit about which sections are which.</p>' },
				{ q: 'How long do we need?', a: '<p>Two weeks for two countries, three or more for three. Below that the trip becomes transit.</p>' },
				{ q: 'Can we cross borders overland?', a: '<p>On several routes, yes, and it is often the better experience. Some crossings are slow, and we will tell you when flying makes more sense.</p>' },
				{ q: 'Is it one price for the whole journey?', a: '<p>Yes — one itinerary, one quotation, confirmed on request once the route is set.</p>' },
				{ q: 'Which combination is best?', a: '<p>It depends on your dates and your arrival airport. Tell us both and we will recommend rather than guess.</p>' },
			],
		},
		related: [
			{ text: 'Laos Signature Tours', url: '/laos-signature-tours/' },
			{ text: 'Visa guide', url: R.visa },
			{ text: 'When to visit Laos', url: R.whenToVisit },
			{ text: 'All journeys', url: R.tours },
		],
	},

	/* --------------------------------------------- F. Laos Signature Tours */
	{
		slug: 'bt-signature-tours',
		title: 'BT – Laos Signature Tours Landing',
		resourceId: 'signature-guide',
		ctaContext: 'Signature Journey',
		hero: {
			eyebrow: 'The collection',
			h1: 'Laos Signature Journeys',
			lede: 'The routes we would travel ourselves.',
			description:
				'Signature journeys are the itineraries Brother Tours has refined over years of running them — the ones where the order of the days, the length of each stay and the choice of guide have all been argued about. Private, Lao-led, and adjustable in every direction.',
			badges: ['Lao-led', 'Private journeys', 'Refined over years'],
			imageAlt: 'A signature Brother Tours moment in the Lao landscape',
		},
		quickAnswer: {
			question: 'What makes a journey a Signature journey?',
			paragraphs: [
				'A Signature journey is a Brother Tours itinerary that has been run, revised and run again — where the sequence and pacing are deliberate rather than assembled to order. Each is private to your group and adjustable.',
				'They suit travellers who want a designed route rather than a blank page, and who are happy to start from a strong itinerary and change what does not fit.',
			],
		},
		why: {
			eyebrow: 'What sets them apart',
			heading: 'Designed, not assembled',
			cards: [
				{ title: 'Sequence matters', body: 'Which place comes third changes the whole trip. Signature routes are ordered on purpose, usually after we got it wrong once.' },
				{ title: 'Local hosting', body: 'Licensed Lao guides throughout, chosen for the region rather than dispatched from a pool.' },
				{ title: 'Cultural depth', body: 'Time with people who live where you are visiting, arranged where it is genuinely welcome rather than staged for a group.' },
				{ title: 'Responsible by construction', body: 'Lao-owned, Lao-staffed, spending locally. Small private groups rather than coaches.' },
				{ title: 'Private and adjustable', body: 'Start from the Signature route and change it. It is a proposal, not a product.' },
				{ title: 'Founder-hosted as a subset', body: 'A small number of Signature journeys are hosted by Ken personally.' },
			],
		},
		journeys: {
			heading: 'Signature journeys currently offered',
			intro: 'Live from the catalogue — retired journeys disappear from this list on their own. Price confirmed on request.',
			taxonomy: 'tour_category',
			term: 'signature-journeys',
			count: 6,
		},
		destinations: {
			heading: 'Regions covered',
			intro: 'Signature routes are drawn across the whole country rather than one corner of it.',
			places: [
				{ text: 'Luang Prabang and the north', url: R.destinations },
				{ text: 'Nong Khiaw and the Nam Ou', url: R.destinations },
				{ text: 'Vientiane and Central Laos', url: '/central-laos/' },
				{ text: 'Vang Vieng and the karst country', url: R.destinations },
				{ text: 'The Bolaven Plateau and the south', url: R.destinations },
				{ text: 'Si Phan Don (4,000 Islands)', url: R.destinations },
			],
		},
		planning: {
			heading: 'Planning a Signature journey',
			items: [
				{ label: 'How long', body: 'Most Signature routes run eight to fourteen days. Shorter versions exist; they cover one region rather than a compressed version of several.' },
				{ label: 'When to go', body: 'November to February is dry and cool; March to May is hot; roughly June to October is the green season — quieter, greener, with afternoon rain.' },
				{ label: 'How much changes', body: 'Anything. The Signature route is the starting proposal, and most journeys end up two or three revisions from it.' },
				{ label: 'Pace', body: 'Two to three nights per base. Long single-day transfers are designed out wherever the railway or a short flight can replace them.' },
				{ label: 'Who travels this way', body: 'Couples, families and small private groups. All journeys are private to your party.' },
				{ label: 'How pricing works', body: 'Confirmed on request, once the route and standard of accommodation are settled.' },
			],
		},
		insight: {
			paragraphs: [
				'A Signature journey is not the most places we can fit into your dates. It is the fewest places that make the trip make sense.',
				'The routes that lasted are the ones where the third day is quiet. Everybody remembers the arrival and the finale; what decides whether the journey worked is what happened in the middle, and that only works with room in it.',
			],
		},
		faq: {
			heading: 'Signature journeys — common questions',
			items: [
				{ q: 'How is this different from the Founder-Hosted page?', a: '<p>Founder-hosted journeys are a small subset of Signature journeys on which Ken travels with you personally. This page is the wider collection.</p>' },
				{ q: 'Can we change the itinerary?', a: '<p>Yes, in any direction. It is a designed starting point, not a fixed product.</p>' },
				{ q: 'Are these group departures?', a: '<p>No. Every journey is private to your party.</p>' },
				{ q: 'What if a journey we saw before is gone?', a: '<p>Then it is no longer offered — this page shows only current journeys. Tell us what appealed about it and we will build the equivalent.</p>' },
				{ q: 'How far ahead should we book?', a: '<p>For November to February, as early as you can. The green season is far more flexible.</p>' },
			],
		},
		related: [
			{ text: 'Founder-hosted signature journeys', url: '/founder-hosted-signature-journeys/' },
			{ text: 'Adventure journeys', url: '/adventure-tours/' },
			{ text: 'Private luxury travel in Laos', url: '/luxury-laos-tours/' },
			{ text: 'All destinations', url: R.destinations },
		],
	},

	/* ---------------------------------------- G. Lao-China Railway guide */
	{
		slug: 'bt-lcr-guide',
		title: 'BT – LCR E-Ticket Guide Landing',
		resourceId: 'lcr-guide',
		ctaContext: 'Railway ticket assistance',
		hero: {
			eyebrow: 'Travel guide',
			h1: 'The Lao-China Railway e-ticket, explained',
			lede: 'What the ticket is, how it is used, and where travellers get caught out.',
			description:
				'The Lao-China Railway opened in December 2021 and changed how people move between Vientiane, Vang Vieng and Luang Prabang. The ticketing is straightforward once you have done it once. This guide is for the first time.',
			badges: ['Practical guide', 'Updated by Brother Tours', 'Ticket assistance available'],
			imageAlt: 'A Lao-China Railway station platform',
		},
		quickAnswer: {
			question: 'What is the Lao-China Railway e-ticket?',
			paragraphs: [
				'It is the electronic ticket for the railway that runs through Laos between Vientiane and the Chinese border at Boten, calling at stations including Vang Vieng and Luang Prabang. Tickets are issued against the passport of the traveller and presented at the station.',
				'This page explains the sequence — before you arrive, at the station, boarding, on board and on arrival — and the points where travellers most often run into trouble.',
			],
		},
		why: {
			eyebrow: 'Why it matters',
			heading: 'What the railway changed',
			cards: [
				{ title: 'Hours, not half-days', body: 'Journeys that were long mountain drives became short hops, which changes how an itinerary can be built.' },
				{ title: 'Stations are outside towns', body: 'The station is not the town. Transfer time at both ends is real and needs planning into the day.' },
				{ title: 'Passport-linked ticketing', body: 'Tickets are tied to the traveller\'s passport, so the name and document you book with matter.' },
				{ title: 'Demand is uneven', body: 'Popular services around holidays and peak season sell out well ahead. Flexibility is worth more than a preferred departure time.' },
			],
		},
		journeys: {
			heading: 'Journeys that use the railway',
			intro: 'Live from the catalogue. Price confirmed on request.',
			taxonomy: '',
			term: '',
			count: 6,
		},
		planning: {
			heading: 'Using the railway — the sequence',
			items: [
				{ label: '1 · Before you travel', body: 'Book against the passport each traveller will carry. Confirm the station for your town — several are a significant drive from the centre — and build the transfer into your plan at both ends.' },
				{ label: '2 · Arriving at the station', body: 'Arrive well ahead of departure. Stations run a security and check-in process before you reach the platform, and the queue is the part that varies.' },
				{ label: '3 · Security and check-in', body: 'Bags are screened. Passport and ticket are checked together, so keep both accessible rather than packed.' },
				{ label: '4 · Boarding', body: 'Platforms open shortly before departure and services leave on time. Seats and carriages are assigned on the ticket.' },
				{ label: '5 · On board', body: 'Tickets and identity may be inspected during the journey. Keep them to hand rather than in the overhead luggage.' },
				{ label: '6 · Arrival and exit', body: 'Exit is via a ticket check. Arrange onward transport in advance where the station is far from town — options at the door can be limited and expensive.' },
				{ label: '7 · Luggage', body: 'Allowances and size limits apply, and oversized items are handled separately. Confirm the current rules before you pack for the train.' },
				{ label: '8 · Restricted items', body: 'The railway operates a prohibited and restricted item list comparable to air travel. It changes. Check the current list before travelling.' },
			],
			note: 'HIGH PRIORITY — every operational detail on this page must be checked against the 2026 rules before publishing: exact arrival times before departure, luggage weight and dimension limits, the current prohibited and restricted item list, ID requirements for children, refund and change rules, ticket release windows, and the current station list with distances from each town. The legacy ZIP contains older rules that must not be republished. Add a visible "Last reviewed: [date]" line once verified.',
		},
		insight: {
			eyebrow: 'Local planning note',
			paragraphs: [
				'The two things that catch people out are both about distance rather than the train. The station is often a long way from where you are staying, and the taxi at the other end is not always waiting.',
				'So we treat a rail leg as a half-day, not as the length of the journey itself. Booked that way it is a genuine improvement on the drive. Booked as a two-hour hop, it turns into a stressful morning.',
			],
		},
		faq: {
			heading: 'Lao-China Railway — common questions',
			items: [
				{ q: 'Do I need my passport to travel?', a: '<p>Yes. Tickets are issued against a passport and identity is checked at the station, so travel with the document you booked with.</p>' },
				{ q: 'How far ahead should I book?', a: '<p>As far ahead as you reasonably can for peak season and holiday periods, when popular services fill. Outside those, availability is easier.</p>' },
				{ q: 'How far is the station from town?', a: '<p>It varies by station and several are a substantial drive from the town centre. Plan the transfer at both ends as part of the journey.</p>' },
				{ q: 'Can Brother Tours book tickets for me?', a: '<p>We assist travellers with rail arrangements as part of a journey. Tell us your dates and route and we will advise on what is possible.</p>' },
				{ q: 'What can I take on board?', a: '<p>Luggage allowances and a restricted-item list apply, broadly comparable to air travel. These change, so check the current rules close to your travel date.</p>' },
				{ q: 'Is the railway better than flying or driving?', a: '<p>On the Vientiane–Vang Vieng–Luang Prabang corridor it is usually the best option once transfer time is counted. For other routes, not always — we advise per itinerary.</p>' },
			],
			note: 'each answer above is deliberately non-specific about numbers. Add exact times, allowances and prices only after verifying the current 2026 rules with the operator.',
		},
		related: [
			{ text: 'Central Laos — the railway corridor', url: '/central-laos/' },
			{ text: 'When to visit Laos', url: R.whenToVisit },
			{ text: 'Visa guide', url: R.visa },
			{ text: 'Talk to Brother Tours about rail arrangements', url: R.contact },
		],
	},

	/* --------------------------------------------------- H. Luxury Laos */
	{
		slug: 'bt-luxury-laos',
		title: 'BT – Luxury Laos Landing',
		resourceId: 'luxury-guide',
		ctaContext: 'Private Luxury',
		hero: {
			eyebrow: 'Private travel',
			h1: 'Private luxury travel in Laos',
			lede: 'Unhurried, private, and quietly well organised.',
			description:
				'Luxury here is not a marble lobby. It is a journey where nothing is rushed, nothing is shared, and everything has been arranged by someone who will answer the phone — private guides, considered places to stay, and days with room in them.',
			badges: ['Entirely private', 'Lao-led', 'Concierge-level planning'],
			imageAlt: 'A quiet, considered interior or private river setting in Laos',
		},
		quickAnswer: {
			question: 'What does luxury travel in Laos actually mean?',
			paragraphs: [
				'A private journey with a dedicated guide and vehicle, boutique places to stay chosen individually rather than by star rating, private river travel where the river is the best route, and an itinerary with deliberate space in it.',
				'It suits travellers who value privacy, quiet and good logistics over grandeur — and who would rather have an excellent dinner in a small place than a large one in a chain.',
			],
		},
		why: {
			eyebrow: 'Our definition',
			heading: 'What we mean by luxury',
			intro: 'Laos does not do ostentation well. It does privacy, quiet and access extremely well.',
			cards: [
				{ title: 'Private throughout', body: 'Your guide, your driver, your boat. No shared transfers and no joining a group for the temple tour.' },
				{ title: 'Unhurried by design', body: 'Long stays, late starts and afternoons with nothing scheduled. Space is the thing being bought.' },
				{ title: 'Personal access', body: 'Introductions that come from relationships rather than a booking system — the reason a morning becomes memorable.' },
				{ title: 'Boutique over branded', body: 'Places chosen individually for character, quiet and location, not for the badge on the door.' },
				{ title: 'Thoughtful logistics', body: 'Good vehicles, sensible transfer timing, and someone reachable in Vientiane throughout the journey.' },
				{ title: 'Food that is worth the detour', body: 'Where and what to eat is planned as carefully as where to sleep.' },
			],
		},
		journeys: {
			heading: 'Private journeys',
			intro: 'Live from the catalogue. Price confirmed on request.',
			taxonomy: 'tour_category',
			term: 'luxury',
			count: 6,
		},
		planning: {
			heading: 'Planning a private journey',
			items: [
				{ label: 'How long', body: 'Eight to fourteen days lets a private journey breathe. Shorter is possible in one region.' },
				{ label: 'When to go', body: 'November to February is dry, cool and busiest. The green season, roughly June to October, is quieter and often the better time to have a place to yourself.' },
				{ label: 'Where to stay', body: 'Chosen individually for each journey. We recommend rather than upsell, and will say when the more expensive option is not the better one.' },
				{ label: 'Private river travel', body: 'Where the Mekong or the Nam Ou is the best route, a private boat is usually worth more to the day than a faster road.' },
				{ label: 'Guide time', body: 'A private guide throughout, with as much or as little of your day as you want structured.' },
				{ label: 'How pricing works', body: 'Confirmed on request once the route and standard of accommodation are settled. There is no fixed tier.' },
			],
			note: 'do not name any hotel, resort or transport partner without written confirmation of the current relationship. The legacy page carried claims — ABTA registration, private jets, exclusive accreditation, guaranteed 5-star properties — and $7,200 / $9,500 / $15,000 pricing. None are reproduced here, and none should be restored without documentary evidence.',
		},
		insight: {
			paragraphs: [
				'The most expensive room in Laos is rarely the best one. Some of the finest places to stay here have twelve rooms and no spa, and the reason to choose them is where they are and how quiet they are at six in the morning.',
				'What genuinely costs money, and is genuinely worth it, is privacy and time — a boat that is yours for the day, a guide who is not watching the clock, and a schedule loose enough to change.',
			],
		},
		faq: {
			heading: 'Private luxury travel — common questions',
			items: [
				{ q: 'Are the journeys entirely private?', a: '<p>Yes. Private guide, private vehicle, and private boat on river sections. Nobody joins your itinerary.</p>' },
				{ q: 'Which hotels do you use?', a: '<p>Properties are chosen individually for each journey rather than from a fixed list, and recommended for location, character and quiet.</p>' },
				{ q: 'Is Laos a five-star destination?', a: '<p>Not in the way Bangkok or Singapore are, and that is rather the point. What Laos offers at the top end is privacy, setting and access.</p>' },
				{ q: 'How much does it cost?', a: '<p>Price is confirmed on request once the itinerary and standard of accommodation are settled.</p>' },
				{ q: 'Can you handle special requests?', a: '<p>Tell us what matters — dietary, mobility, celebrations, a particular interest — and it is built into the plan rather than bolted on.</p>' },
			],
		},
		related: [
			{ text: 'Founder-hosted signature journeys', url: '/founder-hosted-signature-journeys/' },
			{ text: 'Honeymoons in Laos', url: '/honeymoon-packages/' },
			{ text: 'Laos Signature Tours', url: '/laos-signature-tours/' },
			{ text: 'All destinations', url: R.destinations },
		],
	},

	/* --------------------------------------- I. Student Group Learning */
	{
		slug: 'bt-student-groups',
		title: 'BT – Student Group Learning Landing',
		resourceId: 'student-group-planner',
		ctaContext: 'Student Group',
		hero: {
			eyebrow: 'Educational travel',
			h1: 'Student group learning in Laos',
			lede: 'Structured educational travel for schools and universities.',
			description:
				'Brother Tours plans and runs learning journeys in Laos for school and university groups — ecology, history, language and community-based learning, with the logistics handled so that faculty can concentrate on the students.',
			badges: ['Lao-led', 'Faculty coordination', 'Structured logistics'],
			imageAlt: 'A student group in a learning setting in Laos',
		},
		quickAnswer: {
			question: 'What is a student group learning journey?',
			paragraphs: [
				'A structured educational trip to Laos designed with the visiting institution: learning objectives first, then the route, the sites and the people who will teach at each. Brother Tours handles transport, accommodation, guiding and on-the-ground coordination.',
				'It suits schools and universities running field courses, cultural immersion programmes, language study or service-learning components, from a short field week to a full semester module.',
			],
		},
		why: {
			eyebrow: 'What we cover',
			heading: 'Learning areas',
			intro: 'Programmes are built to the syllabus rather than picked from a brochure.',
			cards: [
				{ title: 'Ecology and environment', body: 'River systems, forest, agriculture and the Bolaven highlands — field sites with people who work them.' },
				{ title: 'History and heritage', body: 'UNESCO-listed Luang Prabang, the Plain of Jars, the Vieng Xai caves and twentieth-century Lao history in the places it happened.' },
				{ title: 'Language and culture', body: 'Lao language practice in context, with structured sessions and daily use rather than a phrasebook exercise.' },
				{ title: 'Community-based learning', body: 'Time with communities arranged where it is genuinely welcome and mutually useful, not staged for a visiting group.' },
				{ title: 'Service learning', body: 'Where a group wants a contribution component, it is designed with the receiving community and matched to what is actually needed.' },
				{ title: 'Faculty coordination', body: 'A single planning contact before departure and a coordinator on the ground throughout.' },
			],
		},
		journeys: {
			heading: 'Related journeys',
			intro: 'Group programmes are built from scratch; these show the regions and styles we work in. Price confirmed on request.',
			taxonomy: '',
			term: '',
			count: 6,
		},
		planning: {
			heading: 'Planning a student journey',
			items: [
				{ label: 'How long', body: 'From a one-week field trip to a multi-week programme. Two weeks is the most common shape for an overseas module.' },
				{ label: 'When to go', body: 'November to February is dry and cool and fits most northern-hemisphere academic calendars. The green season works for ecology-focused programmes where full rivers are the subject.' },
				{ label: 'Lead time', body: 'Start early. Group programmes involve approvals on your side and site arrangements on ours, and both take longer than a private trip.' },
				{ label: 'How planning works', body: 'Send the learning objectives, group size, dates and budget range. The first draft is an itinerary mapped to those objectives, not a quotation.' },
				{ label: 'Logistics', body: 'Transport, accommodation, meals, guiding and site access are coordinated as one programme, with a named contact throughout.' },
				{ label: 'Safety and welfare', body: 'Group welfare arrangements are agreed with the institution in advance and documented as part of the programme.' },
			],
			note: 'this page makes no claim about curriculum accreditation, insurance cover, safeguarding certification, free teacher places, staff-to-student ratios, risk assessment documentation or fixed group pricing. Do not add any of these without written verification — the legacy page carried several. Confirm what Brother Tours actually provides on safety, insurance and safeguarding, and state only that.',
		},
		insight: {
			eyebrow: 'Local planning note',
			paragraphs: [
				'The programmes that work are the ones where the objectives arrive before the itinerary. When a faculty lead tells us what students should be able to do by the end, the route almost designs itself.',
				'The ones that struggle are built as a tour with a lecture attached. Laos is a small country with real access — a group that comes with questions gets time with people who can answer them.',
			],
		},
		faq: {
			heading: 'Student group travel — common questions',
			items: [
				{ q: 'What group sizes do you work with?', a: '<p>Tell us your group size and staffing and we will confirm what we can support for your dates.</p>' },
				{ q: 'How far ahead should we plan?', a: '<p>As early as possible. Institutional approvals and site arrangements both take time, and the best field sites are limited.</p>' },
				{ q: 'Can the programme match our syllabus?', a: '<p>That is how it is built. Send the learning objectives first and the itinerary is designed to them.</p>' },
				{ q: 'Who is with the group on the ground?', a: '<p>Licensed Lao guides and a Brother Tours coordinator, alongside your own faculty. Specific arrangements are agreed with your institution in advance.</p>' },
				{ q: 'How is it priced?', a: '<p>Per programme, confirmed on request once group size, dates and content are settled.</p>' },
				{ q: 'Do you arrange service-learning components?', a: '<p>Where a community genuinely wants one and it can be done well. We would rather decline than arrange something performative.</p>' },
			],
		},
		related: [
			{ text: 'Destinations across Laos', url: R.destinations },
			{ text: 'When to visit Laos', url: R.whenToVisit },
			{ text: 'Visa guide', url: R.visa },
			{ text: 'Talk to Brother Tours', url: R.contact },
		],
	},

	/* ------------------------------ J. Upcoming Tours / Journey Calendar */
	{
		slug: 'bt-journey-calendar',
		title: 'BT – Upcoming / Journey Calendar Landing',
		resourceId: 'journey-calendar',
		ctaContext: 'Journey Calendar',
		hero: {
			eyebrow: 'Planning calendar',
			h1: 'When to travel, and what is available',
			lede: 'Private dates available on request, year-round.',
			description:
				'Brother Tours journeys are private, so the calendar that matters is yours. This page explains what each part of the year is actually like in Laos, and shows the journeys currently offered — with dates confirmed by a person when you ask.',
			badges: ['Private dates', 'Year-round travel', 'Human confirmation'],
			imageAlt: 'Seasonal Lao landscape showing the change through the year',
		},
		quickAnswer: {
			question: 'When is the best time to travel in Laos?',
			paragraphs: [
				'November to February is dry and cool and is the most popular window. March to May is hot, and the end of it is hazy in the north. Roughly June to October is the green season: warm, dramatic, much quieter, with rain that usually falls in the afternoon rather than all day.',
				'Because journeys are private rather than scheduled departures, there is no fixed calendar to fit into. Tell us your dates and we confirm what works for them.',
			],
		},
		why: {
			eyebrow: 'The year',
			heading: 'What each season is actually like',
			intro: 'Not "best" and "worst" — different, and suited to different trips.',
			cards: [
				{ title: 'November to February', body: 'Dry and cool, clear light, comfortable walking. The busiest months, and the ones to book earliest for.' },
				{ title: 'March to May', body: 'Hot, and hazy in the north towards the end as agricultural burning peaks. Good for the far south and the higher, cooler Bolaven Plateau.' },
				{ title: 'June to October', body: 'The green season. Full rivers, deep green landscape, dramatic skies and far fewer travellers. Rain is usually an afternoon event rather than a lost day.' },
				{ title: 'Festivals', body: 'Lao New Year in April and the boat racing and light festivals later in the year are worth planning around — in either direction.' },
			],
		},
		journeys: {
			heading: 'Journeys currently offered',
			intro: 'Live from the Brother Tours catalogue. Nothing on this page is a scheduled departure — dates are yours, and price is confirmed on request.',
			taxonomy: '',
			term: '',
			count: 9,
		},
		planning: {
			heading: 'How dates actually get confirmed',
			items: [
				{ label: 'No fixed departures', body: 'Journeys are private. There is no seat to compete for and no scarcity to create — this page will never tell you that two places are left.' },
				{ label: 'Tell us your window', body: 'Even approximate dates are enough to start. Route order is often adjusted to fit the season you are travelling in.' },
				{ label: 'Human confirmation', body: 'Availability is confirmed by a person, not a calendar widget. That is slower by a day and correct rather than optimistic.' },
				{ label: 'Peak season lead time', body: 'For November to February, plan well ahead — guides and the better small properties go first.' },
				{ label: 'Green season flexibility', body: 'From roughly June to October, plans can be made much closer to the date.' },
				{ label: 'Changing dates', body: 'If your plans move, tell us early and we will re-plan around them.' },
			],
			note: 'if Brother Tours does begin publishing scheduled departures with real availability, replace this section with the live departure query. Until then this page must not display dates, seat counts or countdowns of any kind.',
		},
		insight: {
			paragraphs: [
				'Everybody asks for January and we understand why. But some of the best travelling we do here is in September, when the rivers are high, the country is completely green and you have the place largely to yourself.',
				'The rain in the green season is not the all-day British kind. It arrives in the afternoon, it is warm, and it is over in an hour. Plan the morning properly and the season barely costs you anything.',
			],
		},
		faq: {
			heading: 'Timing and availability — common questions',
			items: [
				{ q: 'Do you have scheduled departures?', a: '<p>Journeys are private, so dates are yours rather than fixed. If that changes we will publish real availability here rather than indicative dates.</p>' },
				{ q: 'When is the best time to visit Laos?', a: '<p>November to February for dry, cool weather. March to May is hot. June to October is green, quiet and dramatic, with afternoon rain.</p>' },
				{ q: 'How far ahead should we book?', a: '<p>For peak season, as early as you can. For the green season, much closer in is fine.</p>' },
				{ q: 'Is the green season a bad time to travel?', a: '<p>No — it is a different trip. Fuller rivers, greener landscape, fewer people. Some trails and back roads are harder going, and we route around that.</p>' },
				{ q: 'Can we travel over a festival?', a: '<p>Yes, and it can be the highlight. It also affects transport and accommodation, so it needs planning earlier.</p>' },
				{ q: 'How do we get a price?', a: '<p>Price is confirmed on request once the route and dates are settled.</p>' },
			],
		},
		related: [
			{ text: 'When to visit Laos — the full guide', url: R.whenToVisit },
			{ text: 'All journeys', url: R.tours },
			{ text: 'Laos Signature Tours', url: '/laos-signature-tours/' },
			{ text: 'Destinations across Laos', url: R.destinations },
		],
	},
];

/**
 * Section order. `destinations` is skipped on pages that did not define it —
 * a page with nothing honest to put in a section does not get an empty one.
 */
export const SECTION_ORDER = [
	'hero',
	'quickAnswer',
	'whyCards',
	'featuredJourneys',
	'destinations',
	'planningGuide',
	'pdfResource',
	'localInsight',
	'howWePlanIt',
	'faq',
	'relatedContent',
	'finalInvitation',
];
