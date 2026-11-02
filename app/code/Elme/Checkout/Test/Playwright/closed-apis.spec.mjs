import { expect, test } from '@playwright/test';

const EMAIL = 'e2e-checkout@example.com';
const PASSWORD = 'E2eCheckout!2026';
const PRICE_QUERY = '{products(search:"e2e",pageSize:1){items{sku price_range{minimum_price{final_price{value}}}}}}';

test('a guest gets no cart and no prices from REST', async ({ request }) => {
    for (const response of [
        await request.post('/rest/V1/guest-carts'),
        await request.post('/rest/default/V1/guest-carts'),
        await request.post('/rest/async/V1/guest-carts'),
        await request.get('/rest/V1/products-render-info?searchCriteria[pageSize]=1&storeId=1&currencyCode=AUD'),
        await request.post('/rest/V1/integration/customer/token', {
            data: { username: EMAIL, password: PASSWORD },
        }),
    ]) {
        expect(response.status(), response.url()).toBe(401);
    }
});

test('a guest gets no prices from GraphQL, by POST or GET', async ({ request }) => {
    for (const response of [
        await request.post('/graphql', { data: { query: PRICE_QUERY } }),
        await request.get(`/graphql?query=${encodeURIComponent(PRICE_QUERY)}`),
    ]) {
        expect(response.status(), response.url()).toBe(401);
        expect(await response.text()).not.toContain('price_range');
    }
});

test('integrations can still reach the admin token route', async ({ request }) => {
    const response = await request.post('/rest/V1/integration/admin/token', {
        data: { username: 'nobody', password: 'Wrong!Pass123' },
    });

    expect(response.status()).toBe(401);
    expect((await response.json()).message).toContain('sign-in was incorrect');
});

test('a logged-in customer cannot place an order past the checkout', async ({ page }) => {
    await page.goto('/customer/account/login');
    await page.locator('#email').fill(EMAIL);
    await page.locator('#pass').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    await expect(page).not.toHaveURL(/customer\/account\/login/);

    const response = await page.request.put('/rest/V1/carts/mine/order', {
        data: { paymentMethod: { method: 'checkmo' } },
    });

    expect(response.status()).toBeGreaterThanOrEqual(400);
    expect(await response.text()).not.toMatch(/^"?\d+"?$/);
});
