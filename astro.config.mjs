// @ts-check
import { defineConfig } from 'astro/config';
// 注释掉 Netlify 适配器，因为 HostGator 无法运行 Netlify 的后端 Serverless 函数
// import netlify from '@astrojs/netlify';
import react from '@astrojs/react';
import sanity from '@sanity/astro';

import tailwindcss from '@tailwindcss/vite';
import { loadEnv } from 'vite';

const env = loadEnv(process.env.NODE_ENV ?? 'development', process.cwd(), '');
const projectId = env.PUBLIC_SANITY_PROJECT_ID;
const dataset = env.PUBLIC_SANITY_DATASET;

// https://astro.build/config
export default defineConfig({
    // 1. 核心关键：设置 base 路径为你在 HostGator 建的子文件夹名
    base: '/oscar',

    // 2. 核心关键：强制指定输出为纯静态 HTML 文件（SSG 模式）
    output: 'static',

    // 3. 移除之前的 adapter: netlify()，让 Astro 回归标准的纯静态编译
    // adapter: netlify(),

    integrations: [
        sanity({
            projectId: projectId || '00000000',
            dataset: dataset || 'production',
            // 4. 迁移建议：既然变成纯静态了，建议把 useCdn 改为 true。
            // 这样在本地 build 编译的一瞬间，会通过 Sanity 顶级的全球 CDN 高速抓取数据
            useCdn: true,
        }),
        react()
    ],
    vite: {
        plugins: [tailwindcss()]
    }
});
