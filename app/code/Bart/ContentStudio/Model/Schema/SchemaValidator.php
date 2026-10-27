<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Schema;

/**
 * Validates and normalizes a submitted entry payload against its content
 * type's schema before it reaches storage.
 *
 * Two distinct jobs, on purpose: {@see normalize()} cleans up data that has
 * no legitimate reason to carry markup (plain `text` fields), while
 * {@see validate()} rejects data that is actually unsafe or malformed (bad
 * URLs, missing required fields, oversized payloads). Sanitizing on save is
 * not a substitute for escaping on output - see plan section 7 - this class only
 * keeps garbage out of the database, output escaping still happens at every
 * render site.
 */
class SchemaValidator
{
    public const MAX_PAYLOAD_BYTES = 2_000_000;
    public const MAX_TEXT_LENGTH = 2000;
    public const MAX_BLOCKS_DEPTH = 3;
    public const MAX_REPEATER_ITEMS = 200;

    // A `datetime` is an editorial date - a publication slot, an event. Both
    // directions are legitimate (backdating an import, scheduling ahead), so
    // the bound is symmetric and generous; it exists to catch a mistyped year
    // ("20226") turning into a post that never publishes, not to express policy.
    public const MAX_DATE_DRIFT_YEARS = 10;

    // "#" alone is allowed: a placeholder for "no destination yet", not just an
    // escape-hatch - it never navigates anywhere, unlike javascript:.
    //
    // The (?![/\\]) after the slash is what stops "//evil.com" and "/\evil.com":
    // both are protocol-relative and take the visitor off-site while reading
    // like an internal path.
    public const SAFE_URL_PATTERN = '~^(#|/(?![/\\\\])|https?://)~i';

    // An image source has no use for the "#" placeholder - an anchor cannot
    // load a picture - so it gets the same rule minus that branch.
    private const SAFE_IMAGE_PATTERN = '~^(/(?![/\\\\])|https?://)~i';

    // A colour is printed into a `style` attribute, so it has to be a literal
    // hex and nothing else - a named colour or a `url(...)` would either do
    // nothing or smuggle CSS into the attribute. Escaping cannot express that:
    // escapeHtmlAttr stops a value breaking *out* of the attribute and leaves
    // it free to add whatever it likes inside one.
    //
    // Must stay character-for-character equivalent to HEX_COLOR in the
    // editor's field/color.js, and to the render-time guard of any template
    // that prints a colour (import bypasses this validator, so that guard is
    // the one that protects output). If they drift, a value saves clean and
    // then never renders.
    private const SAFE_COLOR_PATTERN = '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i';

    /**
     * @param Config $schemaConfig
     * @param HtmlSanitizer $htmlSanitizer
     */
    public function __construct(
        private readonly Config $schemaConfig,
        private readonly HtmlSanitizer $htmlSanitizer
    ) {
    }

    /**
     * Same allow-list the URL field validates on save. Render sites call this
     * so a leftover `javascript:` never becomes an href.
     *
     * @param string $url
     * @return bool
     */
    public static function isSafeUrl(string $url): bool
    {
        return $url !== '' && (bool)preg_match(self::SAFE_URL_PATTERN, $url);
    }

    /**
     * Returns a copy of $data with plain `text` fields stripped of markup.
     *
     * @param string $typeCode
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalize(string $typeCode, array $data): array
    {
        $type = $this->schemaConfig->getType($typeCode);

        return $type ? $this->normalizeFields($type['fields'] ?? [], $data) : $data;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    private function normalizeFields(array $fields, array $data): array
    {
        $normalized = [];

        // Underscore-prefixed keys are the engine's own, not the editor's: a
        // blocks item carries `_type` to say which content type it is, and
        // dropping it would make the item unrenderable. Everything else that
        // the schema does not declare is discarded rather than persisted -
        // otherwise a hand-edited import writes junk into the entry forever
        // and carries it through every later export.
        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, '_')) {
                $normalized[$key] = $value;
            }
        }

        foreach ($fields as $field) {
            $name = $field['name'];

            if (array_key_exists($name, $data)) {
                $normalized[$name] = $this->normalizeField($field, $data[$name]);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed $value
     * @return mixed
     */
    private function normalizeField(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            // `wysiwyg` gets the opposite treatment from these: strip_tags on
            // body copy would destroy the content instead of cleaning it, so it
            // goes through an allow-list instead. It must never fall into this
            // branch - that was the phase-1 degrade-to-textarea behaviour.
            'text', 'textarea', 'icon' => is_string($value) ? trim(strip_tags($value)) : $value,
            'wysiwyg' => is_string($value) ? $this->htmlSanitizer->sanitize($value) : $value,
            'datetime' => is_string($value) ? $this->normalizeDateTime($value) : $value,
            'reference' => is_string($value) ? trim($value) : $value,
            'reference_multi' => is_array($value) ? $this->normalizeReferenceList($value) : $value,
            'group' => is_array($value) ? $this->normalizeFields($field['fields'] ?? [], $value) : $value,
            'repeater' => is_array($value) ? $this->normalizeRepeaterItems($field, $value) : $value,
            'blocks' => is_array($value) ? $this->normalizeBlockItems($value) : $value,
            default => $value,
        };
    }

    /**
     * Stores one canonical spelling of an instant - UTC, seconds precision - so
     * two entries saved from different admin timezones sort against each other
     * correctly. An unparseable value is passed through untouched for
     * {@see validateDateTime()} to reject with a message; silently blanking it
     * here would look like the field simply refused to save.
     *
     * @param string $value
     * @return string
     */
    private function normalizeDateTime(string $value): string
    {
        try {
            $date = new \DateTimeImmutable(trim($value));
        } catch (\Exception $e) {
            return $value;
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @param array<int, mixed> $value
     * @return array<int, string>
     */
    private function normalizeReferenceList(array $value): array
    {
        $identifiers = array_map(
            static fn ($item): string => is_string($item) ? trim($item) : '',
            array_values($value)
        );

        return array_values(array_unique(array_filter($identifiers, static fn (string $item): bool => $item !== '')));
    }

    /**
     * @param array<string, mixed> $field
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    private function normalizeRepeaterItems(array $field, array $items): array
    {
        return array_map(
            fn ($item): mixed => is_array($item) ? $this->normalizeFields($field['fields'] ?? [], $item) : $item,
            $items
        );
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    private function normalizeBlockItems(array $items): array
    {
        return array_map(function ($item) {
            if (!is_array($item) || !isset($item['_type'])) {
                return $item;
            }

            $blockType = $this->schemaConfig->getType((string)$item['_type']);

            return $blockType ? $this->normalizeFields($blockType['fields'] ?? [], $item) : $item;
        }, $items);
    }

    /**
     * @param string $typeCode
     * @param array<string, mixed> $data
     * @return array<string, string> Path => message. Empty when the payload is valid.
     */
    public function validate(string $typeCode, array $data): array
    {
        $type = $this->schemaConfig->getType($typeCode);

        if ($type === null) {
            return ['_type' => (string)__('Unknown content type "%1".', $typeCode)];
        }

        if (strlen((string)json_encode($data)) > self::MAX_PAYLOAD_BYTES) {
            return ['_root' => (string)__('This entry is too large to save.')];
        }

        $errors = [];

        $this->validateFields($type['fields'] ?? [], $data, '', $errors, 0);

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function validateFields(array $fields, array $data, string $pathPrefix, array &$errors, int $depth): void
    {
        foreach ($fields as $field) {
            $path = $pathPrefix === '' ? $field['name'] : $pathPrefix . '.' . $field['name'];

            $this->validateField($field, $data[$field['name']] ?? null, $path, $errors, $depth);
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateField(array $field, mixed $value, string $path, array &$errors, int $depth): void
    {
        if (!empty($field['required']) && $this->isEmpty($value)) {
            $errors[$path] = (string)__('This field is required.');

            return;
        }

        if ($this->isEmpty($value)) {
            return;
        }

        match ($field['type']) {
            'text', 'textarea', 'wysiwyg', 'icon' => $this->validateText($field, $value, $path, $errors),
            'number' => $this->validateNumber($field, $value, $path, $errors),
            'url' => $this->validateUrl($value, $path, $errors),
            'image' => $this->validateImage($value, $path, $errors),
            'color' => $this->validateColor($value, $path, $errors),
            'datetime' => $this->validateDateTime($value, $path, $errors),
            'reference' => $this->validateReference($value, $path, $errors),
            'reference_multi' => $this->validateReferenceMulti($field, $value, $path, $errors),
            'group' => $this->validateFields($field['fields'] ?? [], (array)$value, $path, $errors, $depth),
            'repeater' => $this->validateRepeater($field, $value, $path, $errors, $depth),
            'blocks' => $this->validateBlocks($field, $value, $path, $errors, $depth),
            default => null,
        };
    }

    /**
     * Per-field `maxlength` when the schema declares one, the global cap
     * otherwise - so a heading and a paragraph of body copy stop being held to
     * the same 2000 characters.
     *
     * @param array<string, mixed> $field
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateText(array $field, mixed $value, string $path, array &$errors): void
    {
        if (!is_string($value)) {
            return;
        }

        $limit = isset($field['maxlength']) ? (int)$field['maxlength'] : self::MAX_TEXT_LENGTH;

        if ($limit > 0 && mb_strlen($value) > $limit) {
            $errors[$path] = (string)__('This field cannot exceed %1 characters.', $limit);
        }
    }

    /**
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateImage(mixed $value, string $path, array &$errors): void
    {
        // Scheme, not file extension: Cloudinary serves images from paths like
        // /image/upload/c_limit,f_auto/v1/FOO with no suffix at all, so an
        // extension check would reject real, working images.
        if (!is_string($value) || !preg_match(self::SAFE_IMAGE_PATTERN, $value)) {
            $errors[$path] = (string)__('Image links must start with "/", "http://" or "https://".');
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateNumber(array $field, mixed $value, string $path, array &$errors): void
    {
        if (!is_numeric($value)) {
            $errors[$path] = (string)__('This field must be a number.');

            return;
        }

        $number = (float)$value;

        if (isset($field['min']) && $number < (float)$field['min']) {
            $errors[$path] = (string)__('This field must be at least %1.', $field['min']);
        } elseif (isset($field['max']) && $number > (float)$field['max']) {
            $errors[$path] = (string)__('This field cannot exceed %1.', $field['max']);
        }
    }

    /**
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateUrl(mixed $value, string $path, array &$errors): void
    {
        if (!is_string($value) || !self::isSafeUrl($value)) {
            $errors[$path] = (string)__('Links must be "#", or start with "/", "http://" or "https://".');
        }
    }

    /**
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateColor(mixed $value, string $path, array &$errors): void
    {
        if (!is_string($value) || !preg_match(self::SAFE_COLOR_PATTERN, $value)) {
            $errors[$path] = (string)__('Enter a colour as a hex value, for example #ab9377.');
        }
    }

    /**
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateDateTime(mixed $value, string $path, array &$errors): void
    {
        if (!is_string($value)) {
            $errors[$path] = (string)__('This field must be a date and time.');

            return;
        }

        try {
            $date = new \DateTimeImmutable(trim($value));
        } catch (\Exception $e) {
            $errors[$path] = (string)__('This field must be a date and time.');

            return;
        }

        $drift = abs((new \DateTimeImmutable('now'))->diff($date)->y);

        if ($drift > self::MAX_DATE_DRIFT_YEARS) {
            $errors[$path] = (string)__(
                'This date must be within %1 years of today - check the year.',
                self::MAX_DATE_DRIFT_YEARS
            );
        }
    }

    /**
     * Shape only. Whether the target entry actually exists is deliberately NOT
     * checked here, for two reasons: this class is pure schema logic with no
     * repository (which is what keeps it unit-testable), and an all-or-nothing
     * import would otherwise become order-dependent - a post importing before
     * its category would fail, and a ZIP has no guaranteed order. A reference
     * to a missing entry resolves to nothing at render time and is skipped, the
     * same way `Block\Entry::renderBlock()` already skips an unregistered
     * `_type`. The editor's picker only ever offers entries that exist, so this
     * is reachable through a hand-edited import, not through normal authoring.
     *
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateReference(mixed $value, string $path, array &$errors): void
    {
        if (!is_string($value) || trim($value) === '') {
            $errors[$path] = (string)__('This field is invalid.');
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateReferenceMulti(array $field, mixed $value, string $path, array &$errors): void
    {
        if (!is_array($value)) {
            $errors[$path] = (string)__('This field is invalid.');

            return;
        }

        $max = $field['max'] ?? self::MAX_REPEATER_ITEMS;

        if (count($value) > $max) {
            $errors[$path] = (string)__('This field cannot have more than %1 items.', $max);

            return;
        }

        foreach (array_values($value) as $index => $item) {
            if (!is_string($item) || trim($item) === '') {
                $errors[$path . '.' . $index] = (string)__('This field is invalid.');
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateRepeater(array $field, mixed $value, string $path, array &$errors, int $depth): void
    {
        if (!is_array($value)) {
            $errors[$path] = (string)__('This field is invalid.');

            return;
        }

        $max = $field['max'] ?? self::MAX_REPEATER_ITEMS;

        if (count($value) > $max) {
            $errors[$path] = (string)__('This field cannot have more than %1 items.', $max);

            return;
        }

        $items = array_values($value);

        foreach ($items as $index => $item) {
            $this->validateFields($field['fields'] ?? [], (array)$item, $path . '.' . $index, $errors, $depth);
        }

        $this->validateReadonlyKeysAreUnique($field['fields'] ?? [], $items, $path, $errors);
    }

    /**
     * A `readonly` child of a repeater is this module's marker for "other code
     * keys off this value". Two items sharing one means the second is dead
     * content: the lookup finds the first and the
     * editor has no way to see the second never renders.
     *
     * The error lands on the later occurrence; the first one is the keeper.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $items
     * @param array<string, string> $errors
     */
    private function validateReadonlyKeysAreUnique(array $fields, array $items, string $path, array &$errors): void
    {
        foreach ($fields as $field) {
            if (empty($field['readonly'])) {
                continue;
            }

            $seen = [];

            foreach ($items as $index => $item) {
                $value = is_array($item) ? ($item[$field['name']] ?? null) : null;

                if (!is_scalar($value) || $value === '') {
                    continue;
                }

                $key = (string)$value;
                $childPath = $path . '.' . $index . '.' . $field['name'];

                if (isset($seen[$key])) {
                    $errors[$childPath] = (string)__('"%1" is already used above.', $key);
                    continue;
                }

                $seen[$key] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed $value
     * @param array<string, string> $errors
     */
    private function validateBlocks(array $field, mixed $value, string $path, array &$errors, int $depth): void
    {
        if (!is_array($value)) {
            $errors[$path] = (string)__('This field is invalid.');

            return;
        }

        if ($depth >= self::MAX_BLOCKS_DEPTH) {
            $errors[$path] = (string)__('This content is nested too deeply.');

            return;
        }

        $max = $field['max'] ?? self::MAX_REPEATER_ITEMS;

        if (count($value) > $max) {
            $errors[$path] = (string)__('This field cannot have more than %1 items.', $max);

            return;
        }

        foreach (array_values($value) as $index => $item) {
            $this->validateBlockItem($field, (array)$item, $path . '.' . $index, $errors, $depth);
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $item
     * @param array<string, string> $errors
     */
    private function validateBlockItem(array $field, array $item, string $path, array &$errors, int $depth): void
    {
        $blockTypeCode = (string)($item['_type'] ?? '');
        $blockType = $this->schemaConfig->getType($blockTypeCode);

        if (!$blockType || !$this->schemaConfig->isAllowed($blockTypeCode, $field['allow'] ?? [])) {
            $errors[$path . '._type'] = (string)__('"%1" is not allowed here.', $blockTypeCode);

            return;
        }

        $this->validateFields($blockType['fields'] ?? [], $item, $path, $errors, $depth + 1);
    }

    /**
     * @param mixed $value
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
