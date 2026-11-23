<?php

declare(strict_types=1);

namespace Elme\ProductFamily\ViewModel;

use Elme\ProductFamily\Model\AxisBuilder;
use Elme\ProductFamily\Model\ColourCounter;
use Elme\ProductFamily\Model\ConfigurationPicker;
use Elme\ProductFamily\Model\FabricGroups;
use Elme\ProductFamily\Model\FamilyLoader;
use Elme\ProductFamily\Model\LeadTime\LeadTimeProviderInterface;
use Elme\ProductFamily\Model\ProductGroupBuilder;
use Elme\ProductFamily\Model\SiblingOrder;
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
    public const AVAILABILITY_QUICK_SHIP = 'quick_ship';
    public const AVAILABILITY_MADE_TO_ORDER = 'made_to_order';

    private const SWATCH_PX = 112;
    private const CONFIGURATION_PX = 320;
    /** The fabric picture of the Made To Order confirm dialog (FE-85). */
    private const FABRIC_LARGE_PX = 480;
    private const STRUCTURED_DATA_PX = 800;
    /** The grid card photo, so a swatch preview swaps a photo of the same size. */
    private const CARD_PHOTO_PX = 360;

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
        private readonly SiblingOrder $siblingOrder,
        private readonly ConfigurationPicker $configurationPicker,
        private readonly ColourCounter $colourCounter,
        private readonly ProductGroupBuilder $productGroupBuilder,
        private readonly LeadTimeProviderInterface $leadTimeProvider,
        private readonly FabricGroups $fabricGroups,
        private readonly array $visualAxes = []
    ) {
    }

    /**
     * Every member of the family of the product, in a fixed order (family_sort_order, then SKU), for
     * the sibling picker. Empty for a product without siblings.
     * Each row: id, sku, name, url, selected, option (values of the axes that vary, "Muse Flax / Left"),
     * values (axis code => label), image / color (chip fill), madeToOrder, availability
     * ("quick_ship" | "made_to_order"), conditionCode, leadTime (array with weeks and label, or null).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSiblings(Product $product): array
    {
        $context = $this->familyContext($product);
        if (!$context) {
            return [];
        }
        ['members' => $members, 'definitions' => $definitions, 'rows' => $rows] = $context;

        $varying = $this->varyingCodes($definitions, $rows);
        $colourDefinition = $this->colourDefinition($definitions);
        $optionSwatches = $colourDefinition
            ? $this->optionSwatches(array_values(array_unique(array_column(array_column($rows, 'values'), $colourDefinition['code']))))
            : [];

        $siblings = [];
        foreach ($this->siblingOrder->sort($rows) as $id) {
            $member = $members[$id];
            $labels = [];
            foreach ($definitions as $definition) {
                $value = $rows[$id]['values'][$definition['code']];
                if ($value !== '') {
                    $labels[$definition['code']] = $definition['labels'][$value] ?? $value;
                }
            }
            $optionLabels = array_values(array_intersect_key($labels, array_flip($varying)));
            $swatch = $colourDefinition ? ($optionSwatches[$rows[$id]['values'][$colourDefinition['code']]] ?? null) : null;
            $madeToOrder = (bool) $member->getData(FamilyLoader::ATTR_MADE_TO_ORDER);
            $leadTime = $this->leadTimeProvider->getLeadTime($member);

            $siblings[] = [
                'id' => $id,
                'sku' => (string) $member->getSku(),
                'name' => (string) $member->getName(),
                'url' => (string) $member->getProductUrl(),
                'selected' => $id === (int) $product->getId(),
                'option' => $optionLabels ? implode(' / ', $optionLabels) : (string) $member->getName(),
                'values' => $labels,
                'madeToOrder' => $madeToOrder,
                'availability' => $madeToOrder ? self::AVAILABILITY_MADE_TO_ORDER : self::AVAILABILITY_QUICK_SHIP,
                'conditionCode' => $this->optionText($member, FamilyLoader::ATTR_CONDITION),
                'leadTime' => $leadTime ? $leadTime->toArray() : null,
            ] + $this->chipFill($member, $swatch);
        }

        return $siblings;
    }

    /**
     * The other configurations of the same design (modules, armchair, ottoman, 2 to 6 seaters): the
     * products that share `range` but not family_code, one per family_code, the one in the current
     * colour when there is one. Each row: id, sku, name, url, image, colour. Empty without a range.
     *
     * @return array<int, array{id: int, sku: string, name: string, url: string, image: string, colour: string}>
     */
    public function getConfigurations(Product $product): array
    {
        $range = trim((string) $product->getData(FamilyLoader::ATTR_RANGE));
        if ($range === '') {
            return [];
        }
        $rangeTag = FamilyLoader::rangeCacheTag($range);
        $this->identities[$rangeTag] = $rangeTag;

        $products = $this->familyLoader->getRangeProducts($range);
        $candidates = [];
        foreach ($products as $id => $member) {
            $sort = $member->getData(FamilyLoader::ATTR_SORT);
            $candidates[$id] = [
                'sku' => (string) $member->getSku(),
                'family' => trim((string) $member->getData(FamilyLoader::ATTR_CODE)),
                'colour' => trim((string) $member->getData($this->colourCode())),
                'sort' => $sort === null || $sort === '' ? null : (int) $sort,
            ];
        }

        $configurations = [];
        $picked = $this->configurationPicker->pick(
            $candidates,
            (int) $product->getId(),
            (string) $product->getData(FamilyLoader::ATTR_CODE),
            trim((string) $product->getData($this->colourCode()))
        );
        foreach ($picked as $id) {
            $member = $products[$id];
            $this->identities[$id] = Product::CACHE_TAG . '_' . $id;
            $configurations[] = [
                'id' => $id,
                'sku' => (string) $member->getSku(),
                'name' => (string) $member->getName(),
                'url' => (string) $member->getProductUrl(),
                'image' => $this->memberImage($member, self::CONFIGURATION_PX),
                'colour' => $this->optionText($member, $this->colourCode()),
            ];
        }

        return $configurations;
    }

    /**
     * FE-84: the Configuration picker of the PDP. The current product first (selected), then the other
     * configurations of getConfigurations(). Empty when there is no other configuration, so a product
     * without a range or alone in it shows no row. Rows as getConfigurations() plus `selected`.
     *
     * @return array<int, array{id: int, sku: string, name: string, url: string, image: string, colour: string, selected: bool}>
     */
    public function getConfigurationList(Product $product): array
    {
        $others = $this->getConfigurations($product);
        if (!$others) {
            return [];
        }

        $list = [[
            'id' => (int) $product->getId(),
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'url' => (string) $product->getProductUrl(),
            'image' => $this->memberImage($product, self::CONFIGURATION_PX),
            'colour' => $this->optionText($product, $this->colourCode()),
            'selected' => true,
        ]];
        foreach ($others as $other) {
            $list[] = $other + ['selected' => false];
        }

        return $list;
    }

    /**
     * "N colours available" for the product card: different colours in the family of the product.
     * Zero without a family; it comes from the same family query as the swatches.
     */
    public function getColourCount(Product $product): int
    {
        $members = $this->familyLoader->getMembers((string) $product->getData(FamilyLoader::ATTR_CODE));
        if (count($members) < 2) {
            return 0;
        }
        $familyTag = FamilyLoader::cacheTag((string) $product->getData(FamilyLoader::ATTR_CODE));
        $this->identities[$familyTag] = $familyTag;

        return $this->colourCounter->count(array_map(
            fn (Product $member): string => (string) $member->getData($this->colourCode()),
            $members
        ));
    }

    /**
     * The family as schema.org ProductGroup data (productGroupID, variesBy, hasVariant), without
     * "@context" and price. Null for a product without siblings.
     *
     * @return array<string, mixed>|null
     */
    public function getProductGroup(Product $product): ?array
    {
        $context = $this->familyContext($product);
        if (!$context) {
            return null;
        }
        ['members' => $members, 'definitions' => $definitions, 'rows' => $rows] = $context;

        $varying = $this->varyingCodes($definitions, $rows);
        $axisLabels = [];
        $labelsByCode = [];
        foreach ($definitions as $definition) {
            $labelsByCode[$definition['code']] = $definition['labels'];
            if (in_array($definition['code'], $varying, true)) {
                $axisLabels[$definition['code']] = $definition['label'];
            }
        }

        $variants = [];
        foreach ($this->siblingOrder->sort($rows) as $id) {
            $values = [];
            foreach ($axisLabels as $code => $label) {
                $value = $rows[$id]['values'][$code];
                $values[$code] = $value === '' ? '' : ($labelsByCode[$code][$value] ?? $value);
            }
            $variants[] = [
                'sku' => (string) $members[$id]->getSku(),
                'name' => (string) $members[$id]->getName(),
                'url' => (string) $members[$id]->getProductUrl(),
                'image' => $this->memberImage($members[$id], self::STRUCTURED_DATA_PX),
                'values' => $values,
            ];
        }

        return $this->productGroupBuilder->build(
            trim((string) $product->getData(FamilyLoader::ATTR_CODE)),
            (string) $product->getName(),
            $variants,
            $axisLabels
        );
    }

    /**
     * Axes with at least two values, in family_axes order. Each option:
     * value, label, selected, available, url, note (why it is crossed out), image, color; on a visual
     * axis also madeToOrder (bool), leadTime (weeks + label, null for Quick Ship) of the linked member and
     * imageLarge (the picture of the Made To Order confirm dialog, only for a made-to-order option; '' when
     * the fabric is just a colour or has no picture).
     *
     * @return array<int, array{code: string, label: string, visual: bool, selectedLabel: string, options: array<int, array>}>
     */
    public function getAxes(Product $product): array
    {
        $productId = (int) $product->getId();
        if (isset($this->axesByProduct[$productId])) {
            return $this->axesByProduct[$productId];
        }

        $context = $this->familyContext($product);
        if (!$context) {
            return $this->axesByProduct[$productId] = [];
        }
        ['members' => $members, 'definitions' => $definitions, 'rows' => $rows] = $context;

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
                    $optionSwatch = $optionSwatches[$option['value']] ?? null;
                    $option = array_merge($option, $this->chipFill($member, $optionSwatch));
                    $leadTime = $this->leadTimeProvider->getLeadTime($member);
                    $option['madeToOrder'] = (bool) $member->getData(FamilyLoader::ATTR_MADE_TO_ORDER);
                    $option['leadTime'] = $leadTime ? $leadTime->toArray() : null;
                    $option['imageLarge'] = $option['madeToOrder']
                        ? $this->fabricLargeImage($member, $optionSwatch, $option['color'])
                        : '';
                }
            }
            unset($option);
        }
        unset($axis);

        return $this->axesByProduct[$productId] = $axes;
    }

    /**
     * FE-85: the "Select Color:" fabric picker of the PDP for one visual axis of getAxes(): the options
     * in a Quick Ship and a Made To Order group (see FabricGroups). Null when no option of the axis is
     * made to order; the template then keeps the plain FE-28 chips.
     *
     * @param array{code: string, label: string, visual: bool, selectedLabel: string, options: array<int, array>} $axis
     * @return array{
     *     selected: array<string, mixed>|null,
     *     groups: array<int, array{code: string, leadTime: string, options: array<int, array<string, mixed>>}>
     * }|null
     */
    public function getFabricPicker(array $axis): ?array
    {
        return $axis['visual'] ? $this->fabricGroups->build($axis['options']) : null;
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
            // 'photo': the card-size photo of that colour, which the card shows while its swatch is hovered.
            $members = $this->familyLoader->getMembers((string) $product->getData(FamilyLoader::ATTR_CODE));
            foreach ($visible as &$visibleOption) {
                $member = $members[$visibleOption['memberId']] ?? null;
                $visibleOption['photo'] = $member && !$visibleOption['selected']
                    ? $this->memberImage($member, self::CARD_PHOTO_PX)
                    : '';
            }
            unset($visibleOption);
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

    /**
     * The big picture of a fabric for the Made To Order confirm dialog: the same source as chipFill() (the
     * member's swatch image, else the image of the admin swatch, else the product image) at a larger size.
     * A fabric that is only a colour has none ('' - the dialog paints the colour).
     *
     * @param array{type: int, value: string}|null $optionSwatch
     */
    private function fabricLargeImage(Product $member, ?array $optionSwatch, string $chipColor): string
    {
        if ($chipColor !== '') {
            return '';
        }
        $swatchFile = (string) $member->getData('swatch_image');
        if ($swatchFile !== '' && $swatchFile !== 'no_selection') {
            // no white frame around a picture smaller than the size: the dialog fills its box with the fabric
            return $this->imageUrl($member, 'swatch_image', $swatchFile, self::FABRIC_LARGE_PX, false);
        }
        if ($optionSwatch && $optionSwatch['type'] === Swatch::SWATCH_TYPE_VISUAL_IMAGE && $optionSwatch['value'] !== '') {
            return (string) $this->swatchMedia->getSwatchAttributeImage(Swatch::SWATCH_IMAGE_NAME, $optionSwatch['value']);
        }

        return $this->memberImage($member, self::FABRIC_LARGE_PX, false);
    }

    /**
     * The product image of a member (small image, else thumbnail) at a square size; '' without one.
     */
    private function memberImage(Product $member, int $size, bool $keepFrame = true): string
    {
        foreach (['small_image', 'thumbnail'] as $role) {
            $file = (string) $member->getData($role);
            if ($file !== '' && $file !== 'no_selection') {
                return $this->imageUrl($member, $role, $file, $size, $keepFrame);
            }
        }
        return '';
    }

    /**
     * Members of the family of the product, the axes in family_axes order, and per member its axis
     * values and sort order. Null without a family (fewer than two members, or the product is not one).
     *
     * @return array{members: array<int, Product>, definitions: array, rows: array<int, array{sku: string, sort: ?int, values: array<string, string>}>}|null
     */
    private function familyContext(Product $product): ?array
    {
        $productId = (int) $product->getId();
        $code = (string) $product->getData(FamilyLoader::ATTR_CODE);
        $members = $this->familyLoader->getMembers($code);
        if (count($members) < 2 || !isset($members[$productId])) {
            return null;
        }

        $familyTag = FamilyLoader::cacheTag($code);
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

        return ['members' => $members, 'definitions' => $definitions, 'rows' => $rows];
    }

    /**
     * Axes on which the members differ.
     *
     * @return string[]
     */
    private function varyingCodes(array $definitions, array $rows): array
    {
        $varying = [];
        foreach ($definitions as $definition) {
            $code = $definition['code'];
            $values = array_unique(array_column(array_column($rows, 'values'), $code));
            if (count(array_filter($values, static fn (string $value): bool => $value !== '')) > 1) {
                $varying[] = $code;
            }
        }
        return $varying;
    }

    private function colourCode(): string
    {
        $codes = array_values($this->visualAxes);
        return $codes ? (string) $codes[0] : 'color';
    }

    private function colourDefinition(array $definitions): ?array
    {
        foreach ($definitions as $definition) {
            if ($definition['code'] === $this->colourCode()) {
                return $definition;
            }
        }
        return null;
    }

    private function optionText(Product $member, string $code): string
    {
        try {
            $text = $member->getAttributeText($code);
        } catch (\Exception) {
            return '';
        }
        return is_array($text) ? implode(', ', $text) : (string) $text;
    }

    private function imageUrl(
        Product $member,
        string $role,
        string $file,
        int $size = self::SWATCH_PX,
        bool $keepFrame = true
    ): string {
        // the frame stays as the theme sets it (view.xml) unless a caller turns it off
        $attributes = ['type' => $role, 'width' => $size, 'height' => $size];
        if (!$keepFrame) {
            $attributes['frame'] = false;
        }
        try {
            return (string) $this->imageHelper
                ->init($member, 'elme_family_swatch', $attributes)
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
