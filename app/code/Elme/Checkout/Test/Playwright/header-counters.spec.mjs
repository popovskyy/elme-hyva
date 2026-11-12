import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

// FE-79: header cart + shopping-list badges stay in sync with the server (same session and a second browser).
// Account: e2e-qty@example.com (not Roman's manual e2e-checkout@example.com).
const EMAIL = 'e2e-qty@example.com';
const PASSWORD = 'E2eCheckout!2026';
const SKU = 'E2E-NO-MOQ';
const LIST = 'E2E Counters';
const PLP = '/flowers-plants.html';

const magento = (...args) => execFileSync(
    'docker',
    ['exec', '-u', 'magento', '-w', '/var/www/html', 'elme_php', 'bin/magento', ...args],
    { stdio: 'pipe' },
).toString();

const banVarnish = () => {
    try {
        execFileSync('docker', ['exec', 'elme_varnish', 'varnishadm', 'ban', 'req.url ~ .'], { stdio: 'pipe' });
    } catch (e) {
        // local stack without Varnish
    }
};

async function login(page) {
    await page.goto('/customer/account/login');
    await page.locator('#email').fill(EMAIL);
    await page.locator('#pass').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    await expect(page).not.toHaveURL(/customer\/account\/login/);
}

const formKey = async (page) => (await page.context().cookies()).find((cookie) => cookie.name === 'form_key').value;

const post = async (page, path, data) => (await page.request.post(path, {
    data: { form_key: await formKey(page), ...data },
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
})).json();

const getAll = async (page) => (await page.request.get('/quick-order/list/getall', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
})).json();

const saveCart = async (page, items, mode = 'replace') => post(page, '/quick-order/cart/save', { mode, items });

const cartBadge = (page) => page.locator('#menu-cart-icon .elme-header__badge');
const listsBadge = (page) => page.locator('.elme-header__lists-btn .elme-header__roll-in');

async function expectCartBadge(page, count) {
    if (count === 0) {
        await expect(cartBadge(page)).toBeHidden({ timeout: 15_000 });
        return;
    }
    await expect(cartBadge(page)).toHaveText(String(count), { timeout: 15_000 });
}

async function expectListsBadge(page, count) {
    if (count === 0) {
        await expect(listsBadge(page)).toBeHidden({ timeout: 15_000 });
        return;
    }
    await expect(listsBadge(page)).toHaveText(String(count), { timeout: 15_000 });
}

async function resetCustomer(page) {
    await saveCart(page, []);
    for (const list of (await getAll(page)).lists || []) {
        await post(page, '/quick-order/list/delete', { id: list.id });
    }
    await page.goto('/');
    await expectCartBadge(page, 0);
}

async function runMatrix(page, browser, label) {
    await login(page);
    await resetCustomer(page);

    // --- same session: add to cart / qty / heart ---
    expect((await saveCart(page, [{ sku: SKU, qty: 2 }], 'replace')).success).toBe(true);
    await page.reload();
    await expectCartBadge(page, 2);

    expect((await saveCart(page, [{ sku: SKU, qty: 5 }], 'replace')).success).toBe(true);
    await page.evaluate(() => window.elmeQuickOrderCart.load(true));
    await expectCartBadge(page, 5);
    await page.goto('/quick-order/cart');
    await expectCartBadge(page, 5);
    await page.goto('/');
    await expectCartBadge(page, 5);

    expect((await post(page, '/quick-order/list/save', {
        name: LIST,
        mode: 'create',
        items: [],
    })).success).toBe(true);
    expect((await post(page, '/quick-order/list/save', {
        name: LIST,
        mode: 'merge',
        items: [{ sku: SKU, qty: 1 }],
    })).success).toBe(true);
    // API save does not bump this tab's private_content_version; reload the section then the page
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('reload-customer-section-data')));
    await page.waitForFunction(() => {
        const data = JSON.parse(hyva.getBrowserStorage().getItem('mage-cache-storage') || '{}') || {};
        return (parseInt(data.quick_order?.lists_item_count, 10) || 0) === 1;
    }, null, { timeout: 15_000 });
    await page.goto(PLP);
    await expectListsBadge(page, 1);

    await page.goto('/quick-order/template');
    await page.locator('[data-list-row]', { hasText: LIST }).getByRole('button', { name: 'Delete' }).click();
    await page.waitForTimeout(250);
    const deleted = page.waitForResponse(/\/quick-order\/list\/delete/);
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
    await deleted;
    await expectListsBadge(page, 0);

    // --- second browser changes cart + lists; first recovers ---
    expect((await saveCart(page, [{ sku: SKU, qty: 3 }], 'replace')).success).toBe(true);
    // create is empty-only; add the SKU in a second save
    expect((await post(page, '/quick-order/list/save', { name: LIST, mode: 'create', items: [] })).success).toBe(true);
    expect((await post(page, '/quick-order/list/save', {
        name: LIST,
        mode: 'merge',
        items: [{ sku: SKU, qty: 1 }],
    })).success).toBe(true);
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('reload-customer-section-data')));
    await page.waitForFunction(() => {
        const data = JSON.parse(hyva.getBrowserStorage().getItem('mage-cache-storage') || '{}') || {};
        return (parseInt(data.quick_order?.lists_item_count, 10) || 0) === 1
            && (parseInt(data.quick_order?.summary_count, 10) || 0) === 3;
    }, null, { timeout: 15_000 });
    await page.goto('/');
    await expectCartBadge(page, 3);
    await expectListsBadge(page, 1);

    const other = await browser.newContext({ ignoreHTTPSErrors: true });
    const otherPage = await other.newPage();
    await login(otherPage);
    expect((await saveCart(otherPage, [], 'replace')).success).toBe(true);
    for (const list of (await getAll(otherPage)).lists || []) {
        await post(otherPage, '/quick-order/list/delete', { id: list.id });
    }
    await otherPage.close();
    await other.close();

    // after reload
    await page.reload();
    await expectCartBadge(page, 0);
    await expectListsBadge(page, 0);

    // seed stale cache again, then cart page
    expect((await saveCart(page, [{ sku: SKU, qty: 4 }], 'replace')).success).toBe(true);
    await page.reload();
    await expectCartBadge(page, 4);
    const other2 = await browser.newContext({ ignoreHTTPSErrors: true });
    const otherPage2 = await other2.newPage();
    await login(otherPage2);
    expect((await saveCart(otherPage2, [], 'replace')).success).toBe(true);
    await otherPage2.close();
    await other2.close();

    // stash a stale summary_count in local storage the way a long-lived tab would
    await page.evaluate(() => {
        const storage = hyva.getBrowserStorage();
        const data = JSON.parse(storage.getItem('mage-cache-storage') || '{}') || {};
        if (!data.quick_order) {
            data.quick_order = {};
        }
        data.quick_order.summary_count = 4;
        data.quick_order.lists_item_count = 0;
        storage.setItem('mage-cache-storage', JSON.stringify(data));
    });
    await page.goto('/quick-order/cart');
    await expectCartBadge(page, 0);
    await page.goto('/');
    await expectCartBadge(page, 0);

    // focus / visibility when section is older than ~2 min
    expect((await saveCart(page, [{ sku: SKU, qty: 7 }], 'replace')).success).toBe(true);
    await page.reload();
    await expectCartBadge(page, 7);
    const other3 = await browser.newContext({ ignoreHTTPSErrors: true });
    const otherPage3 = await other3.newPage();
    await login(otherPage3);
    expect((await saveCart(otherPage3, [], 'replace')).success).toBe(true);
    await otherPage3.close();
    await other3.close();

    await page.evaluate(() => {
        const storage = hyva.getBrowserStorage();
        const data = JSON.parse(storage.getItem('mage-cache-storage') || '{}') || {};
        if (!data.quick_order) {
            data.quick_order = {};
        }
        data.quick_order.summary_count = 7;
        storage.setItem('mage-cache-storage', JSON.stringify(data));
        window.elmeForceSectionStale();
        document.dispatchEvent(new Event('visibilitychange'));
    });
    // visibilityState stays "visible" in Playwright; call the refresh path via pageshow(persisted)
    await page.evaluate(() => {
        window.elmeForceSectionStale();
        const event = new Event('pageshow');
        Object.defineProperty(event, 'persisted', { value: true });
        window.dispatchEvent(event);
    });
    await page.waitForFunction(() => {
        const data = JSON.parse(hyva.getBrowserStorage().getItem('mage-cache-storage') || '{}') || {};
        return (parseInt(data.quick_order?.summary_count, 10) || 0) === 0;
    }, null, { timeout: 15_000 });
    await expectCartBadge(page, 0);

    // bfcache-style pageshow already covered above; also navigate forward/back
    expect((await saveCart(page, [{ sku: SKU, qty: 1 }], 'replace')).success).toBe(true);
    await page.goto('/');
    await expectCartBadge(page, 1);
    await page.goto('/customer/account/');
    const other4 = await browser.newContext({ ignoreHTTPSErrors: true });
    const otherPage4 = await other4.newPage();
    await login(otherPage4);
    expect((await saveCart(otherPage4, [], 'replace')).success).toBe(true);
    await otherPage4.close();
    await other4.close();
    await page.goBack();
    await page.evaluate(() => {
        window.elmeForceSectionStale();
        const event = new Event('pageshow');
        Object.defineProperty(event, 'persisted', { value: true });
        window.dispatchEvent(event);
    });
    await expectCartBadge(page, 0);

    // silence unused label in some runners
    expect(label).toBeTruthy();
}

for (const fullPageOn of [true, false]) {
    test.describe(`full_page ${fullPageOn ? 'on' : 'off'}`, () => {
        test.beforeAll(() => {
            magento(fullPageOn ? 'cache:enable' : 'cache:disable', 'full_page');
            magento('cache:flush', 'full_page');
            banVarnish();
        });

        test(`header counters matrix (${fullPageOn ? 'Varnish/FPC on' : 'FPC off'})`, async ({ page, browser }) => {
            await runMatrix(page, browser, fullPageOn ? 'on' : 'off');
        });
    });
}
