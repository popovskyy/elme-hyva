import { expect, test } from '@playwright/test';

// FE-74: named shopping lists (Elme_QuickOrder list endpoints, page /quick-order/?list=). Test data of
// qty-rules.spec.mjs (seed-qty-rules.php): E2E-MOQ6 is sold in sixes, E2E-NO-MOQ from 1.
const EMAIL = 'e2e-qty@example.com';
const PASSWORD = 'E2eCheckout!2026';
const LIST = 'E2E Showroom';
const OTHER = 'E2E Warehouse';
const SAVE_URL = /\/quick-order\/list\/save/;

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

const saveList = (page, name, mode, items = []) => post(page, '/quick-order/list/save', { name, mode, items });

const getList = async (page, name) => (
    await (await page.request.get(`/quick-order/list/get?name=${encodeURIComponent(name)}`)).json()
);

const rows = (answer) => answer.list.items.map(({ sku, qty }) => ({ sku, qty }));

const listUrl = (name) => `/quick-order/?list=${encodeURIComponent(name)}`;

// Tab through the page and return the first focused element the sticky bottom bar (below lg) hides, or null
async function focusHiddenByBar(page, presses) {
    for (let i = 0; i < presses; i++) {
        await page.keyboard.press('Tab');
        const hidden = await page.evaluate(() => {
            const bar = [...document.querySelectorAll('.elme-cart__bar')].find((el) => el.offsetParent !== null);
            const active = document.activeElement;
            if (!bar || !active || active === document.body || bar.contains(active)) {
                return null;
            }
            const a = active.getBoundingClientRect();
            const b = bar.getBoundingClientRect();
            return a.height && a.bottom > b.top && a.top < b.bottom
                ? `${active.tagName} "${active.textContent.trim().slice(0, 40)}"`
                : null;
        });
        if (hidden) {
            return hidden;
        }
    }
    return null;
}

test.beforeEach(async ({ page }) => {
    await login(page);
    const all = await (await page.request.get('/quick-order/list/getall')).json();
    for (const list of all.lists) {
        await post(page, '/quick-order/list/delete', { id: list.id });
    }
    expect((await saveList(page, LIST, 'create')).success).toBe(true);
    expect((await saveList(page, LIST, 'add', [{ sku: 'E2E-MOQ6', qty: 12 }, { sku: 'E2E-NO-MOQ', qty: 1 }])).success)
        .toBe(true);
});

test('create with the name of an existing list refuses and keeps the list', async ({ page }) => {
    // the endpoint
    const answer = await saveList(page, LIST.toUpperCase(), 'create');
    expect(answer.success).toBe(false);
    expect(answer.message).toBe('A list with this name already exists.');

    // the page
    await page.goto('/quick-order/');
    await page.getByPlaceholder('List name').fill(LIST);
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(page.getByText('A list with this name already exists.')).toBeVisible();
    await expect(page).toHaveURL(/\/quick-order\/$/);

    expect(rows(await getList(page, LIST))).toEqual([{ sku: 'E2E-MOQ6', qty: 12 }, { sku: 'E2E-NO-MOQ', qty: 1 }]);
});

test('typing 0 or clearing the qty keeps the row at its minimum', async ({ page }) => {
    await page.goto(listUrl(LIST));
    const moq = page.locator('#qo-list-qty-E2E-MOQ6');
    const single = page.locator('#qo-list-qty-E2E-NO-MOQ');
    await expect(moq).toHaveValue('12');

    await moq.fill('0');
    await moq.blur();
    await expect(moq).toHaveValue('6');

    await single.fill('');
    await single.blur();
    await expect(single).toHaveValue('1');

    await expect.poll(async () => rows(await getList(page, LIST)))
        .toEqual([{ sku: 'E2E-MOQ6', qty: 6 }, { sku: 'E2E-NO-MOQ', qty: 1 }]);

    await page.reload();
    await expect(page.locator('#qo-list-qty-E2E-MOQ6')).toHaveValue('6');
    await expect(page.locator('#qo-list-qty-E2E-NO-MOQ')).toHaveValue('1');
});

test('adding an unknown SKU says so and stores nothing', async ({ page }) => {
    const answer = await saveList(page, LIST, 'add', [{ sku: 'NO-SUCH-SKU-123', qty: 1 }]);
    expect(answer.success).toBe(false);
    expect(answer.message).toBe('SKU not found.');

    await page.goto(listUrl(LIST));
    await expect(page.locator('#qo-list-qty-E2E-MOQ6')).toBeVisible();
    await page.getByPlaceholder('Add a SKU').fill('NO-SUCH-SKU-123');
    await page.getByRole('button', { name: 'Add', exact: true }).click();
    await expect(page.getByText('SKU not found.')).toBeVisible();
    await expect(page.getByPlaceholder('Add a SKU')).toHaveValue('NO-SUCH-SKU-123');

    // a known SKU typed in lower case is stored as the catalogue writes it
    await page.getByPlaceholder('Add a SKU').fill('e2e-moq-later');
    await page.getByRole('button', { name: 'Add', exact: true }).click();
    await expect(page.locator('#qo-list-qty-E2E-MOQ-LATER')).toBeVisible();
    await expect(page.getByText('SKU not found.')).toBeHidden();

    expect(rows(await getList(page, LIST)).map(({ sku }) => sku)).toEqual(['E2E-MOQ6', 'E2E-NO-MOQ', 'E2E-MOQ-LATER']);
});

test('+ clicks while a save is on its way end with the last qty', async ({ page }) => {
    await page.goto(listUrl(LIST));
    const single = page.locator('#qo-list-qty-E2E-NO-MOQ');
    await expect(single).toHaveValue('1');

    // every list save takes a second, so the next clicks land while one is in flight
    await page.route(SAVE_URL, async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 1000));
        await route.continue();
    });
    const plus = page.locator('li', { has: single }).getByRole('button', { name: 'Increase quantity' });

    await plus.click();
    await page.waitForRequest(SAVE_URL);
    await plus.click();
    await plus.click();
    await plus.click();
    await expect(single).toHaveValue('5');

    await expect.poll(async () => rows(await getList(page, LIST)).find(({ sku }) => sku === 'E2E-NO-MOQ').qty, {
        timeout: 15_000,
    }).toBe(5);
    // the answer of the first save came back after the clicks: it must not have set the qty back
    await expect(single).toHaveValue('5');

    await page.unroute(SAVE_URL);
    await page.reload();
    await expect(page.locator('#qo-list-qty-E2E-NO-MOQ')).toHaveValue('5');
});

test('rename: Enter saves, a taken name is refused, Esc cancels', async ({ page }) => {
    expect((await saveList(page, OTHER, 'create')).success).toBe(true);
    await page.goto(listUrl(LIST));
    const rename = page.getByRole('button', { name: 'Rename list' });
    const input = page.getByRole('textbox', { name: 'List name' });

    await rename.click();
    await input.fill(OTHER.toLowerCase());
    await input.press('Enter');
    await expect(page.getByText('A list with this name already exists.')).toBeVisible();

    await input.press('Escape');
    await expect(input).toBeHidden();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(LIST);

    await rename.click();
    await input.fill('E2E Shop floor');
    await input.press('Enter');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('E2E Shop floor');
    await expect(page).toHaveURL(/list=E2E(\+|%20)Shop(\+|%20)floor/);

    expect((await getList(page, LIST)).found).toBe(false);
    expect(rows(await getList(page, 'E2E Shop floor'))).toEqual([
        { sku: 'E2E-MOQ6', qty: 12 },
        { sku: 'E2E-NO-MOQ', qty: 1 },
    ]);
    const taken = await post(page, '/quick-order/list/rename', { name: 'E2E Shop floor', new_name: OTHER });
    expect(taken.message).toBe('A list with this name already exists.');

    await page.reload();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('E2E Shop floor');
});

test('deleting a list on My Shopping List does not show the loading skeleton again', async ({ page }) => {
    expect((await saveList(page, OTHER, 'create')).success).toBe(true);
    await page.goto('/quick-order/template');
    const rowsOnPage = page.locator('.elme-shopping-lists [data-list-row]');
    await expect(rowsOnPage).toHaveCount(2);
    const skeleton = page.locator('.elme-shopping-lists [aria-busy="true"]');
    await expect(skeleton).toBeHidden();

    // record any moment the skeleton shows after the click
    await page.evaluate(() => {
        const el = document.querySelector('.elme-shopping-lists [aria-busy="true"]');
        window.__skeletonShown = false;
        new MutationObserver(() => {
            if (el.style.display !== 'none') {
                window.__skeletonShown = true;
            }
        }).observe(el, { attributes: true, attributeFilter: ['style'] });
    });

    await page.locator('[data-list-row]', { hasText: OTHER }).getByRole('button', { name: 'Delete' }).click();
    // Confirm takes clicks once the dialog has finished opening (no delete on a double tap)
    await page.waitForTimeout(250);
    const deleted = page.waitForResponse(/\/quick-order\/list\/delete/);
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
    await deleted;
    await expect(rowsOnPage).toHaveCount(1);
    await page.waitForTimeout(500);
    expect(await page.evaluate(() => window.__skeletonShown)).toBe(false);
});

test('the delete dialog keeps keyboard focus inside and gives it back to the trash button', async ({ page }) => {
    await page.goto('/quick-order/template');
    const trash = page.locator('[data-list-row]', { hasText: LIST }).getByRole('button', { name: 'Delete' });
    await trash.focus();
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('alertdialog');
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeFocused();

    for (const key of ['Tab', 'Tab', 'Tab', 'Shift+Tab', 'Shift+Tab', 'Shift+Tab']) {
        await page.keyboard.press(key);
        expect(await dialog.evaluate((el) => el.contains(document.activeElement)), key).toBe(true);
    }

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trash).toBeFocused();
});

test('on a phone the sticky bar of the list editor never covers the focused element', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(listUrl(LIST));
    await expect(page.locator('.elme-cart__bar')).toBeVisible();
    expect(await focusHiddenByBar(page, 60)).toBeNull();
});

test('FE-75: list editor Remove asks first, the row turns to dust and the list is saved without it', async ({ page }) => {
    await page.goto(listUrl(LIST));
    const line = (sku) => page.locator('.elme-cart__line', { hasText: sku });
    const remove = line('E2E-NO-MOQ').getByRole('button', { name: 'Remove', exact: true });
    const dialog = page.getByRole('alertdialog');

    await remove.click();
    await expect(dialog).toHaveAccessibleName(/^Remove .*E2E-NO-MOQ from this list\?$/);
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(remove).toBeFocused();

    await remove.click();
    // Confirm takes clicks once the dialog has finished opening (no delete on a double tap)
    await page.waitForTimeout(250);
    const saved = page.waitForResponse(SAVE_URL);
    await dialog.getByRole('button', { name: 'Remove' }).click();
    await expect(page.locator('.elme-dust-canvas')).toBeAttached();
    await saved;
    await expect(line('E2E-NO-MOQ')).toHaveCount(0);
    await expect(page.locator('.elme-dust-canvas')).not.toBeAttached();
    await expect(line('E2E-MOQ6').getByRole('button', { name: 'Remove', exact: true })).toBeFocused();
    expect(rows(await getList(page, LIST))).toEqual([{ sku: 'E2E-MOQ6', qty: 12 }]);
});
