<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Content Studio type `stockist_cta` (FE-61 / FE-67): dual CTA band for trade account apply.
 */
class StockistCta implements ArgumentInterface
{
    /**
     * @param array<string, mixed> $content
     */
    public function getHeading(array $content): string
    {
        return $this->text($content, 'heading');
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getText(array $content): string
    {
        return $this->text($content, 'text');
    }

    /**
     * Guest primary (Create an Account). Caller swaps to My Account when logged in.
     *
     * @param array<string, mixed> $content
     * @return array{label: string, link: string}|null
     */
    public function getPrimaryButton(array $content): ?array
    {
        return $this->button($content, 'primary_label', 'primary_link');
    }

    /**
     * Secondary always shown (Talk to our team → Meet the Team).
     *
     * @param array<string, mixed> $content
     * @return array{label: string, link: string}|null
     */
    public function getSecondaryButton(array $content): ?array
    {
        return $this->button($content, 'secondary_label', 'secondary_link');
    }

    /**
     * @param array<string, mixed> $content
     * @return array{label: string, link: string}|null
     */
    private function button(array $content, string $labelKey, string $linkKey): ?array
    {
        $label = $this->text($content, $labelKey);
        $link = $this->text($content, $linkKey);
        if ($label === '' || $link === '') {
            return null;
        }

        return ['label' => $label, 'link' => $link];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
