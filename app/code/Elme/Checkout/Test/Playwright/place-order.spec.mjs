import { expect, test } from '@playwright/test';

const EMAIL = 'e2e-checkout@example.com';
const PASSWORD = 'E2eCheckout!2026';
const PRODUCT_URL = '/e2e-checkout-product.html';
const MAILPIT = process.env.MAILPIT_URL || 'http://localhost:8028';

async function login(page) {
    await page.goto('/customer/account/login');
    await page.locator('#email').fill(EMAIL);
    await page.locator('#pass').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    await expect(page).not.toHaveURL(/customer\/account\/login/);
}

async function addProduct(page) {
    await page.goto(PRODUCT_URL);
    await page.locator('#product-addtocart-button').click();
    await expect(page.locator('#menu-cart-icon')).toContainText('1', { timeout: 15_000 });
}

async function openCheckout(page) {
    await page.goto('/checkout');
    await expect(page.locator('[data-checkout-section="address"]')).toContainText('Deliver to:');
}

async function chooseMethods(page) {
    for (const radio of [
        page.locator('input[name="delivery_method"][value="flatrate_flatrate"]'),
        page.locator('[data-checkout-section="payment"] input[value="checkmo"]'),
    ]) {
        await expect(radio).toBeVisible();
        if (!(await radio.isChecked())) {
            await radio.check();
        }
        await expect(radio).toBeChecked();
    }
}

const orderNumber = (text) => text.match(/\d{6,}/)?.[0];

test('login, add a product, saved address, place order, success page', async ({ page, request }) => {
    await login(page);
    await addProduct(page);
    await openCheckout(page);
    await chooseMethods(page);

    await page.locator('[data-checkout-section="agreements"] [data-place-order]').click();

    await expect(page).toHaveURL(/checkout\/onepage\/success/, { timeout: 60_000 });
    const number = orderNumber(await page.locator('#maincontent').innerText());
    expect(number).toBeTruthy();
    // BE-16: the placed order empties the Quick Order cart
    expect((await (await page.request.get('/quick-order/cart/get')).json()).cart.items).toEqual([]);

    await page.goto('/sales/order/history');
    await expect(page.locator('#maincontent')).toContainText(number);

    await expect(async () => {
        const answer = await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:${EMAIL} ${number}`)}`);
        expect((await answer.json()).messages_count).toBeGreaterThan(0);
    }).toPass({ timeout: 30_000 });
});

async function findEmailHtml(request, number) {
    let id;
    await expect(async () => {
        const answer = await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:${EMAIL} ${number}`)}`);
        id = (await answer.json()).messages[0]?.ID;
        expect(id).toBeTruthy();
    }).toPass({ timeout: 30_000 });

    return (await (await request.get(`${MAILPIT}/api/v1/message/${id}`)).json()).HTML;
}

const detailsSaved = (page) => page.waitForResponse((answer) => answer.url().includes('elme_checkout/action/details'));
const instructionsField = (page) => page.locator('#elme-checkout-instructions');
const placeOrderButton = (page) => page.locator('[data-checkout-section="agreements"] [data-place-order]');

test('order details are kept, checked, and reach My Orders and the email', async ({ page, request }) => {
    await login(page);
    await addProduct(page);
    await openCheckout(page);
    await chooseMethods(page);
    await expect(page.locator('#elme-checkout-reference')).toHaveCount(0);

    const saved = detailsSaved(page);
    await instructionsField(page).fill('  <b>Front door</b>\nRing twice  ');
    await instructionsField(page).blur();
    await saved;
    await expect(instructionsField(page)).toHaveValue('<b>Front door</b>\nRing twice');
    await expect(page.locator('#elme-checkout-instructions-counter')).toHaveText('28 / 250');
    await page.waitForLoadState('networkidle');

    await openCheckout(page);
    await expect(instructionsField(page)).toHaveValue('<b>Front door</b>\nRing twice');

    await instructionsField(page).fill('');
    await instructionsField(page).focus();
    await page.keyboard.insertText('y'.repeat(260));
    await expect(instructionsField(page)).toHaveValue('y'.repeat(250));
    await expect(page.locator('#elme-checkout-instructions-counter')).toHaveText('250 / 250');

    // maxlength stops a person, so only a tampered page gets here: the server still refuses it and stops the order
    await instructionsField(page).evaluate((field) => field.removeAttribute('maxlength'));
    await instructionsField(page).fill('z'.repeat(251));
    await expect(page.locator('#elme-checkout-instructions-error')).toContainText('up to 250 characters');
    await expect(instructionsField(page)).toHaveAttribute('aria-invalid', 'true');

    await placeOrderButton(page).click();
    await expect(instructionsField(page)).toBeFocused();
    await expect(page).toHaveURL(/\/checkout/);
    await expect(page.locator('#elme-checkout-instructions-error')).toBeVisible();

    await instructionsField(page).fill('<b>Front door</b>\nRing twice');
    await expect(page.locator('#elme-checkout-instructions-error')).toBeHidden();
    await chooseMethods(page);
    await placeOrderButton(page).click();
    await expect(page).toHaveURL(/checkout\/onepage\/success/, { timeout: 60_000 });
    const number = orderNumber(await page.locator('#maincontent').innerText());

    await page.goto('/sales/order/history');
    await page.locator('#maincontent').getByRole('link', { name: /view/i }).first().click();
    const details = page.locator('[data-order-details]');
    await expect(details).toContainText('<b>Front door</b>');
    await expect(details.locator('b')).toHaveCount(0);

    const email = await findEmailHtml(request, number);
    expect(email).toContain('&lt;b&gt;Front door&lt;/b&gt;');
    expect(email).toContain('Ring twice');
});

test('the last change goes with the order, even with the focus still in the field', async ({ page }) => {
    const instructions = `Gate code ${Date.now()}`;

    await login(page);
    await addProduct(page);
    await openCheckout(page);
    await chooseMethods(page);

    await instructionsField(page).fill(instructions);
    await placeOrderButton(page).dispatchEvent('click');

    await expect(page).toHaveURL(/checkout\/onepage\/success/, { timeout: 60_000 });
    await page.goto('/sales/order/history');
    await page.locator('#maincontent').getByRole('link', { name: /view/i }).first().click();
    await expect(page.locator('[data-order-details]')).toContainText(instructions);
});

test('a long link in the instructions wraps in My Orders, the email and admin', async ({ page, request, browser }) => {
    const link = `https://www.google.com/maps/place/${'a'.repeat(216)}`;
    const hasSideScroll = (target) => target.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );

    await login(page);
    await addProduct(page);
    await openCheckout(page);
    await chooseMethods(page);
    await instructionsField(page).fill(link);
    await placeOrderButton(page).click();
    await expect(page).toHaveURL(/checkout\/onepage\/success/, { timeout: 60_000 });
    const number = orderNumber(await page.locator('#maincontent').innerText());

    await page.setViewportSize({ width: 390, height: 800 });
    await page.goto('/sales/order/history');
    await page.locator('#maincontent').getByRole('link', { name: /view/i }).first().click();
    await expect(page.locator('[data-order-details]')).toContainText(link);
    expect(await hasSideScroll(page)).toBe(false);
    const orderId = page.url().match(/order_id\/(\d+)/)[1];

    const phone = await browser.newPage({ viewport: { width: 390, height: 800 } });
    await phone.setContent(await findEmailHtml(request, number));
    expect(await hasSideScroll(phone)).toBe(false);
    await phone.close();

    test.skip(!process.env.ADMIN_PASSWORD, 'ADMIN_USER and ADMIN_PASSWORD are needed for the admin check');
    const admin = await browser.newPage({
        baseURL: test.info().project.use.baseURL,
        ignoreHTTPSErrors: true,
        viewport: { width: 1440, height: 1000 },
    });
    await admin.goto('/admin');
    await admin.locator('#username').fill(process.env.ADMIN_USER || 'admin');
    await admin.locator('#login').fill(process.env.ADMIN_PASSWORD);
    await admin.locator('.action-login').click();
    await admin.waitForLoadState('load');
    await admin.goto(`/admin/sales/order/view/order_id/${orderId}`);
    await expect(admin.locator('.order-account-information')).toContainText(link);
    expect(await hasSideScroll(admin)).toBe(false);
    await admin.close();
});

test('empty order details leave no empty labels', async ({ page, request }) => {
    await login(page);
    await addProduct(page);
    await openCheckout(page);
    await chooseMethods(page);
    await expect(instructionsField(page)).toHaveValue('');

    await placeOrderButton(page).click();
    await expect(page).toHaveURL(/checkout\/onepage\/success/, { timeout: 60_000 });
    const number = orderNumber(await page.locator('#maincontent').innerText());

    await page.goto('/sales/order/history');
    await page.locator('#maincontent').getByRole('link', { name: /view/i }).first().click();
    await expect(page.locator('#maincontent')).toContainText(number);
    await expect(page.locator('[data-order-details]')).toHaveCount(0);

    const email = await findEmailHtml(request, number);
    expect(email).not.toContain('Delivery instructions');
});

test('a double click on Place order creates one order', async ({ page }) => {
    await login(page);
    await addProduct(page);
    await openCheckout(page);
    await chooseMethods(page);

    const placeOrderCalls = [];
    page.on('request', (call) => {
        if (call.url().includes('elme_checkout/action/placeorder')) {
            placeOrderCalls.push(call.url());
        }
    });

    await page.locator('[data-checkout-section="agreements"] [data-place-order]').dblclick();

    await expect(page).toHaveURL(/checkout\/onepage\/success/, { timeout: 60_000 });
    expect(placeOrderCalls).toHaveLength(1);
});
