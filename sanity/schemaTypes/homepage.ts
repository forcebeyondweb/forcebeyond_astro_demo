import { defineField, defineType } from 'sanity';

const titledText = (name: string, title: string) =>
	defineField({ name, title, type: 'string', validation: (rule) => rule.required() });

export const homepage = defineType({
	name: 'homepage',
	title: 'Homepage',
	type: 'document',
	fields: [
		titledText('heroEyebrow', 'Hero eyebrow'),
		defineField({ name: 'heroHeadline', title: 'Hero headline', type: 'string', validation: (rule) => rule.required() }),
		defineField({ name: 'heroDescription', title: 'Hero description', type: 'text', rows: 4 }),
		titledText('primaryCtaLabel', 'Primary CTA label'),
		titledText('primaryCtaHref', 'Primary CTA link'),
		titledText('secondaryCtaLabel', 'Secondary CTA label'),
		titledText('secondaryCtaHref', 'Secondary CTA link'),
		defineField({ name: 'capabilitiesTitle', title: 'Capabilities section title', type: 'string' }),
		defineField({ name: 'capabilitiesDescription', title: 'Capabilities section description', type: 'text', rows: 3 }),
		defineField({
			name: 'capabilities', title: 'Capabilities', type: 'array', of: [{ type: 'object', fields: [
				defineField({ name: 'title', title: 'Title', type: 'string', validation: (rule) => rule.required() }),
				defineField({ name: 'description', title: 'Description', type: 'text', rows: 3 })
			] }]
		}),
		defineField({ name: 'industriesTitle', title: 'Industries section title', type: 'string' }),
		defineField({ name: 'industriesDescription', title: 'Industries section description', type: 'text', rows: 3 }),
		defineField({ name: 'industries', title: 'Industries', type: 'array', of: [{ type: 'string' }] }),
		defineField({ name: 'valueTitle', title: 'Value proposition title', type: 'string' }),
		defineField({ name: 'valueDescription', title: 'Value proposition description', type: 'text', rows: 5 }),
		defineField({ name: 'valuePoints', title: 'Value proposition points', type: 'array', of: [{ type: 'string' }] }),
		defineField({ name: 'qualityTitle', title: 'Quality section title', type: 'string' }),
		defineField({ name: 'qualityDescription', title: 'Quality section description', type: 'text', rows: 5 }),
		defineField({ name: 'qualityClosing', title: 'Quality section closing text', type: 'text', rows: 3 }),
		defineField({
			name: 'qualityAreas', title: 'Quality focus areas', type: 'array', of: [{ type: 'object', fields: [
				defineField({ name: 'title', title: 'Title', type: 'string', validation: (rule) => rule.required() }),
				defineField({ name: 'description', title: 'Description', type: 'text', rows: 3 })
			] }]
		}),
		defineField({ name: 'faqTitle', title: 'FAQ section title', type: 'string' }),
		defineField({ name: 'faqDescription', title: 'FAQ section description', type: 'text', rows: 3 }),
		defineField({
			name: 'faqs', title: 'Frequently asked questions', type: 'array', of: [{ type: 'object', fields: [
				defineField({ name: 'question', title: 'Question', type: 'string', validation: (rule) => rule.required() }),
				defineField({ name: 'answer', title: 'Answer', type: 'text', rows: 5, validation: (rule) => rule.required() })
			] }]
		})
	]
});
