// @ts-check
import { defineConfig } from 'astro/config';
import react from '@astrojs/react';
import sanity from '@sanity/astro';
import sitemap from '@astrojs/sitemap';

import tailwindcss from '@tailwindcss/vite';
import { loadEnv } from 'vite';

const env = loadEnv(
    process.env.NODE_ENV ?? 'development',
    process.cwd(),
    ''
);

const projectId = env.PUBLIC_SANITY_PROJECT_ID;
const dataset = env.PUBLIC_SANITY_DATASET;

export default defineConfig({
    site: 'https://www.forcebeyond.store',
    base: '/',
    output: 'static',

    integrations: [
        sanity({
            projectId: projectId || '00000000',
            dataset: dataset || 'production',
            useCdn: true,
        }),
        react(),
        sitemap(),
    ],

    vite: {
        plugins: [tailwindcss()],
    },
});