import { execFileSync } from 'node:child_process';

const inPhpContainer = (...command) => execFileSync(
    'docker',
    ['exec', '-u', 'magento', '-w', '/var/www/html', 'elme_php', ...command],
    { stdio: 'inherit' }
);

// A clean cart and the customer and product the tests need; without the indexers the new product is out of stock
export default function globalSetup() {
    inPhpContainer('php', 'app/code/Elme/Checkout/Test/Playwright/seed.php');
    inPhpContainer('bin/magento', 'indexer:reindex', 'inventory', 'cataloginventory_stock', 'catalog_product_price');
    inPhpContainer('bin/magento', 'cache:flush', 'full_page');
}
