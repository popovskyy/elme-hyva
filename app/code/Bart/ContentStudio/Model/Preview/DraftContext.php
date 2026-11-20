<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Preview;

use Magento\Framework\App\RequestInterface;

/**
 * "Is this request a preview, and of what?" - resolved once per request.
 *
 * A storefront page asks several times over (the repository plugin, the cache
 * bypass observer, the sandbox block), and every ask would otherwise be a
 * cache round trip.
 */
class DraftContext
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $draft = null;

    private bool $resolved = false;

    /**
     * @param RequestInterface $request
     * @param DraftStorage $storage
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly DraftStorage $storage
    ) {
    }

    /**
     * Whether this request carries a token that actually resolves to a draft.
     *
     * Deliberately not "is the parameter present": the answer switches off the
     * full page cache, and anyone can append a query parameter. Requiring a
     * live token keeps that switch behind something only the admin can mint.
     *
     * @return bool
     */
    public function isPreview(): bool
    {
        return $this->getDraft() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDraft(): ?array
    {
        if (!$this->resolved) {
            $this->resolved = true;

            $token = (string)$this->request->getParam(DraftStorage::REQUEST_PARAM, '');
            $this->draft = $token === '' ? null : $this->storage->load($token);
        }

        return $this->draft;
    }

    /**
     * The draft, but only when it is the one for `$identifier`.
     *
     * A product page renders several entries; exactly one of them is being
     * edited, and the rest must still come from the database.
     *
     * @param string $identifier
     * @return array<string, mixed>|null
     */
    public function getDraftFor(string $identifier): ?array
    {
        $draft = $this->getDraft();

        return $draft !== null && (string)($draft['identifier'] ?? '') === $identifier ? $draft : null;
    }
}
