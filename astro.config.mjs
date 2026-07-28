// @ts-check
import { defineConfig } from 'astro/config';
import react from '@astrojs/react';
import sanity from '@sanity/astro';

import tailwindcss from '@tailwindcss/vite';
import { loadEnv } from 'vite';

const env = loadEnv(process.env.NODE_ENV ?? 'development', process.cwd(), '');
const projectId = env.PUBLIC_SANITY_PROJECT_ID;
const dataset = env.PUBLIC_SANITY_DATASET;

// https://astro.build/config
export default defineConfig({
    // 1. Set base path to root
    base: '/',

    // 2. 核心关键：强制指定输出为纯静态 HTML 文件（SSG 模式）
    output: 'static',

    integrations: [
        sanity({
            projectId: projectId || '00000000',
            dataset: dataset || 'production',
            useCdn: true,
        }),
        react()
    ],
    vite: {
        plugins: [tailwindcss()]
    }
});