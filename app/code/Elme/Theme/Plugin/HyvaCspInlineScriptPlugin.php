<?php

declare(strict_types=1);

namespace Elme\Theme\Plugin;

use Hyva\Theme\Model\HtmlPageContent;
use Hyva\Theme\ViewModel\HyvaCsp;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;

/**
 * FE-82: keep a script-src hash in Magento\Csp\Model\BlockCache even when the
 * storefront still allows unsafe-inline (report-only).
 *
 * HyvaCsp::registerInlineScript() returns early when inline is allowed, so
 * Snowdog Menu HTML (cache_lifetime one year) was stored without CSP policies.
 * Checkout then reused that HTML under an enforced CSP and blocked the scripts.
 * The same would break every page once storefront CSP is enforced if the cache
 * still held those plain entries.
 */
class HyvaCspInlineScriptPlugin
{
    public function __construct(
        private readonly DynamicCollector $dynamicCspCollector,
        private readonly HtmlPageContent $htmlPageContent
    ) {
    }

    public function aroundRegisterInlineScript(HyvaCsp $subject, callable $proceed): void
    {
        if ($subject->getScriptSrcPolicy()->isInlineAllowed()) {
            $this->addInlineScriptHashToCspHeader();
            return;
        }

        $proceed();
    }

    private function addInlineScriptHashToCspHeader(): void
    {
        $pageContent = rtrim((string) ob_get_contents());
        $scriptContent = $this->htmlPageContent->extractLastElementContent($pageContent, 'script');
        if ($scriptContent === '') {
            return;
        }

        $this->dynamicCspCollector->add(
            new FetchPolicy(
                'script-src',
                false,
                [],
                [],
                false,
                false,
                false,
                [],
                [base64_encode(hash('sha256', $scriptContent, true)) => 'sha256']
            )
        );
    }
}
