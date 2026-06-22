export default {
  name: 'solutionPage',
  title: 'Solution & Product Pages',
  type: 'document',
  fields: [
    // 1. 基础页面信息
    {
      name: 'slug',
      title: 'URL Slug',
      type: 'slug',
      options: { source: 'title', maxLength: 96 }
    },
    {
      name: 'pageTitle',
      title: 'Page Title (H1)',
      type: 'string',
      initialValue: 'Inconel 718 Forging'
    },
    {
      name: 'badge',
      title: 'Top Badge',
      type: 'string',
      initialValue: 'High-performance alloy forging'
    },
    {
      name: 'description',
      title: 'Top Subtitle / Description',
      type: 'text'
    },

    // 2. Solution Snapshot (侧边栏快照)
    {
      name: 'snapshot',
      title: 'Solution Snapshot',
      type: 'array',
      of: [
        {
          type: 'object',
          fields: [
            { name: 'term', title: 'Term (e.g., Material)', type: 'string' },
            { name: 'description', title: 'Description', type: 'string' }
          ]
        }
      ]
    },

    // 3. Decision Factors (决策因子)
    {
      name: 'decisionFactors',
      title: 'Common Decision Factors',
      type: 'array',
      of: [{ type: 'string' }]
    },

    // 4. Mechanical Properties (机械性能表格)
    {
      name: 'properties',
      title: 'Mechanical Properties Table',
      type: 'array',
      of: [
        {
          type: 'object',
          fields: [
            { name: 'property', title: 'Property Name', type: 'string' },
            { name: 'metric', title: 'Metric Value (e.g., ≥ 1,275 MPa)', type: 'string' },
            { name: 'imperial', title: 'Imperial Value (e.g., ≥ 185 ksi)', type: 'string' }
          ]
        }
      ]
    },

    // 5. Forging Routes (工艺路线)
    {
      name: 'routes',
      title: 'Forging Routes',
      type: 'array',
      of: [
        {
          type: 'object',
          fields: [
            { name: 'title', title: 'Route Title', type: 'string' },
            { name: 'text', title: 'Route Description', type: 'text' }
          ]
        }
      ]
    },

    // 6. FAQs (问答生态 - GEO 核心)
    {
      name: 'faqs',
      title: 'Technical FAQs',
      type: 'array',
      of: [
        {
          type: 'object',
          fields: [
            { name: 'question', title: 'Question', type: 'string' },
            { name: 'answer', title: 'Answer', type: 'text' }
          ]
        }
      ]
    },

    // 7. SEO 元数据
    {
      name: 'seoDescription',
      title: 'SEO Meta Description',
      type: 'text'
    }
  ]
}