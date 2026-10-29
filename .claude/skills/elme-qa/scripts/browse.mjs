#!/usr/bin/env node
// Headless browser pass over the new Elme storefront (and the admin) for an Elme QA run.
//
//   node .claude/skills/elme-qa/scripts/browse.mjs [options] <target> [<target> ...]
//
// Targets
//   /path                 the new site, https://elme-hyva.local
//   old:/path             the live old site (https://www.elmeliving.com) at the same viewports, for a
//                         side-by-side parity check of a ported page. Read-only: never submit forms there.
//   both:/path            new and old
//   admin:<front>/<controller>/<action>[/param/value...]
//                          admin page, e.g. admin:catalog/product/index, admin:cms/block/edit/block_id/3.
//                          Needs --admin.
//
// Options
//   --viewports 375,1440  default: 375,1440 (storefront); add 768 (tablet), 1025 (desktop edge) and
//                         1920 when the change is layout-sensitive; admin pages always run at 1440
//   --out DIR             screenshots + report.json (default /tmp/elme-qa/<branch>-<timestamp>)
//   --full                full-page screenshots instead of the viewport
//   --steps FILE.mjs      after load, runs `export default async (page, ctx) => result` per target;
//                         ctx = { brand, host, path, viewport, shot(name) }; result lands in report.json
//   --admin               log in to the admin first (admin / admin123, local install)
//   --reduced-motion      emulate prefers-reduced-motion: reduce
//
// Every target gets a fresh browser context, so nothing is served from a stale cache and no cookie or
// grid bookmark leaks from one target into the next.

import { execSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const HOSTS = { new: 'https://elme-hyva.local', old: 'https://www.elmeliving.com' };
const ADMIN = { host: HOSTS.new, user: 'admin', pass: 'admin123' };

// Local-only or third-party noise seen on every page of these sites; reported as a count, not as findings.
const NOISE = [
    /violates the following (report-only )?Content Security Policy directive/,
    /RefererNotAllowedMapError|Google Maps JavaScript API|didn't load Google Maps correctly/,
    /No Meta pixel is configured/,
    /klaviyo|zendesk|zdassets|googletagmanager|triplewhale/i,
];

function loadPlaywright() {
    const path = process.env.PLAYWRIGHT_CORE
        || join(execSync('npm root -g').toString().trim(), '@playwright/mcp/node_modules/playwright-core');
    return createRequire(import.meta.url)(path);
}

function parseArgs(argv) {
    const opts = { viewports: [375, 1440], targets: [], full: false, admin: false, reducedMotion: false };
    for (let i = 0; i < argv.length; i += 1) {
        const arg = argv[i];
        if (arg === '--viewports') opts.viewports = argv[++i].split(',').map(Number);
        else if (arg === '--out') opts.out = resolve(argv[++i]);
        else if (arg === '--steps') opts.steps = resolve(argv[++i]);
        else if (arg === '--full') opts.full = true;
        else if (arg === '--admin') opts.admin = true;
        else if (arg === '--reduced-motion') opts.reducedMotion = true;
        else opts.targets.push(arg);
    }
    if (!opts.targets.length) {
        console.error('usage: browse.mjs [options] <target> ...   (see the header of this file)');
        process.exit(2);
    }
    if (!opts.out) {
        const branch = execSync('git branch --show-current').toString().trim().replace(/[^\w.-]+/g, '-');
        const stamp = new Date().toISOString().replace(/[-:]/g, '').slice(0, 15);
        opts.out = `/tmp/elme-qa/${branch || 'main'}-${stamp}`;
    }
    return opts;
}

function expand(opts) {
    const jobs = [];
    for (const target of opts.targets) {
        const admin = target.match(/^admin:(.+)$/);
        if (admin) {
            jobs.push({ brand: 'admin', host: ADMIN.host, path: admin[1], viewport: 1440, admin: true });
            continue;
        }
        const scoped = target.match(/^(old|both):(.+)$/);
        const brands = !scoped ? ['new'] : scoped[1] === 'both' ? ['new', 'old'] : ['old'];
        const path = scoped ? scoped[2] : target;
        for (const brand of brands) {
            for (const viewport of opts.viewports) {
                jobs.push({ brand, host: HOSTS[brand], path, viewport, admin: false });
            }
        }
    }
    return jobs;
}

async function adminLogin(context) {
    const page = await context.newPage();
    await page.goto(`${ADMIN.host}/admin`, { waitUntil: 'load' });
    if (await page.$('#username')) {
        await page.fill('#username', ADMIN.user);
        await page.fill('#login', ADMIN.pass);
        await page.click('.action-login');
        await page.waitForLoadState('load');
    }
    const body = await page.content();
    if (/tfa|two-factor|2fa/i.test(page.url()) || /Google Authenticator|Two-Factor/i.test(body)) {
        throw new Error('2FA screen after login. Run: bin/clinotty bash -c "bin/magento '
            + 'module:disable Magento_TwoFactorAuth Magento_AdminAdobeImsTwoFactorAuth"');
    }
    if (!/dashboard/.test(page.url())) {
        throw new Error(`admin login did not reach the dashboard (at ${page.url()})`);
    }
    return page;
}

// Admin URLs carry a secret key per front/controller/action; the key does not depend on the params. Find
// any link for the same action on the dashboard or on that controller's index page and reuse its key.
async function adminUrl(page, route) {
    const [front, controller, action = 'index', ...params] = route.split('/');
    const needle = `/${front}/${controller}/${action}/`;
    const findKey = async () => page.evaluate((n) => {
        const html = document.documentElement.innerHTML
            .replace(/\\\/|\\x2F|\\u002F/gi, '/');
        const m = html.match(new RegExp(`${n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?:[^"'\\s]*?/)?key/([0-9a-f]{20,})`));
        return m ? m[1] : null;
    }, needle);
    await page.goto(`${ADMIN.host}/admin/admin/dashboard/`, { waitUntil: 'load' });
    let key = await findKey();
    if (!key && action !== 'index') {
        const indexKey = await page.evaluate((n) => {
            const html = document.documentElement.innerHTML.replace(/\\\//g, '/');
            const m = html.match(new RegExp(`${n}(?:[^"'\\s]*?/)?key/([0-9a-f]{20,})`));
            return m ? m[1] : null;
        }, `/${front}/${controller}/index/`);
        if (indexKey) {
            await page.goto(`${ADMIN.host}/admin/${front}/${controller}/index/key/${indexKey}/`, { waitUntil: 'load' });
            await page.waitForTimeout(2500); // grid rows arrive over XHR
            key = await findKey();
        }
    }
    if (!key && action === 'new') {
        // The grid's "Add New" button is a JS widget: its URL is in no attribute or script on the page.
        await page.click('#add');
        await page.waitForLoadState('load');
        return page.url();
    }
    if (!key) throw new Error(`no admin link found for ${needle} (is it in the admin menu or its index grid?)`);
    const tail = params.length ? `${params.join('/')}/` : '';
    return `${ADMIN.host}/admin${needle}${tail}key/${key}/`;
}

// How far a visitor can scroll sideways. Neither scrollWidth nor window.scrollTo() answers that: the old Elme
// list page has a decorative 999999px ::before that inflates scrollWidth, and scrollTo() moves even an
// overflow:hidden viewport. The viewport takes html's overflow-x, or body's when html's is visible;
// hidden/clip there means no user scroll whatever the content width.
function measureOverflow(page) {
    return page.evaluate(() => {
        const html = getComputedStyle(document.documentElement).overflowX;
        const viewport = html !== 'visible' ? html : getComputedStyle(document.body).overflowX;
        if (viewport === 'hidden' || viewport === 'clip') return 0;
        return Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth);
    });
}

async function visit(browser, job, opts, adminState) {
    const context = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: { width: job.viewport, height: 900 },
        reducedMotion: opts.reducedMotion ? 'reduce' : 'no-preference',
        storageState: job.admin ? adminState : undefined,
    });
    const page = await context.newPage();
    const own = [];
    let noise = 0;
    const failed = [];
    const origin = new URL(job.host).host;

    page.on('console', (msg) => {
        if (msg.type() !== 'error') return;
        const text = msg.text();
        const src = msg.location()?.url || '';
        if (NOISE.some((re) => re.test(text)) || (src && !src.includes(origin))) noise += 1;
        else own.push(text.slice(0, 300));
    });
    page.on('pageerror', (err) => own.push(`uncaught: ${err.message.slice(0, 300)}`));
    page.on('response', (res) => {
        if (res.status() >= 400 && res.url().includes(origin)) failed.push(`${res.status()} ${res.url().slice(0, 160)}`);
    });

    const result = { ...job, status: null, ownErrors: own, noiseErrors: 0, failedRequests: failed, overflowX: 0, screenshot: null };
    try {
        const url = job.admin ? await adminUrl(page, job.path) : `${job.host}${job.path}`;
        const res = await page.goto(url, { waitUntil: 'load', timeout: 90000 });
        result.status = res?.status() ?? null;
        result.finalUrl = page.url();
        await page.waitForTimeout(job.admin ? 3000 : 1500);
        result.overflowX = await measureOverflow(page);
        const name = `${job.brand}${job.path.replace(/[^\w]+/g, '_')}_${job.viewport}`.replace(/_+$/, '');
        const shot = async (suffix) => {
            const file = join(opts.out, `${name}${suffix ? `__${suffix}` : ''}.png`);
            await page.screenshot({ path: file, fullPage: opts.full });
            return file;
        };
        result.screenshot = await shot();
        if (opts.steps) {
            const steps = (await import(pathToFileURL(opts.steps).href)).default;
            result.steps = await steps(page, { brand: job.brand, host: job.host, path: job.path, viewport: job.viewport, shot });
            // A step can open a menu or a filter that breaks the layout: keep the worse of the two states.
            result.overflowX = Math.max(result.overflowX, await measureOverflow(page));
        }
    } catch (err) {
        result.error = err.message.split('\n')[0];
    }
    result.noiseErrors = noise;
    await context.close();
    return result;
}

async function main() {
    const opts = parseArgs(process.argv.slice(2));
    mkdirSync(opts.out, { recursive: true });
    const { chromium } = loadPlaywright();
    const browser = await chromium.launch({ headless: true });
    const jobs = expand(opts);

    let adminState;
    if (jobs.some((j) => j.admin)) {
        if (!opts.admin) throw new Error('admin: targets need --admin');
        const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
        await adminLogin(context);
        adminState = await context.storageState();
        await context.close();
    }

    const results = [];
    for (const job of jobs) {
        const r = await visit(browser, job, opts, adminState);
        results.push(r);
        const flags = [
            r.error && `ERROR ${r.error}`,
            r.status && r.status >= 400 && `HTTP ${r.status}`,
            r.ownErrors.length && `${r.ownErrors.length} own console error(s)`,
            r.failedRequests.length && `${r.failedRequests.length} failed request(s)`,
            r.overflowX > 0 && `horizontal overflow ${r.overflowX}px`,
        ].filter(Boolean);
        // The old site is the reference for a parity check, not under test: its own errors never fail the run.
        const tag = r.brand === 'old' ? 'ref ' : flags.length ? 'FAIL' : 'ok  ';
        console.log(`${tag} ${r.brand.padEnd(5)} ${String(r.viewport).padEnd(4)} ${r.path}`
            + `  [${r.status ?? '-'}; noise ${r.noiseErrors}]${flags.length ? `  ${flags.join('; ')}` : ''}`);
        if (r.brand === 'old') continue;
        r.ownErrors.slice(0, 5).forEach((e) => console.log(`       console: ${e}`));
        r.failedRequests.slice(0, 5).forEach((e) => console.log(`       request: ${e}`));
        if (r.steps !== undefined) console.log(`       steps: ${JSON.stringify(r.steps).slice(0, 400)}`);
    }
    await browser.close();
    writeFileSync(join(opts.out, 'report.json'), JSON.stringify(results, null, 2));
    console.log(`\nscreenshots + report.json: ${opts.out}`);
    process.exit(results.some((r) => r.brand !== 'old' && (r.error || (r.status && r.status >= 400) || r.ownErrors.length || r.failedRequests.length || r.overflowX > 0)) ? 1 : 0);
}

main().catch((err) => {
    console.error(err.message);
    process.exit(2);
});
