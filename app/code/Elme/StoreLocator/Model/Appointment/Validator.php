<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\Appointment;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * FE-90: checks a showroom appointment request and returns the values the email needs.
 * The showroom must be open for the whole slot on the chosen day, from tomorrow up to BOOK_AHEAD_DAYS.
 */
class Validator
{
    public const BOOK_AHEAD_DAYS = 90;

    public const VISITORS_MAX = 6;

    public const NOTES_MAX = 500;

    /**
     * @var array<string, string>
     */
    public const INTERESTS = [
        'furniture' => 'Furniture',
        'flora' => 'Artificial flowers & plants',
        'homewares' => 'Homewares',
        'outdoor' => 'Outdoor',
    ];

    public function __construct(
        private readonly Schedule $schedule
    ) {
    }

    /**
     * @param array<string, mixed> $params request params
     * @param array<string, LocationInterface> $showrooms bookable showrooms keyed by url key
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function validate(array $params, array $showrooms, \DateTimeImmutable $today): array
    {
        $text = static fn (string $key): string => is_scalar($params[$key] ?? null)
            ? trim((string) $params[$key])
            : '';

        if ($text('hideit') !== '') {
            throw new \InvalidArgumentException('Bot submission blocked.');
        }

        $showroom = $showrooms[$text('showroom')] ?? null;
        if (!$showroom) {
            throw new LocalizedException(__('Choose a showroom and try again.'));
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $text('date'), $today->getTimezone());
        if (!$date || $date->format('Y-m-d') !== $text('date')) {
            throw new LocalizedException(__('Choose a date for your visit and try again.'));
        }
        $first = $today->setTime(0, 0)->modify('+1 day');
        $last = $today->setTime(0, 0)->modify('+' . self::BOOK_AHEAD_DAYS . ' days');
        if ($date < $first || $date > $last) {
            throw new LocalizedException(__(
                'Choose a date between %1 and %2.',
                $first->format('j M Y'),
                $last->format('j M Y')
            ));
        }

        $slots = $this->schedule->slotsForDate($showroom, $date);
        if ($slots === []) {
            throw new LocalizedException(__(
                'The showroom is closed on %1. Choose another day.',
                $date->format('l j M')
            ));
        }
        $time = $text('time');
        if (!in_array($time, $slots, true)) {
            throw new LocalizedException(__('Choose a time when the showroom is open and try again.'));
        }

        $visitors = (int) $text('visitors');
        if ($visitors < 1 || $visitors > self::VISITORS_MAX) {
            throw new LocalizedException(__('Choose how many people are coming and try again.'));
        }

        $interests = [];
        foreach ((array) ($params['interests'] ?? []) as $code) {
            if (is_string($code) && isset(self::INTERESTS[$code])) {
                $interests[$code] = self::INTERESTS[$code];
            }
        }

        $name = $text('name');
        $email = $text('email');
        $telephone = $text('telephone');
        $company = $text('company');
        $notes = $text('notes');

        if ($name === '') {
            throw new LocalizedException(__('Enter your name and try again.'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('The email address is invalid. Verify the email address and try again.'));
        }
        if ($telephone === '') {
            throw new LocalizedException(__('Enter your phone number and try again.'));
        }
        if ($company === '') {
            throw new LocalizedException(__('Enter your company and try again.'));
        }
        if (mb_strlen($notes) > self::NOTES_MAX) {
            throw new LocalizedException(__('Please enter no more than %1 characters.', self::NOTES_MAX));
        }

        return [
            'showroom' => $showroom->getUrlKey(),
            'showroom_name' => $showroom->getName(),
            'showroom_address' => trim(sprintf(
                '%s, %s %s %s',
                (string) $showroom->getStreet(),
                (string) $showroom->getCity(),
                $showroom->getState(),
                $showroom->getPostcode()
            ), ', '),
            'showroom_phone' => (string) $showroom->getPhone(),
            'date' => $date->format('Y-m-d'),
            'date_label' => $date->format('l j F Y'),
            'time' => $time,
            'time_label' => $this->schedule->slotLabel($time),
            'visitors' => $visitors,
            'interests' => array_keys($interests),
            'interests_label' => implode(', ', $interests),
            'name' => $name,
            'email' => $email,
            'telephone' => $telephone,
            'company' => $company,
            'notes' => $notes,
        ];
    }
}
