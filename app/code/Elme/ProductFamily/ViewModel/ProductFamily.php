<?php

declare(strict_types=1);

namespace Elme\ProductFamily\ViewModel;

use Elme\ProductFamily\Model\AxisBuilder;
use Elme\ProductFamily\Model\FamilyLoader;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogAttribute;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Swatches\Helper\Data as SwatchesHelper;
use Magento\Swatches\Helper\Media as SwatchMedia;
use Magento\Swatches\Model\Swatch;

/**
 * FE-28: swatches of a product family for the PDP and the product card. Every option is a link to a
 * sibling simple product (swatch = navigation). Colour chips show, in this order: the member's
 * swatch_image, the option's admin swatch (colour or image), the member's product image.
 *
 * Hyvä adds getIdentities() to the page cache tags (every member + the family tag), so a page is
 * purged when a sibling changes or a product joins / leaves the family (Plugin\AddFamilyCacheTag).
 */
class ProductFamily implements ArgumentInterface, IdentityInterface
{
    private const SWATCH_PX = 112;

    /** @var array<int, array> */
    private array $axesByProduct = [];

    /** @var array<string, array{labels: array<string, string>, rank: array<string, int>}> */
    private array $optionsByAttribute = [];

    /** @var array<string, string> */
    private array $identities = [];

    /**
     * @param string[] $visualAxes axis codes drawn as colour chips
     */
    public function __construct(
        private readonly FamilyLoader $familyLoader,
        private readonly AxisBuilder $axisBuilder,
        private readonly ImageHelper $imageHelper,
        private readonly SwatchesHelper $swatchesHelper,
        private readonly SwatchMedia $swatchMedia,
        private readonly array $visualAxes = []
    ) {
    }

    /**
     * Axes with at least two values, in family_axes order. Each option:
     * value, label, selected, available, url, note (why it is crossed out), image, color.
     *
     * @return array<int, array{code: string, label: string, visual: bool, selectedLabel: string, options: array<int, array>}>
     */
    public function getAxes(Product $product): array
    {
        $productId = (int) $product->getId();
        if (isset($this->axesByProduct[$productId])) {
            return $this->axesByProduct[$productId];
        }

        $members = $this->familyLoader->getMembers((string) $product->getData(FamilyLoader::ATTR_CODE));
        if (count($members) < 2 || !isset($members[$productId])) {
            return $this->axesByProduct[$productId] = [];
        }

        $familyTag = FamilyLoader::cacheTag((string) $product->getData(FamilyLoader::ATTR_CODE));
        $this->identities[$familyTag] = $familyTag;
        $definitions = $this->axisDefinitions($members[$productId]);
        $rows = [];
        foreach ($members as $id => $member) {
            $values = [];
            foreach ($definitions as $definition) {
                $values[$definition['code']] = (string) $member->getData($definition['code']);
            }
            $sort = $member->getData(FamilyLoader::ATTR_SORT);
            $rows[$id] = [
                'sku' => (string) $member->getSku(),
                'sort' => $sort === null || $sort === '' ? null : (int) $sort,
                'values' => $values,
            ];
            $this->identities[$id] = Product::CACHE_TAG . '_' . $id;
        }

        $axes = $this->axisBuilder->build($definitions, $rows, $productId);
        $labelsByCode = array_column($definitions, 'labels', 'code');
        $current = $rows[$productId]['values'];

        foreach ($axes as &$axis) {
            $optionSwatches = $axis['visual'] ? $this->optionSwatches(array_column($axis['options'], 'value')) : [];
            foreach ($axis['options'] as &$option) {
                $member = $members[$option['memberId']];
                $option['url'] = (string) $member->getProductUrl();
                $option['note'] = $this->unavailableNote($option['missing'], $current, $labelsByCode);
                $option['image'] = '';
                $option['color'] = '';
                if ($axis['visual']) {
                    $option = array_merge($option, $this->chipFill($member, $optionSwatches[$option['value']] ?? null));
                }
            }
            unset($option);
        }
        unset($axis);

        return $this->axesByProduct[$productId] = $axes;
    }

    /**
     * The product card shows the first colour axis as small chips (selected one always visible), or,
     * for a family without colours, how many options the first axis has.
     *
     * @return array{label: string, visual: bool, count: int, options: array<int, array>, more: int}|null
     */
    public function getCardSwatches(Product $product, int $limit = 4): ?array
    {
        $axes = $this->getAxes($product);
        if (!$axes) {
            return null;
        }

        foreach ($axes as $axis) {
            if (!$axis['visual']) {
                continue;
            }
            $options = $axis['options'];
            $visible = array_slice($options, 0, $limit);
            $selectedIndex = array_search(true, array_column($options, 'selected'), true);
            if ($selectedIndex !== false && $selectedIndex >= $limit) {
                $visible[$limit - 1] = $options[$selectedIndex];
            }
            return [
                'label' => $axis['label'],
                'visual' => true,
                'count' => count($options),
                'options' => $visible,
                'more' => max(0, count($options) - $limit),
            ];
        }

        return [
            'label' => $axes[0]['label'],
            'visual' => false,
            'count' => count($axes[0]['options']),
            'options' => [],
            'more' => 0,
        ];
    }

    public function getIdentities(): array
    {
        return array_values($this->identities);
    }

    /**
     * family_axes of the current product (comma separated codes) filtered to known axes; when it is
     * empty, every known axis (an axis whose value does not vary in the family is dropped later).
     *
     * @return array<int, array{code: string, label: string, visual: bool, labels: array<string, string>, rank: array<string, int>}>
     */
    private function axisDefinitions(Product $current): array
    {
        $attributes = $this->familyLoader->getAxisAttributes();
        $codes = array_map('trim', explode(',', strtolower((string) $current->getData(FamilyLoader::ATTR_AXES))));
        $codes = array_values(array_unique(array_filter($codes, static fn (string $code): bool => isset($attributes[$code]))));
        if (!$codes) {
            $codes = array_keys($attributes);
        }

        $definitions = [];
        foreach ($codes as $code) {
            $attribute = $attributes[$code];
            $options = $this->attributeOptions($attribute);
            $definitions[] = [
                'code' => $code,
                'label' => (string) ($attribute->getStoreLabel() ?: $attribute->getDefaultFrontendLabel() ?: $code),
                'visual' => in_array($code, $this->visualAxes, true) || $this->isVisualSwatch($attribute),
                'labels' => $options['labels'],
                'rank' => $options['rank'],
            ];
        }
        return $definitions;
    }

    /**
     * @return array{labels: array<string, string>, rank: array<string, int>}
     */
    private function attributeOptions(AbstractAttribute $attribute): array
    {
        $code = (string) $attribute->getAttributeCode();
        if (isset($this->optionsByAttribute[$code])) {
            return $this->optionsByAttribute[$code];
        }
        $labels = [];
        $rank = [];
        try {
            foreach ($attribute->getSource()->getAllOptions(false) as $index => $option) {
                $value = (string) ($option['value'] ?? '');
                if ($value === '' || is_array($option['value'])) {
                    continue;
                }
                $labels[$value] = trim((string) $option['label']);
                $rank[$value] = (int) $index;
            }
        } catch (\Exception) {
            // no source: values fall back to their raw id as label
        }
        return $this->optionsByAttribute[$code] = ['labels' => $labels, 'rank' => $rank];
    }

    private function isVisualSwatch(AbstractAttribute $attribute): bool
    {
        try {
            return $attribute instanceof CatalogAttribute && $this->swatchesHelper->isVisualSwatch($attribute);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Admin swatches (Stores > Attributes, visual swatch) per option id.
     *
     * @param string[] $optionIds
     * @return array<string, array{type: int, value: string}>
     */
    private function optionSwatches(array $optionIds): array
    {
        try {
            $swatches = $this->swatchesHelper->getSwatchesByOptionsId($optionIds);
        } catch (\Exception) {
            return [];
        }
        $result = [];
        foreach ($swatches as $optionId => $swatch) {
            $result[(string) $optionId] = ['type' => (int) ($swatch['type'] ?? -1), 'value' => (string) ($swatch['value'] ?? '')];
        }
        return $result;
    }

    /**
     * @param array{type: int, value: string}|null $optionSwatch
     * @return array{image: string, color: string}
     */
    private function chipFill(Product $member, ?array $optionSwatch): array
    {
        $swatchFile = (string) $member->getData('swatch_image');
        if ($swatchFile !== '' && $swatchFile !== 'no_selection') {
            return ['image' => $this->imageUrl($member, 'swatch_image', $swatchFile), 'color' => ''];
        }
        if ($optionSwatch && $optionSwatch['value'] !== '') {
            if ($optionSwatch['type'] === Swatch::SWATCH_TYPE_VISUAL_COLOR
                && preg_match('/^#[0-9a-f]{3,8}$/i', $optionSwatch['value'])) {
                return ['image' => '', 'color' => $optionSwatch['value']];
            }
            if ($optionSwatch['type'] === Swatch::SWATCH_TYPE_VISUAL_IMAGE) {
                return [
                    'image' => (string) $this->swatchMedia->getSwatchAttributeImage(
                        Swatch::SWATCH_THUMBNAIL_NAME,
                        $optionSwatch['value']
                    ),
                    'color' => '',
                ];
            }
        }
        foreach (['small_image', 'thumbnail'] as $role) {
            $file = (string) $member->getData($role);
            if ($file !== '' && $file !== 'no_selection') {
                return ['image' => $this->imageUrl($member, $role, $file), 'color' => ''];
            }
        }
        return ['image' => '', 'color' => ''];
    }

    private function imageUrl(Product $member, string $role, string $file): string
    {
        try {
            return (string) $this->imageHelper
                ->init($member, 'elme_family_swatch', [
                    'type' => $role,
                    'width' => self::SWATCH_PX,
                    'height' => self::SWATCH_PX,
                ])
                ->setImageFile($file)
                ->getUrl();
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * "Not available in Black" - the current values the linked member does not keep.
     *
     * @param string[] $missing
     * @param array<string, string> $current
     * @param array<string, array<string, string>> $labelsByCode
     */
    private function unavailableNote(array $missing, array $current, array $labelsByCode): string
    {
        if (!$missing) {
            return '';
        }
        $labels = [];
        foreach ($missing as $code) {
            $value = $current[$code] ?? '';
            $labels[] = $labelsByCode[$code][$value] ?? $value;
        }
        return (string) __('Not available in %1', implode(', ', $labels));
    }
}
