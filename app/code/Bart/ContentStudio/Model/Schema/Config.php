<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Schema;

/**
 * Read-only facade over the merged content_types.xml registry.
 *
 * Everything else in the module (validator, renderer, editor bootstrap,
 * import/export) resolves a type through this class rather than touching
 * {@see Data} directly, so the on-disk array shape stays an implementation
 * detail of this module.
 */
class Config
{
    /**
     * @param Data $data
     */
    public function __construct(private readonly Data $data)
    {
    }

    /**
     * @return array<string, array<string, mixed>> Keyed by type code.
     */
    public function getTypes(): array
    {
        return $this->data->get() ?? [];
    }

    /**
     * @param string $code
     * @return array<string, mixed>|null
     */
    public function getType(string $code): ?array
    {
        return $this->getTypes()[$code] ?? null;
    }

    /**
     * @param string $code
     * @return bool
     */
    public function hasType(string $code): bool
    {
        return $this->getType($code) !== null;
    }

    /**
     * Types creatable as their own admin entry (shown in the "New entry" picker).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getStandaloneTypes(): array
    {
        return array_filter($this->getTypes(), static fn (array $type): bool => (bool)($type['standalone'] ?? false));
    }

    /**
     * Whether a type code satisfies a `blocks` field's <allow> restrictions.
     * An empty $allow list means every registered type is permitted.
     *
     * @param string $code
     * @param array<int, array{type: string|null, group: string|null}> $allow
     * @return bool
     */
    public function isAllowed(string $code, array $allow): bool
    {
        if ($allow === []) {
            return $this->hasType($code);
        }

        $type = $this->getType($code);

        if ($type === null) {
            return false;
        }

        foreach ($allow as $rule) {
            if ($this->matchesAllowRule($code, $type, $rule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $code
     * @param array<string, mixed> $type
     * @param array{type: string|null, group: string|null} $rule
     * @return bool
     */
    private function matchesAllowRule(string $code, array $type, array $rule): bool
    {
        if ($rule['type'] !== null) {
            return $rule['type'] === $code;
        }

        if ($rule['group'] !== null) {
            return $rule['group'] === ($type['group'] ?? null);
        }

        return false;
    }
}
