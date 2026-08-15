( function ( wp ) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
	const { PanelBody, TextControl, TextareaControl, Button, Notice } = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	const text = ( label, value, onChange, multiline ) => multiline
		? el( TextareaControl, { label, value: value || '', onChange, rows: 4 } )
		: el( TextControl, { label, value: value || '', onChange } );

	const imageControl = ( attributes, setAttributes, label ) => el(
		MediaUploadCheck,
		{},
		el( MediaUpload, {
			allowedTypes: [ 'image' ],
			value: attributes.imageId || 0,
			onSelect: ( media ) => setAttributes( { imageId: media.id, imageUrl: media.url } ),
			render: ( { open } ) => el( Fragment, {},
				el( Button, { variant: 'secondary', onClick: open }, attributes.imageId ? __( 'Replace image', 'brother-tours-content-studio' ) : __( 'Choose image', 'brother-tours-content-studio' ) ),
				attributes.imageId ? el( Button, { variant: 'link', onClick: () => setAttributes( { imageId: 0, imageUrl: '' } ) }, __( 'Remove', 'brother-tours-content-studio' ) ) : null
			)
		} )
	);

	const galleryControl = ( attributes, setAttributes ) => el(
		MediaUploadCheck,
		{},
		el( MediaUpload, {
			allowedTypes: [ 'image' ],
			multiple: true,
			gallery: true,
			value: ( attributes.images || [] ).map( ( image ) => image.id ).filter( Boolean ),
			onSelect: ( media ) => setAttributes( { images: media.map( ( image ) => ( { id: image.id, url: image.url, alt: image.alt || image.title || '', caption: image.caption || '' } ) ) } ),
			render: ( { open } ) => el( Button, { variant: 'secondary', onClick: open }, __( 'Choose gallery images', 'brother-tours-content-studio' ) )
		} )
	);

	const lines = ( value ) => Array.isArray( value ) ? value.join( '\n' ) : '';
	const arrayFromLines = ( value ) => value.split( /\r?\n/ ).map( ( item ) => item.trim() ).filter( Boolean );

	const repeater = ( items, setItems, fields, empty ) => el( 'div', { className: 'bt-cs-repeater' },
		( items || [] ).map( ( item, index ) => el( 'div', { className: 'bt-cs-repeater__item', key: index },
			fields.map( ( field ) => text( field.label, item[ field.key ], ( value ) => {
				const next = items.slice();
				next[ index ] = Object.assign( {}, next[ index ], { [ field.key ]: value } );
				setItems( next );
			}, field.multiline ) ),
			el( Button, { isDestructive: true, variant: 'link', onClick: () => setItems( items.filter( ( _, itemIndex ) => itemIndex !== index ) ) }, __( 'Remove item', 'brother-tours-content-studio' ) )
		) ),
		el( Button, { variant: 'secondary', onClick: () => setItems( ( items || [] ).concat( [ Object.assign( {}, empty ) ] ) ) }, __( 'Add item', 'brother-tours-content-studio' ) )
	);

	const definitions = {
		hero: { title: __( 'Hero Section', 'brother-tours-content-studio' ), attributes: { heading: { type: 'string', default: 'Experience Laos through the people who call it home.' }, eyebrow: { type: 'string', default: 'Private Laos journeys' }, body: { type: 'string', default: '' }, imageId: { type: 'number', default: 0 }, imageUrl: { type: 'string', default: '' }, primaryText: { type: 'string', default: 'See our journeys' }, primaryUrl: { type: 'string', default: '/tours/' }, secondaryText: { type: 'string', default: 'Build my trip' }, secondaryUrl: { type: 'string', default: '/build-my-trip/' } } },
		'tour-collection': { title: __( 'Tour Collection', 'brother-tours-content-studio' ), attributes: { heading: { type: 'string', default: 'Signature journeys' }, body: { type: 'string', default: '' }, count: { type: 'number', default: 6 }, layout: { type: 'string', default: 'grid' }, category: { type: 'string', default: '' } } },
		'destination-grid': { title: __( 'Destination Grid', 'brother-tours-content-studio' ), attributes: { heading: { type: 'string', default: 'Where we go' }, body: { type: 'string', default: '' }, count: { type: 'number', default: 6 } } },
		'trust-facts': { title: __( 'Trust / Facts Strip', 'brother-tours-content-studio' ), attributes: { items: { type: 'array', default: [ { value: '2010', label: 'Licensed Lao guide' }, { value: '2018', label: 'Brother Tours founded' } ] } } },
		'founder-profile': { title: __( 'Founder Profile', 'brother-tours-content-studio' ), attributes: { name: { type: 'string', default: '' }, role: { type: 'string', default: '' }, bio: { type: 'string', default: '' }, credentials: { type: 'string', default: '' }, imageId: { type: 'number', default: 0 }, imageUrl: { type: 'string', default: '' } } },
		review: { title: __( 'Review Block', 'brother-tours-content-studio' ), attributes: { quote: { type: 'string', default: '' }, author: { type: 'string', default: '' }, tripReference: { type: 'string', default: '' }, rating: { type: 'number', default: 0 } } },
		itinerary: { title: __( 'Itinerary Block', 'brother-tours-content-studio' ), attributes: { heading: { type: 'string', default: 'Day by day' }, items: { type: 'array', default: [] } } },
		'included-excluded': { title: __( 'Included / Excluded', 'brother-tours-content-studio' ), attributes: { includedHeading: { type: 'string', default: "What's included" }, excludedHeading: { type: 'string', default: "What's not included" }, included: { type: 'array', default: [] }, excluded: { type: 'array', default: [] } } },
		faq: { title: __( 'FAQ Block', 'brother-tours-content-studio' ), attributes: { heading: { type: 'string', default: 'Questions travellers ask' }, items: { type: 'array', default: [] } } },
		'gallery-story': { title: __( 'Gallery / Story', 'brother-tours-content-studio' ), attributes: { heading: { type: 'string', default: 'A closer look' }, images: { type: 'array', default: [] } } },
		'cta-inquiry': { title: __( 'CTA & Inquiry', 'brother-tours-content-studio' ), attributes: { heading: { type: 'string', default: 'Tell us what you are imagining.' }, body: { type: 'string', default: 'We design your journey around the way you want to experience Laos.' }, primaryText: { type: 'string', default: 'Build my trip' }, primaryUrl: { type: 'string', default: '/build-my-trip/' }, whatsappText: { type: 'string', default: 'Ask on WhatsApp' }, whatsappUrl: { type: 'string', default: '' } } },
		newsletter: { title: __( 'Newsletter Block', 'brother-tours-content-studio' ), attributes: { heading: { type: 'string', default: 'Stay close to Laos' }, body: { type: 'string', default: '' } } }
	};

	function controlsFor( slug, attributes, setAttributes ) {
		const controls = [];
		const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );
		if ( [ 'hero', 'founder-profile' ].includes( slug ) ) {
			controls.push( imageControl( attributes, setAttributes, __( 'Image', 'brother-tours-content-studio' ) ) );
		}
		if ( slug === 'hero' ) {
			controls.push( text( __( 'Eyebrow', 'brother-tours-content-studio' ), attributes.eyebrow, set( 'eyebrow' ) ), text( __( 'Heading', 'brother-tours-content-studio' ), attributes.heading, set( 'heading' ) ), text( __( 'Body', 'brother-tours-content-studio' ), attributes.body, set( 'body' ), true ), text( __( 'Primary button', 'brother-tours-content-studio' ), attributes.primaryText, set( 'primaryText' ) ), text( __( 'Primary URL', 'brother-tours-content-studio' ), attributes.primaryUrl, set( 'primaryUrl' ) ), text( __( 'Secondary button', 'brother-tours-content-studio' ), attributes.secondaryText, set( 'secondaryText' ) ), text( __( 'Secondary URL', 'brother-tours-content-studio' ), attributes.secondaryUrl, set( 'secondaryUrl' ) ) );
		} else if ( [ 'tour-collection', 'destination-grid' ].includes( slug ) ) {
			controls.push( text( __( 'Heading', 'brother-tours-content-studio' ), attributes.heading, set( 'heading' ) ), text( __( 'Intro', 'brother-tours-content-studio' ), attributes.body, set( 'body' ), true ), text( __( 'Number of cards', 'brother-tours-content-studio' ), attributes.count, set( 'count' ) ), slug === 'tour-collection' ? text( __( 'Tour category slug', 'brother-tours-content-studio' ), attributes.category, set( 'category' ) ) : null );
		} else if ( slug === 'trust-facts' ) {
			controls.push( repeater( attributes.items, ( items ) => setAttributes( { items } ), [ { key: 'value', label: __( 'Value', 'brother-tours-content-studio' ) }, { key: 'label', label: __( 'Label', 'brother-tours-content-studio' ) } ], { value: '', label: '' } ) );
		} else if ( slug === 'founder-profile' ) {
			controls.push( text( __( 'Name', 'brother-tours-content-studio' ), attributes.name, set( 'name' ) ), text( __( 'Role', 'brother-tours-content-studio' ), attributes.role, set( 'role' ) ), text( __( 'Bio', 'brother-tours-content-studio' ), attributes.bio, set( 'bio' ), true ), text( __( 'Credentials', 'brother-tours-content-studio' ), attributes.credentials, set( 'credentials' ), true ) );
		} else if ( slug === 'review' ) {
			controls.push( text( __( 'Quote', 'brother-tours-content-studio' ), attributes.quote, set( 'quote' ), true ), text( __( 'Author', 'brother-tours-content-studio' ), attributes.author, set( 'author' ) ), text( __( 'Trip reference', 'brother-tours-content-studio' ), attributes.tripReference, set( 'tripReference' ) ), text( __( 'Rating (only use with verified review evidence)', 'brother-tours-content-studio' ), attributes.rating, set( 'rating' ) ) );
		} else if ( slug === 'itinerary' ) {
			controls.push( text( __( 'Heading', 'brother-tours-content-studio' ), attributes.heading, set( 'heading' ) ), repeater( attributes.items, ( items ) => setAttributes( { items } ), [ { key: 'title', label: __( 'Day title', 'brother-tours-content-studio' ) }, { key: 'body', label: __( 'Description', 'brother-tours-content-studio' ), multiline: true }, { key: 'meals', label: __( 'Meals', 'brother-tours-content-studio' ) }, { key: 'accommodation', label: __( 'Accommodation', 'brother-tours-content-studio' ) } ], { title: '', body: '', meals: '', accommodation: '' } ) );
		} else if ( slug === 'included-excluded' ) {
			controls.push( text( __( 'Included heading', 'brother-tours-content-studio' ), attributes.includedHeading, set( 'includedHeading' ) ), text( __( 'Included items (one per line)', 'brother-tours-content-studio' ), lines( attributes.included ), ( value ) => setAttributes( { included: arrayFromLines( value ) } ), true ), text( __( 'Excluded heading', 'brother-tours-content-studio' ), attributes.excludedHeading, set( 'excludedHeading' ) ), text( __( 'Excluded items (one per line)', 'brother-tours-content-studio' ), lines( attributes.excluded ), ( value ) => setAttributes( { excluded: arrayFromLines( value ) } ), true ) );
		} else if ( slug === 'faq' ) {
			controls.push( text( __( 'Heading', 'brother-tours-content-studio' ), attributes.heading, set( 'heading' ) ), repeater( attributes.items, ( items ) => setAttributes( { items } ), [ { key: 'question', label: __( 'Question', 'brother-tours-content-studio' ) }, { key: 'answer', label: __( 'Answer', 'brother-tours-content-studio' ), multiline: true } ], { question: '', answer: '' } ) );
		} else if ( slug === 'gallery-story' ) {
			controls.push( text( __( 'Heading', 'brother-tours-content-studio' ), attributes.heading, set( 'heading' ) ), galleryControl( attributes, setAttributes ), el( Notice, { status: 'info', isDismissible: false }, __( 'Alt text must be completed on every non-decorative image before publishing.', 'brother-tours-content-studio' ) ) );
		} else if ( slug === 'cta-inquiry' ) {
			controls.push( text( __( 'Heading', 'brother-tours-content-studio' ), attributes.heading, set( 'heading' ) ), text( __( 'Body', 'brother-tours-content-studio' ), attributes.body, set( 'body' ), true ), text( __( 'Primary label', 'brother-tours-content-studio' ), attributes.primaryText, set( 'primaryText' ) ), text( __( 'Primary URL', 'brother-tours-content-studio' ), attributes.primaryUrl, set( 'primaryUrl' ) ), text( __( 'WhatsApp label', 'brother-tours-content-studio' ), attributes.whatsappText, set( 'whatsappText' ) ), text( __( 'WhatsApp URL', 'brother-tours-content-studio' ), attributes.whatsappUrl, set( 'whatsappUrl' ) ) );
		} else if ( slug === 'newsletter' ) {
			controls.push( text( __( 'Heading', 'brother-tours-content-studio' ), attributes.heading, set( 'heading' ) ), text( __( 'Body', 'brother-tours-content-studio' ), attributes.body, set( 'body' ), true ) );
		}
		return controls.filter( Boolean );
	}

	Object.keys( definitions ).forEach( ( slug ) => {
		const definition = definitions[ slug ];
		registerBlockType( 'brother-tours/' + slug, {
			title: definition.title,
			icon: 'layout',
			category: 'brother-tours',
			attributes: definition.attributes,
			supports: { align: [ 'wide', 'full' ], anchor: true, spacing: { margin: true, padding: true } },
			edit: ( { attributes, setAttributes } ) => el( Fragment, {},
				el( InspectorControls, {}, el( PanelBody, { title: __( 'Content Studio controls', 'brother-tours-content-studio' ), initialOpen: true }, controlsFor( slug, attributes, setAttributes ) ) ),
				el( ServerSideRender, { block: 'brother-tours/' + slug, attributes } )
			),
			save: () => null
		} );
	} );
}( window.wp ) );
