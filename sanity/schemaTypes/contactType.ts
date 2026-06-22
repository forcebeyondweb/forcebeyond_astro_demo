import { defineField, defineType } from 'sanity'

export const contactType = defineType({
  name: 'contactPage',
  title: 'Contact Page',
  type: 'document',
  fields: [
    // 页面头部文案
    defineField({ name: 'badge', title: 'Top Small Badge', type: 'string', initialValue: 'Corporate access' }),
    defineField({ name: 'title', title: 'Main Heading (H1)', type: 'string', initialValue: 'Contact ForceBeyond' }),
    defineField({ name: 'seoDescription', title: 'SEO Page Description', type: 'text' }),
    
    // 侧边栏物理信息（这些改变会自动同步到页面和底部的 JSON-LD 结构化数据中！）
    defineField({ name: 'companyName', title: 'Company Name', type: 'string', initialValue: 'FORCEBEYOND' }),
    defineField({ name: 'streetAddress', title: 'Street Address', type: 'string', initialValue: '261 Quigley Blvd, Suite 18' }),
    defineField({ name: 'locality', title: 'City', type: 'string', initialValue: 'New Castle' }),
    defineField({ name: 'region', title: 'State/Region', type: 'string', initialValue: 'DE' }),
    defineField({ name: 'postalCode', title: 'Postal Code', type: 'string', initialValue: '19720' }),
    defineField({ name: 'phone', title: 'Phone Number', type: 'string', initialValue: '(302) 995 6588' }),
    defineField({ name: 'fax', title: 'Fax Number', type: 'string', initialValue: '(302) 355 1166' }),
    defineField({ name: 'email', title: 'Corporate Email', type: 'string', initialValue: 'contact@forcebeyond.com' }),
    
    // 表单区文案
    defineField({ name: 'formTitle', title: 'Form Section Title', type: 'string', initialValue: 'B2B Communications' }),
    defineField({ name: 'formDescription', title: 'Form Subtitle', type: 'text', initialValue: 'We would love to hear from you, please drop us a line and we will get back with you very soon.' }),
  ],
})