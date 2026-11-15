import { expect, test } from '@playwright/test';

// Roman 2026-11-15: Add to Cart in the recently viewed strip on a PLP says "Your session has expired. Please reload
// the page." The strip loads its tiles by ajax (Elme_RecentlyViewed strip-products.phtml). Hyva caches tile HTML
// (block_html, 1h) with the form key of the session that rendered it first, and only fixes form keys of the page
// it loads with (hyva.initFormKey), so tiles added later must get the same treatment.
const EMAIL = 'e2e-qty@example.com';
const PASSWORD = 'E2eCheckout!2026';
// Any category of the local catalog that lists products and shows the strip
const PLP = process.env.E2E_PLP || '/furniture.html';

async function login(page) {
    await page.goto('/customer/account/login');
    await page.locator('#email').fill(EMAIL);
    await page.locator('#pass').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    await expect(page).not.toHaveURL(/customer\/account\/login/);
}

async function viewAll(page, urls) {
    for (const url of urls) {
        await page.goto(url);
        await page.waitForLoadState('load');
    }
}

const stripTiles = (page) => page.locator('.elme-rv--products .elme-rv-products__slide');

test('the recently viewed strip posts the form key of this session', async ({ browser }) => {
    // Session A gets the strip first, so the tile cache holds A's form key
    const first = await browser.newContext();
    const a = await first.newPage();
    await login(a);
    await a.goto(PLP);
    const productUrls = await a.locator('.products-grid .elme-card__name, .product-items .elme-card__name')
        .evaluateAll((links) => links.slice(0, 3).map((link) => link.getAttribute('href')));
    expect(productUrls.length).toBeGreaterThan(0);
    await viewAll(a, productUrls);
    await a.goto(PLP);
    await expect(stripTiles(a).first()).toBeVisible();
    await first.close();

    // Session B views those products, then gets the same tiles in the strip
    const second = await browser.newContext();
    const b = await second.newPage();
    await login(b);
    await viewAll(b, productUrls);
    await b.goto(PLP);
    await expect(stripTiles(b).first()).toBeVisible();

    const formKey = await b.evaluate(() => hyva.getFormKey());
    const stripKeys = await stripTiles(b).locator('input[name="form_key"]')
        .evaluateAll((inputs) => inputs.map((input) => input.value));
    expect(stripKeys.length).toBeGreaterThan(0);
    expect(new Set(stripKeys)).toEqual(new Set([formKey]));

    // And a real add from the strip is not refused
    const simple = stripTiles(b).filter({ hasNot: b.locator('[name^="super_attribute"]') }).first();
    const answer = b.waitForResponse((response) => response.url().includes('/checkout/cart/add'));
    await simple.locator('.btn-atc').click();
    await answer;
    const sections = await (await b.request.get('/customer/section/load/?sections=messages')).json();
    const texts = (sections.messages.messages || []).map((message) => message.text).join(' ');
    expect(texts).not.toContain('session has expired');
    await second.close();
});
