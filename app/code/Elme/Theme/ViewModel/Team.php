<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Content Studio type `team` (etc/content_types.xml, FE-65): Meet the Team people + page JSON-LD.
 *
 * Cards are always server-rendered; Alpine only toggles visibility by region. Person / ItemList nodes
 * stay in the graph for every published person (filter is UX, not a separate indexable view).
 */
class Team implements ArgumentInterface
{
    public const PAGE_IDENTIFIER = 'meet-the-team';

    public const ENTRY_IDENTIFIER = 'meet-the-team';

    public const MAX_PEOPLE = 40;

    public const IMAGE_SIZE = [540, 540];

    public const DEFAULT_TITLE = 'Meet the Team';

    public const DEFAULT_HEADING = 'Our account managers';

    /** Region codes stored in the entry → chip / card label. */
    public const REGIONS = [
        'head_office' => 'Head office',
        'VIC' => 'VIC',
        'NSW' => 'NSW',
        'QLD' => 'QLD',
        'WA' => 'WA',
        'SA_TAS' => 'SA-TAS',
    ];

    /** Schema.org AdministrativeArea names for regional people (omit Head office). */
    public const AREA_SERVED = [
        'VIC' => 'Victoria',
        'NSW' => 'New South Wales',
        'QLD' => 'Queensland',
        'WA' => 'Western Australia',
        'SA_TAS' => 'South Australia and Tasmania',
    ];

    private ?string $mediaPath = null;

    /** @var array<string, mixed>|null */
    private ?array $entryContent = null;

    private bool $entryLoaded = false;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly CmsPage $cmsPage,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly ContentPage $contentPage
    ) {
    }

    public function isMeetTheTeamPage(?string $identifier = null): bool
    {
        $id = $identifier ?? (string)$this->cmsPage->getIdentifier();

        return $id === self::PAGE_IDENTIFIER;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getTitle(array $content): string
    {
        $title = $this->text($content, 'title');

        return $title !== '' ? $title : self::DEFAULT_TITLE;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getIntro(array $content): string
    {
        $intro = str_replace('\\/', '/', $this->text($content, 'intro'));

        return trim(strip_tags($intro)) !== '' ? $intro : '';
    }

    /**
     * Optional H2 above the grid; empty when the editor left it blank.
     *
     * @param array<string, mixed> $content
     */
    public function getHeading(array $content): string
    {
        return $this->text($content, 'heading');
    }

    /**
     * Chip row: All first, then every region code (including empty regions so filters stay stable).
     *
     * @return list<array{code: string, label: string}>
     */
    public function getChips(): array
    {
        $chips = [['code' => 'all', 'label' => (string)__('All')]];

        foreach (self::REGIONS as $code => $label) {
            $chips[] = ['code' => $code, 'label' => $label];
        }

        return $chips;
    }

    /**
     * Published people in editor order. Name + role + region required; photo / email / phone optional
     * (omit empty contacts — do not invent). Unpublished rows never reach the HTML or JSON-LD.
     *
     * @param array<string, mixed> $content
     * @return list<array{
     *     name: string,
     *     role: string,
     *     email: string,
     *     mailto: string,
     *     phone: string,
     *     tel: string,
     *     region: string,
     *     region_label: string,
     *     slug: string,
     *     photo: array{src: string, width: int, height: int}|null
     * }>
     */
    public function getPeople(array $content): array
    {
        $people = [];

        foreach ((array)($content['people'] ?? []) as $row) {
            if (!is_array($row) || !$this->isVisible($row)) {
                continue;
            }

            $name = $this->text($row, 'name');
            $role = $this->text($row, 'role');
            $region = $this->text($row, 'region');

            if ($name === '' || $role === '' || !isset(self::REGIONS[$region])) {
                continue;
            }

            $email = $this->email($this->text($row, 'email')) ?? '';
            $phone = $this->text($row, 'phone');
            $tel = $phone !== '' ? $this->telHref($phone) : '';
            $photoUrl = $this->text($row, 'photo');

            $people[] = [
                'name' => $name,
                'role' => $role,
                'email' => $email,
                'mailto' => $email !== '' ? 'mailto:' . $email : '',
                'phone' => $phone,
                'tel' => $tel,
                'region' => $region,
                'region_label' => self::REGIONS[$region],
                'slug' => $this->slug($name),
                'photo' => $photoUrl !== '' ? $this->image($photoUrl) : null,
            ];

            if (count($people) === self::MAX_PEOPLE) {
                break;
            }
        }

        return $people;
    }

    /**
     * Region codes that have at least one published person (for the empty-state check).
     *
     * @param list<array{region: string}> $people
     * @return list<string>
     */
    public function getPresentRegions(array $people): array
    {
        $present = [];

        foreach ($people as $person) {
            $code = $person['region'] ?? '';
            if ($code !== '' && !in_array($code, $present, true)) {
                $present[] = $code;
            }
        }

        return $present;
    }

    /**
     * One @graph: ContentPage WebPage + BreadcrumbList, plus ItemList #team and Person nodes.
     *
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(): array
    {
        $graphDoc = $this->contentPage->getJsonLdGraph('WebPage');
        $graph = $graphDoc['@graph'] ?? [];
        $baseUrl = rtrim((string)$this->storeManager->getStore()->getBaseUrl(), '/');
        $pageUrl = $this->contentPage->getCanonicalUrl();
        $people = $this->getPeople($this->loadEntryContent());

        if ($people && isset($graph[0]) && is_array($graph[0])) {
            $graph[0]['mainEntity'] = ['@id' => $pageUrl . '#team'];
        }

        if ($people) {
            $listElements = [];
            $personNodes = [];

            foreach ($people as $i => $person) {
                $personId = $pageUrl . '#person-' . $person['slug'];
                $listElements[] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'item' => ['@id' => $personId],
                ];

                $node = [
                    '@type' => 'Person',
                    '@id' => $personId,
                    'name' => $person['name'],
                    'jobTitle' => $person['role'],
                    'worksFor' => ['@id' => $baseUrl . '/#organization'],
                ];
                if ($person['photo'] !== null) {
                    $node['image'] = $person['photo']['src'];
                }
                if ($person['email'] !== '') {
                    $node['email'] = $person['email'];
                }
                if ($person['tel'] !== '') {
                    $node['telephone'] = $person['tel'];
                }
                if (isset(self::AREA_SERVED[$person['region']])) {
                    $node['areaServed'] = [
                        '@type' => 'AdministrativeArea',
                        'name' => self::AREA_SERVED[$person['region']],
                    ];
                }
                $personNodes[] = $node;
            }

            $graph[] = [
                '@type' => 'ItemList',
                '@id' => $pageUrl . '#team',
                'name' => 'Elme Living team',
                'numberOfItems' => count($listElements),
                'itemListElement' => $listElements,
            ];
            foreach ($personNodes as $node) {
                $graph[] = $node;
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    public function getJsonLdScript(): string
    {
        return (string)json_encode(
            $this->getJsonLdGraph(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /**
     * Dialable tel: value (digits and leading + only). AU landlines become +61…
     */
    public function telHref(string $phone): string
    {
        $raw = trim($phone);
        if ($raw === '') {
            return '';
        }

        $compact = preg_replace('/[^\d+]/', '', str_replace(' ', '', $raw)) ?? '';
        $compact = preg_replace('/(?!^)\+/', '', $compact) ?? $compact;

        if (str_starts_with($compact, '+')) {
            return $compact;
        }

        if (str_starts_with($compact, '61') && strlen($compact) >= 10) {
            return '+' . $compact;
        }

        if (str_starts_with($compact, '0') && strlen($compact) >= 9) {
            return '+61' . substr($compact, 1);
        }

        return $compact !== '' ? $compact : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadEntryContent(): array
    {
        if ($this->entryLoaded) {
            return $this->entryContent ?? [];
        }

        $this->entryLoaded = true;
        $this->entryContent = [];

        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $entry = $this->entryRepository->getByIdentifier(self::ENTRY_IDENTIFIER, $storeId);
        } catch (NoSuchEntityException) {
            try {
                $entry = $this->entryRepository->getByIdentifier(
                    self::ENTRY_IDENTIFIER,
                    Store::DEFAULT_STORE_ID
                );
            } catch (NoSuchEntityException) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        if (!$entry->isActive() || $entry->getType() !== 'team') {
            return [];
        }

        $this->entryContent = $entry->getEntryData();

        return $this->entryContent;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isVisible(array $row): bool
    {
        return !array_key_exists('is_visible', $row) || !empty($row['is_visible']);
    }

    private function email(string $value): ?string
    {
        $email = filter_var($value, FILTER_VALIDATE_EMAIL);

        return is_string($email) ? $email : null;
    }

    private function slug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-') ?: 'person';
    }

    /**
     * @return array{src: string, width: int, height: int}
     */
    private function image(string $url): array
    {
        [$width, $height] = $this->readSize($url) ?? self::IMAGE_SIZE;

        return ['src' => $url, 'width' => $width, 'height' => $height];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function readSize(string $url): ?array
    {
        $relative = $this->mediaRelativePath($url);

        if ($relative === null) {
            return null;
        }

        try {
            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

            if (!$media->isFile($relative)) {
                return null;
            }

            $size = getimagesize($media->getAbsolutePath($relative));
        } catch (Throwable) {
            return null;
        }

        if (!$size || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        return [(int)$size[0], (int)$size[1]];
    }

    private function mediaRelativePath(string $url): ?string
    {
        $path = rawurldecode((string)parse_url($url, PHP_URL_PATH));
        $mediaPath = $this->mediaPath();

        if ($mediaPath === '' || !str_starts_with($path, $mediaPath)) {
            return null;
        }

        $relative = substr($path, strlen($mediaPath));

        return $relative === '' || str_contains($relative, '..') ? null : $relative;
    }

    private function mediaPath(): string
    {
        if ($this->mediaPath === null) {
            try {
                $base = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
                $this->mediaPath = rtrim((string)parse_url($base, PHP_URL_PATH), '/') . '/';
            } catch (Throwable) {
                $this->mediaPath = '';
            }
        }

        return $this->mediaPath;
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
