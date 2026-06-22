// @ts-check
import { defineConfig } from 'astro/config';
import netlify from '@astrojs/netlify';
import react from '@astrojs/react';
import sanity from '@sanity/astro';

import tailwindcss from '@tailwindcss/vite';
import { loadEnv } from 'vite';

const env = loadEnv(process.env.NODE_ENV ?? 'development', process.cwd(), '');
const projectId = env.PUBLIC_SANITY_PROJECT_ID;
const dataset = env.PUBLIC_SANITY_DATASET;

// https://astro.build/config
export default defineConfig({
	adapter: netlify(),
	integrations: [
		sanity({
			projectId: projectId || '00000000',
			dataset: dataset || 'production',
			useCdn: false,
			studioBasePath: '/admin'
		}),
		react()
	],
  vite: {
    plugins: [tailwindcss()]
  }
});
