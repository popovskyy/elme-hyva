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

    await page.goto('/sales/order/history');
    await expect(page.locator('#maincontent')).toContainText(number);

    await expect(async () => {
        const answer = await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:${EMAIL} ${number}`)}`);
        expect((await answer.json()).messages_count).toBeGreaterThan(0);
    }).toPass({ timeout: 30_000 });
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
