<?php

declare(strict_types=1);

namespace Elme\StoreLocator\ViewModel;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\Model\Appointment\Schedule;
use Elme\StoreLocator\Model\Appointment\Validator;
use Elme\StoreLocator\Model\Service\LocationQuery;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * FE-90: /book-appointment form data - bookable showrooms, open days and slots, prefill from the
 * trade account, values kept after a failed submit, and the summary shown after a sent request.
 * The block is not cacheable (prefill and summary are per customer).
 */
class AppointmentForm implements ArgumentInterface
{
    public const PAGE_PATH = 'book-appointment';

    public const PERSIST_KEY = 'elme_showroom_appointment';

    public const PERSIST_SENT_KEY = 'elme_showroom_appointment_sent';

    /**
     * @var array<string, LocationInterface>|null
     */
    private ?array $showrooms = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $persisted = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $prefill = null;

    public function __construct(
        private readonly LocationQuery $locationQuery,
        private readonly LocationPresenter $presenter,
        private readonly Schedule $schedule,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly RequestInterface $request,
        private readonly TimezoneInterface $timezone,
        private readonly UrlInterface $urlBuilder,
        private readonly EncoderInterface $urlEncoder,
        private readonly Json $json
    ) {
    }

    /**
     * Active showrooms (trade-only included) keyed by url key, in locator sort order.
     *
     * @return array<string, LocationInterface>
     */
    public function getShowrooms(): array
    {
        if ($this->showrooms === null) {
            $this->showrooms = [];
            foreach ($this->locationQuery->list() as $location) {
                if ($this->presenter->isShowroom($location)) {
                    $this->showrooms[$location->getUrlKey()] = $location;
                }
            }
        }

        return $this->showrooms;
    }

    public function getSelectedShowroom(): ?LocationInterface
    {
        $showrooms = $this->getShowrooms();
        $key = $this->getFieldValue('showroom') ?: (string) $this->request->getParam('showroom');

        return $showrooms[$key] ?? (reset($showrooms) ?: null);
    }

    public function getPresenter(): LocationPresenter
    {
        return $this->presenter;
    }

    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getPageUrl(): string
    {
        return $this->urlBuilder->getDirectUrl(self::PAGE_PATH);
    }

    public function getFormAction(): string
    {
        return $this->urlBuilder->getUrl('showroom-locator/appointment/post', ['_secure' => true]);
    }

    /**
     * Login that comes back here afterwards.
     */
    public function getLoginUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/account/login', [
            'referer' => $this->urlEncoder->encode($this->getPageUrl()),
        ]);
    }

    public function getApplyUrl(): string
    {
        return $this->presenter->getApplyUrl();
    }

    /**
     * Open days and slots per showroom for the date and time fields:
     * {url_key: {days: {monday: ["09:00", ...]}, holidays: {"2026-12-25": []}}}.
     */
    public function getScheduleJson(): string
    {
        $data = [];
        foreach ($this->getShowrooms() as $key => $location) {
            $data[$key] = [
                'days' => $this->schedule->weekdaySlots($location),
                'holidays' => (object) $this->schedule->holidaySlots($location),
            ];
        }

        return (string) $this->json->serialize((object) $data);
    }

    /**
     * Short opening hours, consecutive days with the same hours merged: "Mon-Fri 9am - 5pm".
     */
    public function getOpenDaysLabel(LocationInterface $location): string
    {
        $hours = $this->presenter->getHours();
        $groups = [];
        foreach ($hours->dayLabels($location->getOpeningHours()) as $day => $label) {
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['label'] === $label) {
                $groups[$last]['to'] = $day;
                continue;
            }
            $groups[] = ['from' => $day, 'to' => $day, 'label' => $label];
        }

        $closed = (string) __('Closed');
        $parts = [];
        foreach ($groups as $group) {
            if ($group['label'] === $closed) {
                continue;
            }
            $days = (string) __(Hours::DAY_SHORT[$group['from']]);
            if ($group['to'] !== $group['from']) {
                $days .= "\u{2013}" . __(Hours::DAY_SHORT[$group['to']]);
            }
            $parts[] = $days . ' ' . $group['label'];
        }

        return implode('; ', $parts);
    }

    /**
     * Open-days summary per showroom url key, for the hint under the date field.
     *
     * @return array<string, string>
     */
    public function getOpenDaysLabels(): array
    {
        return array_map(fn (LocationInterface $location) => $this->getOpenDaysLabel($location), $this->getShowrooms());
    }

    /**
     * @return array<string, string> slot => label
     */
    public function getTimeSlots(): array
    {
        $slots = [];
        foreach ($this->schedule->allSlots($this->getShowrooms()) as $slot) {
            $slots[$slot] = $this->schedule->slotLabel($slot);
        }

        return $slots;
    }

    public function getMinDate(): string
    {
        return $this->today()->modify('+1 day')->format('Y-m-d');
    }

    public function getMaxDate(): string
    {
        return $this->today()->modify('+' . Validator::BOOK_AHEAD_DAYS . ' days')->format('Y-m-d');
    }

    public function today(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable($this->timezone->date())->setTime(0, 0);
    }

    public function getVisitorsMax(): int
    {
        return Validator::VISITORS_MAX;
    }

    public function getNotesMax(): int
    {
        return Validator::NOTES_MAX;
    }

    /**
     * @return array<string, string>
     */
    public function getInterests(): array
    {
        return Validator::INTERESTS;
    }

    /**
     * @return list<string>
     */
    public function getSelectedInterests(): array
    {
        $values = $this->getPersisted()['interests'] ?? [];

        return array_values(array_filter((array) $values, 'is_string'));
    }

    public function getFieldValue(string $key): string
    {
        $value = $this->getPersisted()[$key] ?? null;
        if (is_scalar($value) && (string) $value !== '') {
            return (string) $value;
        }

        return $this->getPrefill()[$key] ?? '';
    }

    /**
     * Summary of the request that was just sent, once: it is cleared when read.
     *
     * @return array<string, mixed>|null
     */
    public function takeSentRequest(): ?array
    {
        $sent = $this->dataPersistor->get(self::PERSIST_SENT_KEY);
        if (!is_array($sent) || $sent === []) {
            return null;
        }
        $this->dataPersistor->clear(self::PERSIST_SENT_KEY);

        return $sent;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPersisted(): array
    {
        if ($this->persisted === null) {
            $data = $this->dataPersistor->get(self::PERSIST_KEY);
            $this->persisted = is_array($data) ? $data : [];
            $this->dataPersistor->clear(self::PERSIST_KEY);
        }

        return $this->persisted;
    }

    /**
     * @return array<string, string>
     */
    private function getPrefill(): array
    {
        if ($this->prefill !== null) {
            return $this->prefill;
        }
        $this->prefill = [];
        if (!$this->customerSession->isLoggedIn()) {
            return $this->prefill;
        }

        try {
            $customer = $this->customerRepository->getById((int) $this->customerSession->getCustomerId());
        } catch (\Exception) {
            return $this->prefill;
        }

        $company = '';
        $telephone = '';
        $companyAttr = $customer->getCustomAttribute('company');
        if ($companyAttr) {
            $company = trim((string) $companyAttr->getValue());
        }
        foreach ($customer->getAddresses() ?? [] as $address) {
            $company = $company !== '' ? $company : trim((string) $address->getCompany());
            $telephone = $telephone !== '' ? $telephone : trim((string) $address->getTelephone());
        }

        $this->prefill = [
            'name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
            'email' => (string) $customer->getEmail(),
            'company' => $company,
            'telephone' => $telephone,
        ];

        return $this->prefill;
    }
}
