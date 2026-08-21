import { readFile, readdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const ROOT = process.cwd();
const DIST = path.join(ROOT, 'dist');
const SITE_ORIGIN = 'https://www.forcebeyond.com';
const ENTITY_IDS = {
  organization: `${SITE_ORIGIN}/#organization`,
  website: `${SITE_ORIGIN}/#website`,
  place: `${SITE_ORIGIN}/#place`,
};
const OLD_ENTITY_ID_RE = /https:\/\/www\.forcebeyond\.com#(?:organization|website|place)\b/gi;
const PAGE_ENTITY_ID_RE = /^https:\/\/www\.forcebeyond\.com\/(.+?)(\/?#(?:webpage|service|faq))$/i;
const CLAIM_RE = /\b(?:guarantee|guaranteed|zero downtime|zero contamination|mandatory for all|every part|all facilities|unmatched|most stringent|fully compliant|total quality control)\b/gi;

const errors = [];
const warnings = [];

function report(list, level, page, message) {
  list.push(`${level} ${page}: ${message}`);
}

function decodeHtml(value) {
  return value
    .replace(/&quot;/gi, '"')
    .replace(/&#39;|&apos;/gi, "'")
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&#(\d+);/g, (_, code) => String.fromCodePoint(Number(code)))
    .replace(/&#x([0-9a-f]+);/gi, (_, code) => String.fromCodePoint(Number.parseInt(code, 16)));
}

function attrValue(tag, name) {
  const match = tag.match(new RegExp(`\\b${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i'));
  return match ? decodeHtml(match[1] ?? match[2] ?? match[3] ?? '') : undefined;
}

function tags(html, name) {
  return html.match(new RegExp(`<${name}\\b[^>]*>`, 'gi')) ?? [];
}

function normalizePathname(pathname) {
  let value = pathname || '/';
  if (!value.startsWith('/')) value = `/${value}`;
  if (!path.extname(value) && !value.endsWith('/')) value += '/';
  return value;
}

function builtUrlForFile(file) {
  const relative = path.relative(DIST, file).split(path.sep).join('/');
  if (relative === 'index.html') return `${SITE_ORIGIN}/`;
  if (relative.endsWith('/index.html')) return `${SITE_ORIGIN}/${relative.slice(0, -'index.html'.length)}`;
  return `${SITE_ORIGIN}/${relative}`;
}

function isNormalContentPage(relative, html) {
  return relative !== '__forms-fallback.html'
    && !relative.startsWith('_astro/')
    && !/<meta\b[^>]*\bhttp-equiv\s*=\s*["']?refresh\b/i.test(html);
}

function jsonLdBlocks(html) {
  const blocks = [];
  const re = /<script\b([^>]*)>([\s\S]*?)<\/script\s*>/gi;
  let match;
  while ((match = re.exec(html))) {
    const type = attrValue(`<script ${match[1]}>`, 'type');
    if (type?.toLowerCase() === 'application/ld+json') blocks.push(match[2].trim());
  }
  return blocks;
}

function walkJson(value, visit, trail = '$') {
  if (!value || typeof value !== 'object') return;
  visit(value, trail);
  if (Array.isArray(value)) {
    value.forEach((item, index) => walkJson(item, visit, `${trail}[${index}]`));
  } else {
    for (const [key, item] of Object.entries(value)) walkJson(item, visit, `${trail}.${key}`);
  }
}

function typesOf(node) {
  const value = node?.['@type'];
  return new Set((Array.isArray(value) ? value : [value]).filter(Boolean));
}

async function htmlFiles(directory) {
  const found = [];
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const target = path.join(directory, entry.name);
    if (entry.isDirectory()) found.push(...await htmlFiles(target));
    else if (entry.isFile() && entry.name.endsWith('.html')) found.push(target);
  }
  return found.sort();
}

async function sitemapUrls() {
  const indexPath = path.join(DIST, 'sitemap-index.xml');
  const directPath = path.join(DIST, 'sitemap.xml');
  const files = [];
  if (existsSync(indexPath)) {
    const index = await readFile(indexPath, 'utf8');
    for (const match of index.matchAll(/<loc>([\s\S]*?)<\/loc>/gi)) {
      const url = decodeHtml(match[1].trim());
      const parsed = new URL(url);
      if (parsed.origin === SITE_ORIGIN) files.push(path.join(DIST, parsed.pathname.replace(/^\//, '')));
    }
  } else if (existsSync(directPath)) {
    files.push(directPath);
  }

  const urls = new Set();
  for (const file of files) {
    if (!existsSync(file)) {
      report(errors, 'ERROR', 'sitemap', `referenced sitemap file is missing: ${path.relative(DIST, file)}`);
      continue;
    }
    const xml = await readFile(file, 'utf8');
    for (const match of xml.matchAll(/<loc>([\s\S]*?)<\/loc>/gi)) urls.add(decodeHtml(match[1].trim()));
  }
  return urls;
}

if (!existsSync(DIST)) {
  console.log('SEO/GEO Verification');
  console.log('Pages checked: 0');
  console.log('Errors: 1');
  console.log('Warnings: 0\n');
  console.log('ERROR dist: build output does not exist; run npm run build first');
  process.exit(1);
}

const files = await htmlFiles(DIST);
const pages = new Map();

for (const file of files) {
  const html = await readFile(file, 'utf8');
  const relative = path.relative(DIST, file).split(path.sep).join('/');
  const page = `/${relative}`;
  const expectedUrl = builtUrlForFile(file);
  const titleCount = (html.match(/<title\b[^>]*>[\s\S]*?<\/title\s*>/gi) ?? []).length;
  const canonicalTags = tags(html, 'link').filter((tag) => (attrValue(tag, 'rel') ?? '').toLowerCase().split(/\s+/).includes('canonical'));
  const h1Count = (html.match(/<h1\b[^>]*>[\s\S]*?<\/h1\s*>/gi) ?? []).length;
  const metaTags = tags(html, 'meta');
  const descriptions = metaTags.filter((tag) => (attrValue(tag, 'name') ?? '').toLowerCase() === 'description');
  const robots = metaTags
    .filter((tag) => (attrValue(tag, 'name') ?? '').toLowerCase() === 'robots')
    .map((tag) => attrValue(tag, 'content') ?? '')
    .join(',');
  const noindex = /\bnoindex\b/i.test(robots);
  const redirect = /<meta\b[^>]*\bhttp-equiv\s*=\s*["']?refresh\b/i.test(html);
  const utility = relative === '__forms-fallback.html';
  const normal = isNormalContentPage(relative, html);
  const canonicalDeferred = relative === 'contact-us/index.html' || relative === 'request-for-quote/index.html';
  const canonicalMismatchExempt = relative === '404-page/index.html';

  if (!utility && titleCount !== 1) report(errors, 'ERROR', page, `expected exactly one <title>; found ${titleCount}`);
  if (!utility && canonicalTags.length !== 1 && !(canonicalDeferred && canonicalTags.length === 0)) {
    report(errors, 'ERROR', page, `expected exactly one canonical link; found ${canonicalTags.length}`);
  }
  if (normal && h1Count !== 1) report(errors, 'ERROR', page, `expected exactly one H1 on a content page; found ${h1Count}`);
  if (!utility && (descriptions.length !== 1 || !(attrValue(descriptions[0] ?? '', 'content') ?? '').trim())) {
    report(errors, 'ERROR', page, `expected one non-empty meta description; found ${descriptions.length}`);
  }

  let canonicalUrl;
  if (canonicalTags.length === 1) {
    const href = attrValue(canonicalTags[0], 'href');
    try {
      canonicalUrl = new URL(href).toString();
      if (!canonicalMismatchExempt && canonicalUrl !== expectedUrl) report(errors, 'ERROR', page, `canonical ${canonicalUrl} does not match built URL ${expectedUrl}`);
    } catch {
      report(errors, 'ERROR', page, `canonical URL is invalid: ${href ?? '(missing href)'}`);
    }
  }

  const documents = [];
  for (const [index, block] of jsonLdBlocks(html).entries()) {
    try {
      documents.push(JSON.parse(block));
    } catch (error) {
      report(errors, 'ERROR', page, `JSON-LD block ${index + 1} is invalid JSON: ${error.message}`);
    }
  }

  const organizationDefinitions = [];
  const organizationFacts = { name: new Set(), legalName: new Set(), foundingDate: new Set() };
  for (const document of documents) {
    walkJson(document, (node, trail) => {
      const id = node['@id'];
      if (typeof id === 'string') {
        const oldMatch = id.match(OLD_ENTITY_ID_RE);
        if (oldMatch) report(errors, 'ERROR', page, `legacy entity ID at ${trail}: ${oldMatch[0]}`);
        const pageId = id.match(PAGE_ENTITY_ID_RE);
        if (pageId && !pageId[2].startsWith('/#')) report(errors, 'ERROR', page, `page entity ID needs a trailing slash before its fragment: ${id}`);
      }

      const nodeTypes = typesOf(node);
      if (nodeTypes.has('Organization')) {
        const forceBeyond = id === ENTITY_IDS.organization || /forcebeyond/i.test(String(node.name ?? node.legalName ?? ''));
        if (forceBeyond) {
          organizationDefinitions.push({ node, trail });
          for (const key of Object.keys(organizationFacts)) if (node[key] != null) organizationFacts[key].add(String(node[key]));
          if (node.name === 'www.forcebeyond.com') report(errors, 'ERROR', page, `Organization primary name is www.forcebeyond.com at ${trail}`);
        }
      }
      if (nodeTypes.has('WebSite') && node.name === 'www.forcebeyond.com') {
        report(errors, 'ERROR', page, `WebSite primary name is www.forcebeyond.com at ${trail}`);
      }
      if (nodeTypes.has('Service') && node.provider) {
        const provider = node.provider;
        const forceBeyondProvider = provider?.['@id']?.includes('forcebeyond.com') || /forcebeyond/i.test(String(provider?.name ?? ''));
        if (forceBeyondProvider && provider['@id'] !== ENTITY_IDS.organization) {
          report(errors, 'ERROR', page, `ForceBeyond Service provider should reference ${ENTITY_IDS.organization} at ${trail}.provider`);
        }
      }
    });
  }

  if (organizationDefinitions.length > 1) {
    report(errors, 'ERROR', page, `contains ${organizationDefinitions.length} ForceBeyond Organization definitions; expected at most one`);
  }
  for (const [key, values] of Object.entries(organizationFacts)) {
    if (values.size > 1) report(errors, 'ERROR', page, `conflicting ForceBeyond Organization ${key} values: ${[...values].join(' | ')}`);
  }

  const visibleText = decodeHtml(html
    .replace(/<script\b[^>]*>[\s\S]*?<\/script\s*>/gi, ' ')
    .replace(/<style\b[^>]*>[\s\S]*?<\/style\s*>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' '));
  const claimMatches = [...new Set([...visibleText.matchAll(CLAIM_RE)].map((match) => match[0].toLowerCase()))];
  if (claimMatches.length) report(warnings, 'WARN', page, `potentially absolute claim language: ${claimMatches.join(', ')}`);

  pages.set(new URL(expectedUrl).pathname, { page, noindex, redirect });
}

const sitemap = await sitemapUrls();
for (const url of sitemap) {
  let parsed;
  try {
    parsed = new URL(url);
  } catch {
    report(errors, 'ERROR', 'sitemap', `invalid URL: ${url}`);
    continue;
  }
  if (parsed.origin !== SITE_ORIGIN) continue;
  const pathname = normalizePathname(parsed.pathname);
  const built = pages.get(pathname);
  if (!built) report(errors, 'ERROR', 'sitemap', `${url} has no corresponding built HTML page`);
  else if (built.redirect) report(errors, 'ERROR', 'sitemap', `${url} corresponds to a redirect page (${built.page})`);
  else if (built.noindex) report(errors, 'ERROR', 'sitemap', `${url} is noindex (${built.page})`);
}

console.log('SEO/GEO Verification');
console.log(`Pages checked: ${files.length}`);
console.log(`Errors: ${errors.length}`);
console.log(`Warnings: ${warnings.length}`);
if (errors.length || warnings.length) console.log('');
for (const message of errors) console.log(message);
for (const message of warnings) console.log(message);

process.exit(errors.length ? 1 : 0);
