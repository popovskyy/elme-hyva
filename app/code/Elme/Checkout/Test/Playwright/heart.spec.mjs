import { expect, test } from '@playwright/test';

// FE-78: the heart on product cards and the PDP saves to a shopping list (Elme_QuickOrder js/lists-store.phtml,
// add-to-list/modal.phtml). Roman 2026-11-07: "the heart works every other time and keeps loading"; 2026-11-08:
// a product already in a list keeps an empty heart on flowers-plants.html. Run in Chromium and Firefox.
const EMAIL = 'e2e-qty@example.com';
const PASSWORD = 'E2eCheckout!2026';
const LIST = 'E2E Showroom';
const OTHER = 'E2E Warehouse';
const PLP = '/flowers-plants.html';
const SAVE_URL = /\/quick-order\/list\/save/;

async function login(page) {
    await page.goto('/customer/account/login');
    await page.locator('#email').fill(EMAIL);
    await page.locator('#pass').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    await expect(page).not.toHaveURL(/customer\/account\/login/);
}

// from inside the page: its cookies, and the host the browser resolves
const post = (page, path, data) => page.evaluate(async ([url, body]) => (await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ form_key: hyva.getFormKey(), ...body }),
})).json(), [path, data]);

const getAll = (page) => page.evaluate(async () => (await fetch('/quick-order/list/getall', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    cache: 'no-store',
})).json());

const skusOf = async (page, name) => (await getAll(page)).lists.find((list) => list.name === name)?.skus || [];

const picker = (page) => page.getByRole('dialog', { name: 'Save to shopping list' });

const hearts = (page) => page.locator('.elme-card__heart[data-elme-heart]');

const listsBadge = (page) => page.locator('.elme-header__lists-btn .elme-header__roll-in');

// the first and second product of the grid (not the recently viewed strip)
async function gridSkus(page) {
    return page.locator('.products-grid .elme-card__heart, .product-items .elme-card__heart')
        .evaluateAll((buttons) => [...new Set(buttons.map((button) => button.dataset.sku))].slice(0, 2));
}

const heartFor = (page, sku) => page.locator(`.elme-card__heart[data-sku="${sku}"]`).first();

test.beforeEach(async ({ page }) => {
    await login(page);
    for (const list of (await getAll(page)).lists) {
        await post(page, '/quick-order/list/delete', { id: list.id });
    }
    expect((await post(page, '/quick-order/list/save', { name: LIST, mode: 'create', items: [] })).success).toBe(true);
    expect((await post(page, '/quick-order/list/save', { name: OTHER, mode: 'create', items: [] })).success).toBe(true);
});

test('a product already in a list has a filled heart on the PLP, also after reload', async ({ page }) => {
    await page.goto(PLP);
    const [saved, other] = await gridSkus(page);
    await post(page, '/quick-order/list/save', { name: LIST, mode: 'merge', items: [{ sku: saved, qty: 1 }] });

    // a new page load: the section has to bring the lists along
    await page.reload();
    await expect(heartFor(page, saved)).toHaveAttribute('aria-pressed', 'true');
    await expect(heartFor(page, saved)).toHaveAttribute('aria-label', /^Saved to shopping list /);
    await expect(heartFor(page, other)).toHaveAttribute('aria-pressed', 'false');
    await expect(listsBadge(page)).toHaveText('1');

    await page.reload();
    await expect(heartFor(page, saved)).toHaveAttribute('aria-pressed', 'true');
});

test('20 opens in a row: the picker is there at once, never a Loading box, Esc gives focus back', async ({ page }) => {
    await page.goto(PLP);
    const skus = await gridSkus(page);
    let getAllCalls = 0;
    page.on('request', (request) => {
        if (request.url().includes('/quick-order/list/getall')) {
            getAllCalls++;
        }
    });

    for (let i = 0; i < 20; i++) {
        const heart = heartFor(page, skus[i % 2]);
        await heart.click();
        await expect(picker(page)).toBeVisible({ timeout: 1000 });
        await expect(picker(page).getByRole('button', { name: new RegExp(LIST) })).toBeVisible({ timeout: 300 });
        await expect(page.getByText('Loading...')).toHaveCount(0);
        if (i % 2) {
            await page.keyboard.press('Escape');
        } else {
            await page.getByRole('button', { name: 'Close' }).filter({ visible: true }).click();
        }
        await expect(picker(page)).toBeHidden();
        await expect(heart).toBeFocused();
    }
    expect(getAllCalls).toBe(0);
});

test('one tap on a row saves: heart fills, badge counts; a ticked row takes it off again', async ({ page }) => {
    await page.goto(PLP);
    const [sku] = await gridSkus(page);
    const heart = heartFor(page, sku);
    await expect(heart).toHaveAttribute('aria-pressed', 'false');

    await heart.click();
    const save = page.waitForResponse(SAVE_URL);
    await picker(page).getByRole('button', { name: new RegExp(LIST) }).click();
    expect((await (await save).json()).success).toBe(true);
    await expect(picker(page)).toBeHidden();
    await expect(heart).toHaveAttribute('aria-pressed', 'true');
    await expect(listsBadge(page)).toHaveText('1');
    await expect(page.getByText(`Saved to ${LIST}.`)).toBeVisible();
    expect(await skusOf(page, LIST)).toEqual([sku]);

    // the second tap (Roman's "every other time") opens the picker again with the row ticked
    await heart.click();
    const row = picker(page).getByRole('button', { name: new RegExp(LIST) });
    await expect(row).toHaveAttribute('aria-pressed', 'true');
    await expect(picker(page).getByRole('button', { name: new RegExp(OTHER) })).toHaveAttribute('aria-pressed', 'false');
    const remove = page.waitForResponse(SAVE_URL);
    await row.click();
    expect((await (await remove).json()).success).toBe(true);
    await expect(heart).toHaveAttribute('aria-pressed', 'false');
    await expect(listsBadge(page)).toBeHidden();
    expect(await skusOf(page, LIST)).toEqual([]);

    await page.reload();
    await expect(heartFor(page, sku)).toHaveAttribute('aria-pressed', 'false');
});

test('New list in the picker: a taken name is refused, a new one is made and saved to', async ({ page }) => {
    await page.goto(PLP);
    const [sku] = await gridSkus(page);
    await heartFor(page, sku).click();
    const name = picker(page).getByPlaceholder('Name a new list');

    await name.fill(LIST.toUpperCase());
    await name.press('Enter');
    await expect(picker(page).getByText('A list with this name already exists.')).toBeVisible();
    await expect(name).toHaveAttribute('aria-invalid', 'true');

    await name.fill('E2E Heart list');
    await picker(page).getByRole('button', { name: 'Create', exact: true }).click();
    await expect(picker(page)).toBeHidden();
    await expect(heartFor(page, sku)).toHaveAttribute('aria-pressed', 'true');
    await expect.poll(() => skusOf(page, 'E2E Heart list')).toEqual([sku]);
});

test('a failed save springs the heart back, shakes it and says so', async ({ page }) => {
    await page.goto(PLP);
    const [sku] = await gridSkus(page);
    const heart = heartFor(page, sku);
    await page.route(SAVE_URL, (route) => route.fulfill({
        status: 400,
        contentType: 'application/json',
        body: JSON.stringify({ success: false, message: 'Could not save the list.' }),
    }));

    await heart.click();
    await picker(page).getByRole('button', { name: new RegExp(LIST) }).click();
    await expect(page.getByText('Could not save the list.')).toBeVisible();
    await expect(heart).toHaveAttribute('aria-pressed', 'false');
    await expect(listsBadge(page)).toBeHidden();
});

test('the save motion: burst, a heart flies to the header; reduced motion is colour only', async ({ page }) => {
    await page.goto(PLP);
    const [first, second] = await gridSkus(page);

    await heartFor(page, first).click();
    await picker(page).getByRole('button', { name: new RegExp(LIST) }).click();
    await expect(heartFor(page, first)).toHaveClass(/is-saving/);
    await expect(page.locator('.elme-heart-fly')).toHaveCount(1);
    await expect(page.locator('.elme-header__lists-btn .elme-header__icon')).toHaveClass(/is-squashed/);
    await expect(listsBadge(page)).toHaveText('1');

    await page.emulateMedia({ reducedMotion: 'reduce' });
    let flew = false;
    await page.exposeFunction('elmeSawFlight', () => { flew = true; });
    await page.evaluate(() => new MutationObserver(() => {
        if (document.querySelector('.elme-heart-fly')) {
            window.elmeSawFlight();
        }
    }).observe(document.body, { childList: true }));
    await heartFor(page, second).click();
    await picker(page).getByRole('button', { name: new RegExp(LIST) }).click();
    await expect(heartFor(page, second)).toHaveAttribute('aria-pressed', 'true');
    await expect(listsBadge(page)).toHaveText('2');
    await expect(heartFor(page, second)).not.toHaveClass(/is-saving/);
    expect(flew).toBe(false);
});

test('the phone gets a bottom sheet; a heart in the recently viewed strip (AJAX) opens it and shows its state', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(PLP);
    const [sku] = await gridSkus(page);
    await post(page, '/quick-order/list/save', { name: OTHER, mode: 'merge', items: [{ sku, qty: 1 }] });
    // open its PDP so it lands in recently viewed, then back to the PLP
    await page.locator('.elme-card', { has: heartFor(page, sku) }).first().locator('a.product-item-link').click();
    await expect(page.locator('.elme-save[data-elme-heart]')).toHaveAttribute('aria-pressed', 'true');
    await page.goto(PLP);

    const strip = page.locator('.elme-rv-products__slide .elme-card__heart');
    await strip.first().scrollIntoViewIfNeeded();
    await expect(strip.first()).toHaveAttribute('aria-pressed', 'true');
    await strip.first().click();
    await expect(picker(page)).toBeVisible();
    await expect(page.locator('.elme-lp.is-sheet')).toBeVisible();
    await expect(picker(page).getByRole('button', { name: new RegExp(OTHER) })).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('Escape');
    await expect(picker(page)).toBeHidden();
});
