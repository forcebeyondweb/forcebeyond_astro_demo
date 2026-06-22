import { defineField, defineType } from 'sanity'

export const quoteType = defineType({
  name: 'quotePage',
  title: 'Quote Page',
  type: 'document',
  fields: [
    defineField({ name: 'badge', title: 'Top Small Badge', type: 'string', initialValue: 'Start a project' }),
    defineField({ name: 'title', title: 'Main Heading (H1)', type: 'string', initialValue: 'Ready to Start A Conversation? Request for Quote?' }),
    defineField({ name: 'description', title: 'Introductory Paragraph', type: 'text', initialValue: 'Tell our team about your component requirements, manufacturing needs, and project timeline. We will review your information and follow up with the right next step.' }),
    defineField({ name: 'seoDescription', title: 'SEO Page Description', type: 'text' }),
  ],
})