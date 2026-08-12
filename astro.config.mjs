// @ts-check
import { defineConfig } from 'astro/config';
import react from '@astrojs/react';
import sanity from '@sanity/astro';
import sitemap from '@astrojs/sitemap';
import { pageDates } from './src/data/page-dates.js';

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
    site: 'https://www.forcebeyond.com',
    base: '/',
    output: 'static',

    integrations: [
        sanity({
            projectId: projectId || '00000000',
            dataset: dataset || 'production',
            useCdn: true,
        }),

        react(),

        sitemap({
            serialize(item) {
                const pathname = new URL(item.url).pathname;
                const dates = pageDates[pathname];

                if (dates?.modified) {
                    item.lastmod = dates.modified;
                }

                return item;
            },
        }),
    ],

    vite: {
        plugins: [tailwindcss()],
    },
});