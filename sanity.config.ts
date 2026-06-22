import { defineConfig } from 'sanity';
import { structureTool } from 'sanity/structure';
import { schemaTypes } from './sanity/schemaTypes';

export default defineConfig({
	name: 'forcebeyond',
	title: 'ForceBeyond Content',
	basePath: '/admin', 
	projectId: 'hxb9nbp2',  // 直接写死字符串，绝对不会出错
	dataset: 'production',   // 直接写死字符串
	plugins: [structureTool()],
	schema: { 
		types: schemaTypes 
	}
});