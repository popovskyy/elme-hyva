import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

// BE-19: how often a checkout action asks the carriers for rates. The counting test carrier
// (Model/Carrier/RateCounter) is switched on for this file and writes one line per request to
// var/log/elme_checkout_carrier.log; the actions are posted the way the page posts them.

// FE-79: uses place-order customer; keep off Roman's manual account (e2e-checkout@example.com)
const EMAIL = 'e2e-placeorder@example.com';
const PASSWORD = 'E2eCheckout!2026';
const PRODUCT_URL = '/e2e-checkout-product.html';
const COUNTER = 'elme_rate_counter';
const COUNTED = `${COUNTER}_counted`;
const PRICE = '12.00';
const LOG = fileURLToPath(new URL('../../../../../../var/log/elme_checkout_carrier.log', import.meta.url));

// Carrier requests an action may make; QA item 0 of the ticket
const LIMITS = {
    'page load': 1,
    'address change (new address)': 1,
    'address change (saved address)': 1,
    'delivery method pick': 1,
    'payment method pick': 0,
    'details save': 0,
    'billing address pick': 0,
    'place order': 2,
};

const magento = (...args) => execFileSync(
    'docker',
    ['exec', '-u', 'magento', '-w', '/var/www/html', 'elme_php', 'bin/magento', ...args],
    { stdio: 'pipe' },
).toString();

function configure(settings) {
    for (const [path, value] of Object.entries(settings)) {
        magento('config:set', `carriers/${COUNTER}/${path}`, String(value));
    }
    magento('cache:clean', 'config', 'full_page');
}

const regionId = (code) => Number(execFileSync(
    'docker',
    ['exec', 'elme_mariadb', 'mariadb', '-uelme', '-pelme', 'elme_magento', '-N', '-e',
        `SELECT region_id FROM directory_country_region WHERE country_id='AU' AND code='${code}'`],
    { stdio: 'pipe' },
).toString().trim());

const calls = () => (existsSync(LOG) ? readFileSync(LOG, 'utf8').split('\n').filter((line) => line.includes('collectRates')) : []);

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

async function post(page, action, data = {}) {
    const formKey = (await page.context().cookies()).find((cookie) => cookie.name === 'form_key')?.value;
    const answer = await page.request.post(`/elme_checkout/action/${action}`, {
        form: { form_key: formKey, data: JSON.stringify(data) },
    });

    return answer.json();
}

const placeOrderButton = (page) => page.locator('[data-checkout-section="agreements"] [data-place-order]');
const deliverySection = (page) => page.locator('[data-checkout-section="delivery"]');

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => configure({ active: 1, price: PRICE }));
test.afterAll(() => configure({ active: 0, price: PRICE }));

test('carrier requests per checkout action', async ({ page }) => {
    const counts = [];
    const measure = async (name, run) => {
        const before = calls().length;
        const result = await run();
        const lines = calls().slice(before);
        counts.push({ name, count: lines.length, paths: lines.map((line) => line.match(/action=(\S+)/)?.[1]) });

        return result;
    };

    await login(page);
    await addProduct(page);

    await measure('page load', () => openCheckout(page));
    const state = await post(page, 'state');
    const savedAddressId = state.state.sections.address.addresses[0].id;

    await measure('address change (new address)', async () => {
        const answer = await post(page, 'shippingaddress', {
            address: {
                firstname: 'Count', lastname: 'Me', company: 'Elme QA', street_1: '2 Rate Street',
                city: 'Sydney', region_id: regionId('NSW'), postcode: '2000', telephone: '0298765432',
            },
            save_to_book: false,
        });
        expect(answer.ok, JSON.stringify(answer)).toBe(true);
        expect(answer.state.sections.address.shipping.address.locality).toContain('2000');
        expect(answer.state.sections.delivery.rates.map((rate) => rate.code)).toContain(COUNTED);
    });

    await measure('address change (saved address)', async () => {
        const answer = await post(page, 'shippingaddress', { address_id: savedAddressId });
        expect(answer.ok, JSON.stringify(answer)).toBe(true);
        expect(answer.state.sections.address.shipping.address_id).toBe(savedAddressId);
    });

    await measure('delivery method pick', async () => {
        const answer = await post(page, 'shippingmethod', { carrier_code: COUNTER, method_code: 'counted' });
        expect(answer.ok, JSON.stringify(answer)).toBe(true);
        expect(answer.state.sections.delivery.selected).toBe(COUNTED);
    });

    await measure('payment method pick', async () => {
        const answer = await post(page, 'paymentmethod', { method: 'checkmo' });
        expect(answer.ok, JSON.stringify(answer)).toBe(true);
        expect(answer.state.sections.payment.selected).toBe('checkmo');
    });

    await measure('details save', async () => {
        const answer = await post(page, 'details', { delivery_instructions: 'Count the carrier requests' });
        expect(answer.ok, JSON.stringify(answer)).toBe(true);
    });

    await measure('billing address pick', async () => {
        const answer = await post(page, 'billingaddress', { address_id: savedAddressId });
        expect(answer.ok, JSON.stringify(answer)).toBe(true);
    });

    await measure('place order', async () => {
        const answer = await post(page, 'placeorder', { agreements: [] });
        expect(answer.ok, JSON.stringify(answer)).toBe(true);
        expect(answer.redirect).toContain('checkout/onepage/success');
    });

    const table = counts.map(({ name, count, paths }) => `${name.padEnd(32)} ${count}  ${paths.join(' ')}`).join('\n');
    console.log(`\nCarrier requests per action:\n${table}\n`);
    test.info().annotations.push({ type: 'carrier requests', description: table });

    for (const { name, count } of counts) {
        expect.soft(count, `${name}: ${count} carrier request(s), at most ${LIMITS[name]} allowed`).toBeLessThanOrEqual(LIMITS[name]);
    }
});

test('a delivery price that changed before Place order refuses the order once', async ({ page }) => {
    await login(page);
    await addProduct(page);
    await openCheckout(page);

    const counted = page.locator(`input[name="delivery_method"][value="${COUNTED}"]`);
    await counted.check();
    await expect(counted).toBeChecked();
    await expect(deliverySection(page)).toContainText(`$${PRICE}`);
    const payment = page.locator('[data-checkout-section="payment"] input[value="checkmo"]');
    if (await payment.count()) {
        if (!(await payment.isChecked())) {
            await payment.check();
        }
        await expect(payment).toBeChecked();
    } else {
        await expect(page.locator('[data-checkout-section="payment"] .elme-checkout-line__value')).toBeVisible();
        await expect(page.locator('[data-checkout-section="payment"].is-busy')).toHaveCount(0);
    }

    configure({ price: '15.00' });
    try {
        const before = calls().length;
        await placeOrderButton(page).click();
        await expect(deliverySection(page).locator('[role="alert"]')).toContainText('Your delivery options have changed');
        await expect(page).toHaveURL(/\/checkout/);
        await expect(deliverySection(page)).toContainText('$15.00');
        await expect(counted).toBeChecked();
        expect(calls().length - before, 'carrier requests of the refused place order').toBeLessThanOrEqual(2);

        await placeOrderButton(page).click();
        await expect(page).toHaveURL(/checkout\/onepage\/success/, { timeout: 60_000 });
    } finally {
        configure({ price: PRICE });
    }
});
