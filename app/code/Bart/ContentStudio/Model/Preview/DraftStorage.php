<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Preview;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Short-lived storage for an unsaved entry, so the storefront can render it.
 *
 * The editor holds the only copy of what the user is typing; the storefront
 * runs in another area and, on a multi-domain setup, another origin. Parking
 * the payload in the shared cache under an unguessable token is the one
 * transport that works for both without writing a half-finished entry to the
 * database or trusting anything the URL carries.
 *
 * The token is the whole security boundary: 32 hex characters from
 * random_bytes(), created only by an authenticated admin
 * ({@see \Bart\ContentStudio\Controller\Adminhtml\Entry\PreviewDraft}), and
 * expiring in fifteen minutes. Nothing derived from the entry goes into it,
 * so a token cannot be constructed from an identifier that is public anyway.
 */
class DraftStorage
{
    /**
     * Query parameter that carries the token on the storefront.
     */
    public const REQUEST_PARAM = 'cs_preview';

    private const CACHE_PREFIX = 'bart_content_studio_preview_draft_';

    /**
     * Long enough to author and re-check a block, short enough that a leaked
     * URL is worthless by the time it is shared.
     */
    private const LIFETIME = 900;

    private const TOKEN_BYTES = 16;

    private const TOKEN_PATTERN = '/^[a-f0-9]{32}$/';

    /**
     * @param CacheInterface $cache
     * @param Json $json
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json
    ) {
    }

    /**
     * @param array<string, mixed> $draft
     * @return string The token to hand to the storefront.
     * @throws \Exception When no source of randomness is available.
     */
    public function save(array $draft): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        // Stamped into the payload rather than derived on the page: the token
        // is minted when the editor asks for it, which may be minutes before
        // anyone opens the link, so a countdown started at render time would
        // promise time the token no longer has.
        $draft['expires_at'] = time() + self::LIFETIME;

        $this->cache->save(
            $this->json->serialize($draft),
            self::CACHE_PREFIX . $token,
            [],
            self::LIFETIME
        );

        return $token;
    }

    /**
     * @param string $token
     * @return array<string, mixed>|null Null for an absent, expired or malformed token.
     */
    public function load(string $token): ?array
    {
        // Shape-check before touching the cache: the token arrives straight off
        // a storefront query string, and an unchecked value would become part
        // of a cache key.
        if (!preg_match(self::TOKEN_PATTERN, $token)) {
            return null;
        }

        $raw = $this->cache->load(self::CACHE_PREFIX . $token);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
