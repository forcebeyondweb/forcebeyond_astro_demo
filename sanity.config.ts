import { defineConfig } from 'sanity';
import { structureTool } from 'sanity/structure';
import { schemaTypes } from './sanity/schemaTypes';

// 在 Astro/Vite 前端打包环境中，直接使用符合标准的安全读取方式
const projectId = import.meta.env.PUBLIC_SANITY_PROJECT_ID || 'hxb9nbp2';
const dataset = import.meta.env.PUBLIC_SANITY_DATASET || 'production';

export default defineConfig({
	name: 'forcebeyond',
	title: 'ForceBeyond Content',
	basePath: '/admin', // 确保管理后台的路由根路径正确映射
	projectId: projectId,
	dataset: dataset,
	plugins: [structureTool()],
	schema: { 
		types: schemaTypes 
	}
});
