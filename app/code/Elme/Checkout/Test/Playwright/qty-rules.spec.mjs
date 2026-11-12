import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

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

// What a redirecting controller told the customer, without a page render reading the cookie first
const postAndReadMessages = async (page, url, form) => {
    await page.context().clearCookies({ name: 'mage-messages' });
    await page.request.post(url, { form: { form_key: await formKey(page), ...form }, maxRedirects: 0 });
    const cookie = (await page.context().cookies()).find((item) => item.name === 'mage-messages');
    return cookie ? JSON.parse(decodeURIComponent(cookie.value)).map((message) => message.text) : [];
};

const addToCart = (page, id, qty) => postAndReadMessages(page, '/checkout/cart/add', { product: id, qty: String(qty) });

// BE-16: add to cart fills the Quick Order cart (cart_list), [{sku, qty}]
const cartRows = async (page) => (await (await page.request.get('/quick-order/cart/get')).json())
    .cart.items.map(({ sku, qty }) => ({ sku, qty }));

const saveCartRows = async (page, items) => page.request.post('/quick-order/cart/save', {
    data: { form_key: await formKey(page), mode: 'replace', items },
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

// The quote is built from cart_list when checkout opens; the quote paths below (cart update, mini cart, reorder)
// are still reachable and still have to refuse
async function fillQuote(page, items) {
    await saveCartRows(page, items);
    await page.goto('/checkout');
    await expect(page.locator('[data-checkout-section="address"]')).toContainText('Deliver to:');
}

// Quote lines [{sku, qty, item_id}] straight from the server
const cartItems = async (page) => {
    const answer = await page.request.get('/customer/section/load?sections=cart&force_new_section_timestamp=true');
    return (await answer.json()).cart.items.map(({ product_sku: sku, qty, item_id: itemId }) => ({ sku, qty: Number(qty), itemId }));
};

const emptyCart = async (page) => {
    for (const item of await cartItems(page)) {
        await page.request.post('/checkout/sidebar/removeItem', { form: { form_key: await formKey(page), item_id: item.itemId } });
    }
};

test.beforeEach(async ({ page }) => {
    await login(page);
    await emptyCart(page);
    await saveCartRows(page, []);
    setMoq('E2E-MOQ-LATER', 1);
});

test('cart/add: 4 and 7 are refused with the multiples message, 12 is accepted', async ({ page }) => {
    const id = await productId(page, 'E2E-MOQ6');

    for (const qty of [4, 7]) {
        expect(await addToCart(page, id, qty)).toEqual([MOQ_MESSAGE]);
        expect(await cartRows(page)).toEqual([]);
    }

    await addToCart(page, id, 12);
    expect(await cartRows(page)).toEqual([{ sku: 'E2E-MOQ6', qty: 12 }]);
});

test('cart/add: no qty adds the MOQ, and a second add may not break the multiple', async ({ page }) => {
    const id = await productId(page, 'E2E-MOQ6');

    await postAndReadMessages(page, '/checkout/cart/add', { product: id });
    expect(await cartRows(page)).toMatchObject([{ qty: 6 }]);

    expect(await addToCart(page, id, 7)).toEqual([MOQ_MESSAGE]);
    expect(await cartRows(page)).toMatchObject([{ qty: 6 }]);

    await addToCart(page, id, 6);
    expect(await cartRows(page)).toMatchObject([{ qty: 12 }]);
});

test('a product without MOQ takes any whole qty', async ({ page }) => {
    await addToCart(page, await productId(page, 'E2E-NO-MOQ'), 7);

    expect(await cartRows(page)).toEqual([{ sku: 'E2E-NO-MOQ', qty: 7 }]);
});

test('cart page update: refused with the same message, qty not recalculated', async ({ page }) => {
    await fillQuote(page, [{ sku: 'E2E-MOQ6', qty: 12 }]);
    const [{ itemId }] = await cartItems(page);

    for (const qty of [4, 7]) {
        const messages = await postAndReadMessages(page, '/checkout/cart/updatePost', {
            update_cart_action: 'update_qty',
            [`cart[${itemId}][qty]`]: String(qty),
        });
        expect(messages).toEqual([MOQ_MESSAGE]);
        expect(await cartItems(page)).toMatchObject([{ qty: 12 }]);
    }

    await postAndReadMessages(page, '/checkout/cart/updatePost', {
        update_cart_action: 'update_qty',
        [`cart[${itemId}][qty]`]: '18',
    });
    expect(await cartItems(page)).toMatchObject([{ qty: 18 }]);
});

test('ajax cart update and mini cart update: refused with the same message', async ({ page }) => {
    await fillQuote(page, [{ sku: 'E2E-MOQ6', qty: 12 }]);
    const [{ itemId }] = await cartItems(page);
    const form_key = await formKey(page);

    for (const qty of [4, 7]) {
        const cart = await page.request.post('/checkout/cart/updateItemQty', {
            form: { form_key, [`cart[${itemId}][qty]`]: String(qty) },
        });
        const cartAnswer = await cart.json();
        expect(cartAnswer.success).toBe(false);
        expect(cartAnswer.error_message).toContain(MOQ_MESSAGE);

        const miniCart = await page.request.post('/checkout/sidebar/updateItemQty', {
            form: { form_key, item_id: itemId, item_qty: String(qty) },
        });
        expect(await miniCart.json()).toMatchObject({ success: false, error_message: MOQ_MESSAGE });

        expect(await cartItems(page)).toMatchObject([{ qty: 12 }]);
    }

    const miniCart = await page.request.post('/checkout/sidebar/updateItemQty', {
        form: { form_key, item_id: itemId, item_qty: '6' },
    });
    expect(await miniCart.json()).toMatchObject({ success: true });
    expect(await cartItems(page)).toMatchObject([{ qty: 6 }]);
});

// The customer token route is closed (Elme_LoginOnly), so these use the customer's session, which REST accepts on AJAX only
test('REST and GraphQL refuse with the same message', async ({ page }) => {
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };
    const quoteId = await (await page.request.post('/rest/V1/carts/mine', { headers })).json();
    const rest = await page.request.post('/rest/V1/carts/mine/items', {
        headers,
        data: { cartItem: { sku: 'E2E-MOQ6', qty: 7, quote_id: String(quoteId) } },
    });
    expect((await rest.json()).message).toBe(MOQ_MESSAGE);

    const graphql = async (query) => (await page.request.post('/graphql', { data: { query } })).json();
    const cartId = (await graphql('{ customerCart { id } }')).data.customerCart.id;
    const added = await graphql(`mutation { addProductsToCart(cartId: "${cartId}", cartItems: [{sku: "E2E-MOQ6", quantity: 7}]) { user_errors { message } } }`);
    expect(added.data.addProductsToCart.user_errors.map((error) => error.message)).toEqual([MOQ_MESSAGE]);

    await graphql(`mutation { addProductsToCart(cartId: "${cartId}", cartItems: [{sku: "E2E-MOQ6", quantity: 6}]) { user_errors { message } } }`);
    const uid = (await graphql('{ customerCart { itemsV2 { items { uid } } } }')).data.customerCart.itemsV2.items[0].uid;
    const updated = await graphql(`mutation { updateCartItems(input: {cart_id: "${cartId}", cart_items: [{cart_item_uid: "${uid}", quantity: 7}]}) { errors { message } } }`);
    expect(updated.data.updateCartItems.errors[0].message).toContain(MOQ_MESSAGE);
    expect(await cartItems(page)).toMatchObject([{ qty: 6 }]);
});

async function placeOrder(page) {
    await page.goto('/checkout');
    await expect(page.locator('[data-checkout-section="address"]')).toContainText('Deliver to:');
    const deliveryRadio = page.locator('input[name="delivery_method"][value="flatrate_flatrate"]');
    if (await deliveryRadio.count()) {
        if (!(await deliveryRadio.isChecked())) {
            await deliveryRadio.check();
        }
        await expect(deliveryRadio).toBeChecked();
    } else {
        await expect(page.locator('[data-checkout-section="delivery"] .elme-checkout-line__value')).toBeVisible();
        await expect(page.locator('[data-checkout-section="delivery"].is-busy')).toHaveCount(0);
    }
    const paymentRadio = page.locator('[data-checkout-section="payment"] input[value="checkmo"]');
    if (await paymentRadio.count()) {
        if (!(await paymentRadio.isChecked())) {
            await paymentRadio.check();
        }
        await expect(paymentRadio).toBeChecked();
    } else {
        await expect(page.locator('[data-checkout-section="payment"] .elme-checkout-line__value')).toBeVisible();
        await expect(page.locator('[data-checkout-section="payment"].is-busy')).toHaveCount(0);
    }
    await page.locator('[data-checkout-section="agreements"] [data-place-order]').click();
    await expect(page).toHaveURL(/checkout\/onepage\/success/, { timeout: 60_000 });
}

test('reorder: a line that is no longer a multiple is refused, the others are added', async ({ page }) => {
    const later = await productId(page, 'E2E-MOQ-LATER');
    await addToCart(page, later, 7);
    await addToCart(page, await productId(page, 'E2E-NO-MOQ'), 2);
    await placeOrder(page);
    setMoq('E2E-MOQ-LATER', 6);

    await page.goto('/sales/order/history');
    const orderId = (await page.locator('a[href*="/sales/order/view/order_id/"]').first().getAttribute('href')).match(/order_id\/(\d+)/)[1];
    const messages = await postAndReadMessages(page, `/sales/order/reorder/order_id/${orderId}/`, {});

    expect(messages.join(' ')).toContain(MOQ_MESSAGE);
    expect(await cartItems(page)).toMatchObject([{ sku: 'E2E-NO-MOQ', qty: 2 }]);
});

test('reorder: a refused line leaves the same product already in the cart alone', async ({ page }) => {
    const later = await productId(page, 'E2E-MOQ-LATER');
    await addToCart(page, later, 7);
    await placeOrder(page);
    setMoq('E2E-MOQ-LATER', 6);
    await fillQuote(page, [{ sku: 'E2E-MOQ-LATER', qty: 6 }]);

    await page.goto('/sales/order/history');
    const orderId = (await page.locator('a[href*="/sales/order/view/order_id/"]').first().getAttribute('href')).match(/order_id\/(\d+)/)[1];
    const messages = await postAndReadMessages(page, `/sales/order/reorder/order_id/${orderId}/`, {});

    expect(messages.join(' ')).toContain(MOQ_MESSAGE);
    expect(await cartItems(page)).toMatchObject([{ sku: 'E2E-MOQ-LATER', qty: 6 }]);
});

test('a cart line that became invalid blocks checkout until it is corrected', async ({ page }) => {
    await addToCart(page, await productId(page, 'E2E-MOQ-LATER'), 7);
    setMoq('E2E-MOQ-LATER', 6);

    await page.goto('/checkout');
    await expect(page).toHaveURL(/quick-order\/cart/);
    await expect(page.locator('#maincontent')).toContainText(MOQ_MESSAGE);

    await saveCartRows(page, [{ sku: 'E2E-MOQ-LATER', qty: 12 }]);
    await page.goto('/checkout');
    await expect(page.locator('[data-checkout-section="address"]')).toContainText('Deliver to:');
});

test('the customer sees the refusal on the page', async ({ page }) => {
    await addToCart(page, await productId(page, 'E2E-MOQ6'), 7);

    await page.goto('/e2e-moq6.html');
    await expect(page.getByText(MOQ_MESSAGE)).toBeVisible();
});
