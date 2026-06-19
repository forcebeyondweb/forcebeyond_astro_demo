import { defineConfig } from 'sanity';
import { structureTool } from 'sanity/structure';
import { loadEnv } from 'vite';
import { schemaTypes } from './sanity/schemaTypes';

const env = loadEnv(process.env.NODE_ENV ?? 'development', process.cwd(), '');

export default defineConfig({
	name: 'forcebeyond',
	title: 'ForceBeyond Content',
	projectId: env.PUBLIC_SANITY_PROJECT_ID || '00000000',
	dataset: env.PUBLIC_SANITY_DATASET || 'production',
	plugins: [structureTool()],
	schema: { types: schemaTypes }
});
