import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

// BE-16: the Quick Order cart (cart_list) is the cart. Test data of qty-rules.spec.mjs (seed-qty-rules.php).
const EMAIL = 'e2e-qty@example.com';
const PASSWORD = 'E2eCheckout!2026';
const MOQ_MESSAGE = 'This product is sold in multiples of 6';

const setMoq = (sku, moq) => execFileSync(
    'docker',
    ['exec', '-u', 'magento', '-w', '/var/www/html', 'elme_php', 'php', 'app/code/Elme/Checkout/Test/Playwright/set-moq.php', sku, String(moq)],
    { stdio: 'inherit' }
);

async function login(page) {
    await page.goto('/customer/account/login');
    await page.locator('#email').fill(EMAIL);
    await page.locator('#pass').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    await expect(page).not.toHaveURL(/customer\/account\/login/);
}

const formKey = async (page) => (await page.context().cookies()).find((cookie) => cookie.name === 'form_key').value;

const productId = async (page, sku) => {
    await page.goto(`/${sku.toLowerCase()}.html`);
    return page.locator('input[name="product"]').first().inputValue();
};

// The ajax add to cart of the tiles and the product page (FE-70), and what it left in the messages section
async function addToCart(page, id, qty) {
    const form = { form_key: await formKey(page), product: id };
    if (qty !== undefined) {
        form.qty = String(qty);
    }
    const answer = await page.request.post('/checkout/cart/add', {
        form,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    expect(answer.headers()['content-type']).toContain('json');
    const sections = await (await page.request.get('/customer/section/load/?sections=messages')).json();
    return sections.messages.messages;
}

const getCart = async (page) => (await (await page.request.get('/quick-order/cart/get')).json()).cart;

const saveCart = async (page, items, mode = 'replace') => (await page.request.post('/quick-order/cart/save', {
    data: { form_key: await formKey(page), mode, items },
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
})).json();

const rows = (cart) => cart.items.map(({ sku, qty }) => ({ sku, qty }));

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

const section = async (page, name) => (
    await (await page.request.get(`/customer/section/load/?sections=${name}&force_new_section_timestamp=true`)).json()
)[name];

test.beforeEach(async ({ page }) => {
    setMoq('E2E-MOQ-LATER', 1);
    await login(page);
    await saveCart(page, []);
});

test('add to cart goes to the cart list, the header count follows, the quote stays empty', async ({ page }) => {
    const id = await productId(page, 'E2E-MOQ6');
    const quoteBefore = await section(page, 'cart');

    const messages = await addToCart(page, id, 12);
    expect(messages.map((message) => message.type)).toEqual(['success']);
    expect(rows(await getCart(page))).toEqual([{ sku: 'E2E-MOQ6', qty: 12 }]);
    expect((await section(page, 'quick_order')).summary_count).toBe(12);
    expect((await section(page, 'cart')).summary_count).toBe(quoteBefore.summary_count);

    // a second add adds up; no qty is the MOQ
    await addToCart(page, id);
    expect(rows(await getCart(page))).toEqual([{ sku: 'E2E-MOQ6', qty: 18 }]);
});

test('MOQ: a qty outside the multiples is refused on add and on save, with the message', async ({ page }) => {
    const id = await productId(page, 'E2E-MOQ6');

    const messages = await addToCart(page, id, 4);
    expect(messages).toEqual([expect.objectContaining({ type: 'error', text: MOQ_MESSAGE })]);
    expect(rows(await getCart(page))).toEqual([]);

    await saveCart(page, [{ sku: 'E2E-MOQ6', qty: 6 }]);
    const answer = await saveCart(page, [{ sku: 'E2E-MOQ6', qty: 7 }]);
    expect(answer.errors).toEqual({ 'E2E-MOQ6': MOQ_MESSAGE });
    expect(rows(answer.cart)).toEqual([{ sku: 'E2E-MOQ6', qty: 6 }]);
    expect(answer.cart.items[0].message).toBe(MOQ_MESSAGE);
});

test('five quick qty changes are one save after the debounce, and a reload shows the last qty', async ({ page }) => {
    await saveCart(page, [{ sku: 'E2E-NO-MOQ', qty: 1 }]);
    await page.goto('/quick-order/cart');
    const qty = page.getByRole('spinbutton', { name: 'Qty' });
    await expect(qty).toHaveValue('1');

    const saves = [];
    page.on('request', (request) => {
        if (request.url().includes('/quick-order/cart/save')) {
            saves.push(request.postDataJSON());
        }
    });
    const saved = page.waitForResponse((response) => response.url().includes('/quick-order/cart/save'));
    const plus = page.getByRole('button', { name: 'Increase quantity' });
    for (let i = 0; i < 5; i++) {
        await plus.click();
    }
    await expect(qty).toHaveValue('6');
    expect(saves).toHaveLength(0);
    await saved;
    await page.waitForTimeout(1500);
    expect(saves).toEqual([expect.objectContaining({ mode: 'replace', items: [{ sku: 'E2E-NO-MOQ', qty: 6 }] })]);

    await page.reload();
    await expect(qty).toHaveValue('6');
    expect((await section(page, 'quick_order')).summary_count).toBe(6);
});

test('a qty edit still waiting for the debounce is saved when the page is reloaded or left', async ({ page }) => {
    await saveCart(page, [{ sku: 'E2E-NO-MOQ', qty: 1 }]);
    await page.goto('/quick-order/cart');
    const qty = page.getByRole('spinbutton', { name: 'Qty' });
    const plus = page.getByRole('button', { name: 'Increase quantity' });
    await expect(qty).toHaveValue('1');

    for (let i = 0; i < 5; i++) {
        await plus.click();
    }
    await page.reload();
    await expect(qty).toHaveValue('6');
    expect(rows(await getCart(page))).toEqual([{ sku: 'E2E-NO-MOQ', qty: 6 }]);

    await plus.click();
    await plus.click();
    await page.goto('/');
    await expect.poll(async () => rows(await getCart(page))).toEqual([{ sku: 'E2E-NO-MOQ', qty: 8 }]);
});

test('the cart answer has live line data and the totals with GST from the server', async ({ page }) => {
    await saveCart(page, [{ sku: 'E2E-MOQ6', qty: 6 }, { sku: 'E2E-NO-MOQ', qty: 1 }]);
    const cart = await getCart(page);

    expect(cart.items.map((item) => [item.sku, item.min, item.step, item.available])).toEqual([
        ['E2E-MOQ6', 6, 6, true],
        ['E2E-NO-MOQ', 1, 1, true],
    ]);
    const subtotal = cart.items.reduce((sum, item) => sum + item.row_total, 0);
    expect(cart.totals.subtotal).toBeCloseTo(subtotal, 2);
    expect(cart.totals.gst).toBeCloseTo(subtotal * 0.1, 2);
    expect(cart.totals.total).toBeCloseTo(cart.totals.subtotal + cart.totals.gst, 2);
    expect(cart.summary_count).toBe(7);
    expect(cart.can_checkout).toBe(true);
});

test('/checkout/cart opens the Quick Order cart, and there is no Magento mini-cart', async ({ page }) => {
    await page.goto('/checkout/cart');
    await expect(page).toHaveURL(/\/quick-order\/cart\/?$/);
    await expect(page.locator('#cart-drawer, [x-data="initCartDrawer"]')).toHaveCount(0);
});

test('/checkout rebuilds the quote from the cart; a refused row goes back to the cart with its reason', async ({ page }) => {
    await saveCart(page, [{ sku: 'E2E-NO-MOQ', qty: 2 }, { sku: 'E2E-MOQ-LATER', qty: 1 }]);
    await page.goto('/checkout');
    await expect(page).toHaveURL(/\/checkout\/?$/);
    const quote = await section(page, 'cart');
    expect(quote.items.map(({ product_sku: sku, qty }) => ({ sku, qty: Number(qty) })).sort((a, b) => a.sku.localeCompare(b.sku)))
        .toEqual([{ sku: 'E2E-MOQ-LATER', qty: 1 }, { sku: 'E2E-NO-MOQ', qty: 2 }]);

    // leaving checkout keeps the cart
    await page.goto('/');
    expect(rows(await getCart(page))).toEqual([{ sku: 'E2E-NO-MOQ', qty: 2 }, { sku: 'E2E-MOQ-LATER', qty: 1 }]);

    // the row is no longer valid: back to the cart, with the reason, and the row says it too
    setMoq('E2E-MOQ-LATER', 6);
    await page.goto('/checkout');
    await expect(page).toHaveURL(/\/quick-order\/cart\/?$/);
    await expect(page.getByText(`E2E-MOQ-LATER: ${MOQ_MESSAGE}`)).toBeVisible();
    const cart = await getCart(page);
    expect(cart.can_checkout).toBe(false);
    expect(cart.items.find((item) => item.sku === 'E2E-MOQ-LATER')).toMatchObject({ available: false, message: MOQ_MESSAGE });
    expect(rows(cart)).toEqual([{ sku: 'E2E-NO-MOQ', qty: 2 }, { sku: 'E2E-MOQ-LATER', qty: 1 }]);
});

test('the endpoints are never cached and a guest gets 401', async ({ page, browser }) => {
    const answer = await page.request.get('/quick-order/cart/get');
    expect(answer.status()).toBe(200);
    expect(answer.headers()['cache-control']).toContain('no-store');

    const guest = await browser.newContext({ ignoreHTTPSErrors: true });
    for (const url of ['/quick-order/cart/get']) {
        const response = await guest.request.get(url, { maxRedirects: 0 });
        expect(response.status()).toBe(401);
        expect(response.headers()['cache-control']).toContain('no-store');
    }
    // as the cart client sends it; a plain form post without a form key is turned away earlier by Magento's CSRF check
    const save = await guest.request.post('/quick-order/cart/save', {
        data: { items: [] },
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        maxRedirects: 0,
    });
    expect(save.status()).toBe(401);
    const page2 = await guest.request.get('/quick-order/cart', { maxRedirects: 0 });
    expect(page2.status()).toBe(302);
    await guest.close();
});

test('a save without the form key is refused', async ({ page }) => {
    const answer = await page.request.post('/quick-order/cart/save', {
        data: { items: [{ sku: 'E2E-NO-MOQ', qty: 1 }] },
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    expect(answer.status()).toBe(400);
    expect(rows(await getCart(page))).toEqual([]);
});

test('on a phone the sticky bar of the cart never covers the focused element', async ({ page }) => {
    await saveCart(page, [{ sku: 'E2E-NO-MOQ', qty: 1 }, { sku: 'E2E-MOQ6', qty: 6 }]);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/quick-order/cart');
    await expect(page.locator('.elme-cart__bar')).toBeVisible();
    expect(await focusHiddenByBar(page, 60)).toBeNull();
});

// FE-75: Remove asks in the shared confirm dialog, then the row turns to dust (Magento_Theme::elme/dust.phtml)
const cartLine = (page, sku) => page.locator('.elme-cart__line', { hasText: sku });
const removeText = (page, sku) => cartLine(page, sku).getByRole('button', { name: 'Remove', exact: true });

async function confirmRemove(page) {
    // Confirm takes clicks once the dialog has finished opening (no delete on a double tap)
    await page.waitForTimeout(250);
    await page.getByRole('alertdialog').getByRole('button', { name: 'Remove' }).click();
}

test('FE-75: Remove asks first; Cancel and Esc keep the row, Remove turns it to dust, then the total changes', async ({ page }) => {
    await saveCart(page, [{ sku: 'E2E-NO-MOQ', qty: 1 }, { sku: 'E2E-MOQ6', qty: 6 }]);
    await page.goto('/quick-order/cart');
    const remove = removeText(page, 'E2E-NO-MOQ');
    const dialog = page.getByRole('alertdialog');
    const total = page.locator('.elme-cart__summary .elme-cart__money-total dd');
    await expect(total).not.toHaveText('');
    const before = await total.textContent();

    await remove.click();
    await expect(dialog).toHaveAccessibleName(/^Remove .*E2E-NO-MOQ from your cart\?$/);
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeFocused();
    await expect(dialog.locator('h1')).toHaveCount(0);
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();
    await expect(remove).toBeFocused();

    await remove.click();
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(remove).toBeFocused();
    await expect(page.locator('.elme-cart__line')).toHaveCount(2);
    await expect(page.locator('.elme-dust-canvas')).toHaveCount(0);

    await remove.click();
    await confirmRemove(page);
    await expect(page.locator('.elme-dust-canvas')).toBeAttached();
    await expect(page.locator('.elme-cart__line')).toHaveCount(1);
    await expect(total).not.toHaveText(before);
    await expect(page.locator('.elme-dust-canvas')).not.toBeAttached();
    await expect(removeText(page, 'E2E-MOQ6')).toBeFocused();
    await expect(page.getByRole('status').filter({ hasText: 'removed from your cart' })).toHaveCount(1);
    expect(rows(await getCart(page))).toEqual([{ sku: 'E2E-MOQ6', qty: 6 }]);
});

test('FE-75: a failed remove brings the row back with the error', async ({ page }) => {
    await saveCart(page, [{ sku: 'E2E-NO-MOQ', qty: 1 }, { sku: 'E2E-MOQ6', qty: 6 }]);
    await page.goto('/quick-order/cart');
    await expect(page.locator('.elme-cart__line')).toHaveCount(2);
    await page.route(/\/quick-order\/cart\/save/, (route) => route.fulfill({
        json: { success: false, message: 'E2E: the cart could not be saved.' },
    }));

    const remove = removeText(page, 'E2E-NO-MOQ');
    await remove.click();
    await confirmRemove(page);
    await expect(page.locator('.elme-toast-stack').getByText('E2E: the cart could not be saved.')).toBeVisible();
    await expect(page.locator('.elme-dust-canvas')).not.toBeAttached();
    await expect(cartLine(page, 'E2E-NO-MOQ')).toHaveCSS('opacity', '1');
    await expect(cartLine(page, 'E2E-NO-MOQ')).toHaveCSS('clip-path', 'none');
    await expect(page.locator('.elme-dust.is-collapsed')).toHaveCount(0);
    await expect(remove).toBeFocused();

    await page.unroute(/\/quick-order\/cart\/save/);
    expect(rows(await getCart(page))).toEqual([{ sku: 'E2E-NO-MOQ', qty: 1 }, { sku: 'E2E-MOQ6', qty: 6 }]);
});

test('FE-75: with reduced motion Remove fades the row out without dust', async ({ page }) => {
    await saveCart(page, [{ sku: 'E2E-NO-MOQ', qty: 1 }, { sku: 'E2E-MOQ6', qty: 6 }]);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/quick-order/cart');
    await expect(page.locator('.elme-cart__line')).toHaveCount(2);
    await page.evaluate(() => {
        window.e2eDust = false;
        new MutationObserver(() => {
            window.e2eDust = window.e2eDust || Boolean(document.querySelector('.elme-dust-canvas'));
        }).observe(document.body, { childList: true });
    });

    await removeText(page, 'E2E-NO-MOQ').click();
    await confirmRemove(page);
    await expect(page.locator('.elme-cart__line')).toHaveCount(1);
    expect(await page.evaluate(() => window.e2eDust)).toBe(false);
});

test('FE-75: the confirm dialog and the dust load only where something can be removed', async ({ page }) => {
    for (const path of ['/quick-order/cart', '/quick-order/template']) {
        await page.goto(path);
        expect(await page.evaluate(() => typeof window.elmeDust + typeof window.elmeConfirm), path)
            .toBe('objectfunction');
    }
    await page.goto('/');
    expect(await page.content()).not.toContain('elmeDust');
});
