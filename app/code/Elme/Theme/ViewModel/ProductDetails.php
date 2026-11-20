<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Catalog\Helper\Output;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * FE-29: everything the PDP detail sections show, as plain rows, so the template and the later
 * Product JSON-LD (FE-55) read the same values. Rows are {code, label, value, unit}; every field can
 * be empty (elme-product-data) and an empty list means the section is not rendered.
 *
 * Target attribute codes are read first, then the codes the local test catalogue still uses.
 */
class ProductDetails implements ArgumentInterface
{
    public const UNIT_CM = 'cm';
    public const UNIT_KG = 'kg';

    /** target code first, local seed code second */
    private const CARE_CODES = ['special_care', 'care_instructions'];
    private const SPEC_JSON_CODES = ['specifications', 'specs'];

    /** shown in their own sections, never as a spec table row */
    private const NOT_IN_SPEC_TABLE = [
        'width', 'depth', 'height', 'weight', 'measurements', 'cartons', 'units_per_ctn',
        'product_note', 'disclaimer', 'special_care', 'care_instructions', 'specifications', 'specs',
        'description', 'short_description', 'subtitle', 'sku', 'name', 'price', 'special_price',
    ];

    /** product types whose front-back size is called Length (product type template, elme-product-data) */
    private const DEPTH_AS_LENGTH = ['rug', 'oversized_rugs', 'wall_art'];

    /** @var array<int, string> */
    private array $typeCodes = [];

    public function __construct(
        private readonly Output $outputHelper,
        private readonly AttributeSetRepositoryInterface $attributeSetRepository
    ) {
    }

    /**
     * Long description for the Details section. Empty when it adds nothing to the short description
     * already shown in the info column. h1/h2 from the PIM become h3 (one h1, section titles are h2).
     */
    public function getDescriptionHtml(Product $product, string $shownShortText = ''): string
    {
        $raw = trim((string) $product->getData('description'));
        if ($raw === '') {
            return '';
        }

        $text = $this->normaliseText($raw);
        $shown = $this->normaliseText($shownShortText);
        if ($text === '' || ($shown !== '' && str_contains($shown, $text))) {
            return '';
        }

        $html = (string) $this->outputHelper->productAttribute($product, $raw, 'description');

        return $this->demoteHeadings($html);
    }

    /**
     * Spec table: attributes marked "Visible on Catalog Pages on Storefront" (admin order), then the
     * rows of the specifications JSON. A JSON row with the label of an earlier row is skipped.
     *
     * @return list<array{code: string, label: string, value: string, unit: string}>
     */
    public function getSpecRows(Product $product): array
    {
        $rows = [];
        $labels = [];

        foreach ($product->getAttributes() as $attribute) {
            $code = (string) $attribute->getAttributeCode();
            if (!$attribute->getIsVisibleOnFront() || in_array($code, self::NOT_IN_SPEC_TABLE, true)) {
                continue;
            }
            if ($product->getData($code) === null || $product->getData($code) === '') {
                continue;
            }
            $value = $attribute->getFrontend()->getValue($product);
            if ($value instanceof Phrase) {
                $value = (string) $value;
            }
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $value = trim(strip_tags((string) $value));
            if ($value === '') {
                continue;
            }
            $label = trim((string) $attribute->getStoreLabel());
            $rows[] = ['code' => $code, 'label' => $label, 'value' => $value, 'unit' => ''];
            $labels[mb_strtolower($label)] = true;
        }

        foreach ($this->jsonList($product, self::SPEC_JSON_CODES) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim(strip_tags((string) ($row['value'] ?? '')));
            if ($label === '' || $value === '' || isset($labels[mb_strtolower($label)])) {
                continue;
            }
            $code = trim((string) ($row['code'] ?? '')) ?: $this->slug($label);
            $rows[] = ['code' => $code, 'label' => $label, 'value' => $value, 'unit' => ''];
            $labels[mb_strtolower($label)] = true;
        }

        return $rows;
    }

    /**
     * Width, depth, height (cm) and assembled weight (kg). Value is the trimmed number as text.
     *
     * @return list<array{code: string, label: string, value: string, unit: string}>
     */
    public function getDimensionRows(Product $product): array
    {
        $depthLabel = in_array($this->getProductTypeCode($product), self::DEPTH_AS_LENGTH, true)
            ? __('Length')
            : __('Depth');

        $fields = [
            'width' => [__('Width'), self::UNIT_CM],
            'depth' => [$depthLabel, self::UNIT_CM],
            'height' => [__('Height'), self::UNIT_CM],
            'weight' => [__('Weight'), self::UNIT_KG],
        ];

        $rows = [];
        foreach ($fields as $code => [$label, $unit]) {
            $value = $this->number($product->getData($code));
            if ($value !== '') {
                $rows[] = ['code' => $code, 'label' => (string) $label, 'value' => $value, 'unit' => $unit];
            }
        }

        return $rows;
    }

    /**
     * Extra measurements (seat height, weight limit ...) from the `measurements` JSON.
     *
     * @return list<array{code: string, label: string, value: string, unit: string}>
     */
    public function getMeasurementRows(Product $product): array
    {
        $rows = [];
        foreach ($this->jsonList($product, ['measurements']) as $row) {
            $label = trim((string) ($row['name'] ?? ''));
            $raw = $row['value'] ?? '';
            $value = $this->number($raw);
            if ($value === '') {
                $value = trim(strip_tags(is_scalar($raw) ? (string) $raw : ''));
            }
            if ($label === '' || $value === '') {
                continue;
            }
            $rows[] = [
                'code' => $this->slug($label),
                'label' => $label,
                'value' => $value,
                'unit' => trim((string) ($row['unit'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * Shipping cartons from the `cartons` JSON: L x W x H in cm, weight in kg (each part may be empty).
     *
     * @return list<array{length: string, width: string, height: string, weight: string}>
     */
    public function getCartons(Product $product): array
    {
        $cartons = [];
        foreach ($this->jsonList($product, ['cartons']) as $row) {
            $carton = [
                'length' => $this->number($row['length'] ?? null),
                'width' => $this->number($row['width'] ?? null),
                'height' => $this->number($row['height'] ?? null),
                'weight' => $this->number($row['weight'] ?? null),
            ];
            if (array_filter($carton) !== []) {
                $cartons[] = $carton;
            }
        }

        return $cartons;
    }

    /**
     * "245 x 115 x 20 cm, 98 kg" for one carton.
     *
     * @param array{length: string, width: string, height: string, weight: string} $carton
     */
    public function formatCarton(array $carton): string
    {
        $size = array_values(array_filter([$carton['length'], $carton['width'], $carton['height']], 'strlen'));
        $parts = [];
        if ($size !== []) {
            $parts[] = implode(' x ', $size) . ' ' . self::UNIT_CM;
        }
        if ($carton['weight'] !== '') {
            $parts[] = $carton['weight'] . ' ' . self::UNIT_KG;
        }

        return implode(', ', $parts);
    }

    /**
     * Units per carton; only worth showing when more than one item ships in a carton.
     */
    public function getUnitsPerCarton(Product $product): int
    {
        $units = (int) $product->getData('units_per_ctn');

        return $units > 1 ? $units : 0;
    }

    /**
     * Delivery and handling note (`product_note`, e.g. stairs restriction or "cannot be dismantled").
     */
    public function getDeliveryNote(Product $product): string
    {
        return trim(strip_tags((string) $product->getData('product_note')));
    }

    public function getDisclaimerHtml(Product $product): string
    {
        $raw = trim((string) $product->getData('disclaimer'));

        return $raw === ''
            ? ''
            : $this->demoteHeadings((string) $this->outputHelper->productAttribute($product, $raw, 'disclaimer'));
    }

    public function getCareHtml(Product $product): string
    {
        foreach (self::CARE_CODES as $code) {
            $raw = trim((string) $product->getData($code));
            if ($raw !== '' && $this->normaliseText($raw) !== '') {
                return $this->demoteHeadings((string) $this->outputHelper->productAttribute($product, $raw, $code));
            }
        }

        return '';
    }

    /**
     * Product type code (`product_type` option, e.g. "sofa_cover"); falls back to the attribute set
     * name while the catalogue has no product_type (old site keyed its PDP CMS blocks on the set).
     */
    public function getProductTypeCode(Product $product): string
    {
        $id = (int) $product->getId();
        if (isset($this->typeCodes[$id])) {
            return $this->typeCodes[$id];
        }

        $label = '';
        if ($product->getData('product_type') !== null && $product->getResource()->getAttribute('product_type')) {
            $text = $product->getAttributeText('product_type');
            $label = is_array($text) ? (string) reset($text) : (string) $text;
        }
        if ($label === '') {
            try {
                $label = (string) $this->attributeSetRepository->get((int) $product->getAttributeSetId())
                    ->getAttributeSetName();
            } catch (NoSuchEntityException) {
                $label = '';
            }
        }

        return $this->typeCodes[$id] = str_replace('-', '_', $this->slug($label));
    }

    /**
     * CMS block identifier for a PDP block, e.g. "pdp_middle_page_%s" -> "pdp_middle_page_sofa".
     */
    public function getCmsBlockIdentifier(Product $product, string $template): string
    {
        $type = $this->getProductTypeCode($product);

        return $type === '' ? '' : sprintf($template, $type);
    }

    /**
     * @param list<string> $codes first code with a non-empty JSON list wins
     * @return list<array<string, mixed>>
     */
    private function jsonList(Product $product, array $codes): array
    {
        foreach ($codes as $code) {
            $raw = $product->getData($code);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            try {
                $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }
            if (is_array($data) && $data !== []) {
                return array_values(array_filter($data, 'is_array'));
            }
        }

        return [];
    }

    /**
     * "240.0000" -> "240", "90.50" -> "90.5"; one decimal at most. Empty for non-numeric or zero.
     */
    private function number(mixed $value): string
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (!is_numeric($value) || (float) $value <= 0) {
            return '';
        }
        $rounded = round((float) $value, 1);

        return $rounded == floor($rounded)
            ? (string) (int) $rounded
            : number_format($rounded, 1, '.', '');
    }

    private function demoteHeadings(string $html): string
    {
        return (string) preg_replace('#<(/?)h[12](?=[\s>])#i', '<$1h3', $html);
    }

    private function normaliseText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return mb_strtolower(trim($text), 'UTF-8');
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    }
}
