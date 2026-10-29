<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Content Studio type `store_visit` (etc/content_types.xml, FE-63): the "Visit us" card. Address, phone
 * and map pin come from StoreContact (store config); name, notes and opening hours from the entry. The
 * card and the schema.org store node (getSchemaNode) both read this class, so they always agree.
 */
class StoreVisit implements ArgumentInterface
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public const SCHEMA_TYPES = ['OutletStore', 'FurnitureStore', 'HomeGoodsStore', 'Store'];

    public const MAX_HOURS = 7;

    public function __construct(
        private readonly StoreContact $storeContact
    ) {
    }

    public function getStoreContact(): StoreContact
    {
        return $this->storeContact;
    }

    /**
     * @param array<string, mixed> $content Block\Entry::getContent() or the entry data
     */
    public function getHeading(array $content): string
    {
        return $this->text($content, 'heading');
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getStoreName(array $content): string
    {
        return $this->text($content, 'store_name');
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getIntro(array $content): string
    {
        return $this->text($content, 'intro');
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getPublicNote(array $content): string
    {
        return $this->text($content, 'public_note');
    }

    /**
     * @param array<string, mixed> $content
     */
    public function isOpenToPublic(array $content): bool
    {
        return $this->getPublicNote($content) !== '';
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getSchemaType(array $content): string
    {
        $type = $this->text($content, 'schema_type');

        return in_array($type, self::SCHEMA_TYPES, true) ? $type : self::SCHEMA_TYPES[0];
    }

    /**
     * Opening hours rows in editor order. A row runs from day_from to day_to (one day when day_to is
     * empty or the same day); rows with an unknown day or a time that is not HH:MM are skipped.
     *
     * @param array<string, mixed> $content
     * @return list<array{
     *     days: list<string>,
     *     label: string,
     *     label_full: string,
     *     opens: string,
     *     closes: string,
     *     time_label: string
     * }>
     */
    public function getHours(array $content): array
    {
        $rows = [];

        foreach ((array)($content['hours'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $from = array_search($this->text($row, 'day_from'), self::DAYS, true);
            $toName = $this->text($row, 'day_to');
            $to = $toName === '' ? $from : array_search($toName, self::DAYS, true);
            $opens = $this->time($this->text($row, 'opens'));
            $closes = $this->time($this->text($row, 'closes'));

            if ($from === false || $to === false || $to < $from || $opens === null || $closes === null) {
                continue;
            }

            $days = array_slice(self::DAYS, $from, $to - $from + 1);
            $first = $days[0];
            $last = $days[count($days) - 1];
            $single = $first === $last;

            $rows[] = [
                'days' => $days,
                'label' => substr($first, 0, 3) . ($single ? '' : ' - ' . substr($last, 0, 3)),
                'label_full' => $single ? $first : $first . ' to ' . $last,
                'opens' => $opens,
                'closes' => $closes,
                'time_label' => $this->clock($opens) . ' - ' . $this->clock($closes),
            ];

            if (count($rows) === self::MAX_HOURS) {
                break;
            }
        }

        return $rows;
    }

    /**
     * schema.org store node for the page @graph. Null without a store name.
     *
     * @param array<string, mixed> $content
     * @param array{src: string, width: int, height: int}|null $image
     * @return array<string, mixed>|null
     */
    public function getSchemaNode(array $content, string $pageUrl, string $baseUrl, ?array $image): ?array
    {
        $name = $this->getStoreName($content);

        if ($name === '') {
            return null;
        }

        $address = $this->storeContact->getAddressParts();
        $node = [
            '@type' => $this->getSchemaType($content),
            '@id' => $pageUrl . '#store',
            'name' => $name,
            'url' => $pageUrl,
            'parentOrganization' => ['@id' => $baseUrl . '/#organization'],
        ];
        if ($image !== null) {
            $node['image'] = $image['src'];
        }
        $intro = $this->getIntro($content);
        if ($intro !== '') {
            $node['description'] = $intro;
        }
        $node += [
            'telephone' => $this->storeContact->getPhoneInternational(),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address['street'],
                'addressLocality' => $address['city'],
                'addressRegion' => $address['region'],
                'postalCode' => $address['postcode'],
                'addressCountry' => $address['country'],
            ],
        ];
        $geo = $this->storeContact->getGeo();
        if ($geo !== null) {
            $node['geo'] = ['@type' => 'GeoCoordinates'] + $geo;
        }
        $node['hasMap'] = $this->storeContact->getDirectionsUrl();

        $hours = [];
        foreach ($this->getHours($content) as $row) {
            $hours[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => count($row['days']) === 1 ? $row['days'][0] : $row['days'],
                'opens' => $row['opens'],
                'closes' => $row['closes'],
            ];
        }
        if ($hours) {
            $node['openingHoursSpecification'] = $hours;
        }
        $node['publicAccess'] = $this->isOpenToPublic($content);

        return $node;
    }

    /**
     * "9:00", "09:00" or "9" to "09:00"; null when it is not a time of day.
     */
    private function time(string $value): ?string
    {
        if (!preg_match('/^(\d{1,2})(?::(\d{2}))?$/', $value, $match)) {
            return null;
        }

        $hour = (int)$match[1];
        $minute = (int)($match[2] ?? 0);

        if ($hour > 24 || $minute > 59 || ($hour === 24 && $minute > 0)) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * "09:00" to "9am", "17:30" to "5:30pm".
     */
    private function clock(string $time): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $suffix = $hour >= 12 && $hour < 24 ? 'pm' : 'am';
        $hour12 = $hour % 12 ?: 12;

        return $hour12 . ($minute ? sprintf(':%02d', $minute) : '') . $suffix;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? '';

        return is_scalar($value) ? trim((string)$value) : '';
    }
}
