<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Content Studio type `vacancies` (etc/content_types.xml, FE-62): the open roles of the Careers page.
 *
 * A role is listed only when it has a title, a known state and type, an address to apply to and is
 * switched on; anything else is left out of the HTML entirely. The state is stored as its code and
 * shown in full; employment types are stored as schema.org employmentType values (CASUAL has no
 * schema.org value of its own) so a later JobPosting needs no data migration.
 */
class Vacancies implements ArgumentInterface
{
    public const MAX_ROLES = 20;

    public const STATES = [
        'ACT' => 'Australian Capital Territory',
        'NSW' => 'New South Wales',
        'NT' => 'Northern Territory',
        'QLD' => 'Queensland',
        'SA' => 'South Australia',
        'TAS' => 'Tasmania',
        'VIC' => 'Victoria',
        'WA' => 'Western Australia',
    ];

    public const EMPLOYMENT_TYPES = [
        'FULL_TIME' => 'Full-time',
        'PART_TIME' => 'Part-time',
        'CASUAL' => 'Casual',
        'CONTRACTOR' => 'Contract',
    ];

    public const DEFAULT_HEADING = 'Current vacancies';

    public const DEFAULT_EMPTY_TEXT = 'We have no vacancies right now, but we are always glad to hear from people'
        . ' who love design and service.';

    /**
     * @param array<string, mixed> $content Block\Entry::getContent()
     * @return list<array{
     *     title: string,
     *     state: string,
     *     state_name: string,
     *     employment_type: string,
     *     employment_label: string,
     *     email: string,
     *     mailto: string
     * }>
     */
    public function getRoles(array $content): array
    {
        $fallbackEmail = $this->getEmail($content);
        $roles = [];

        foreach ((array)($content['roles'] ?? []) as $row) {
            if (!is_array($row) || !$this->isOpen($row)) {
                continue;
            }

            $title = $this->text($row, 'title');
            $state = $this->text($row, 'state');
            $type = $this->text($row, 'employment_type');
            $email = $this->email($this->text($row, 'email')) ?? $fallbackEmail;

            if ($title === '' || !isset(self::STATES[$state], self::EMPLOYMENT_TYPES[$type]) || $email === '') {
                continue;
            }

            $roles[] = [
                'title' => $title,
                'state' => $state,
                'state_name' => self::STATES[$state],
                'employment_type' => $type,
                'employment_label' => self::EMPLOYMENT_TYPES[$type],
                'email' => $email,
                'mailto' => $this->mailto($email, sprintf('Application: %s (%s)', $title, self::STATES[$state])),
            ];

            if (count($roles) === self::MAX_ROLES) {
                break;
            }
        }

        return $roles;
    }

    /**
     * The one careers address of the page, '' when it is missing or not an address.
     *
     * @param array<string, mixed> $content
     */
    public function getEmail(array $content): string
    {
        return $this->email($this->text($content, 'email')) ?? '';
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getMailto(array $content): string
    {
        $email = $this->getEmail($content);

        return $email !== '' ? $this->mailto($email, 'Job application') : '';
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getHeading(array $content): string
    {
        $heading = $this->text($content, 'heading');

        return $heading !== '' ? $heading : self::DEFAULT_HEADING;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getEmptyText(array $content): string
    {
        $text = $this->text($content, 'empty_text');

        return $text !== '' ? $text : self::DEFAULT_EMPTY_TEXT;
    }

    /**
     * Intro HTML as the editor's sanitiser stores it; the template escapes it to the same allow-list.
     * A JSON solidus escape left in the string (<\/p>) is normalised so it cannot leak as visible text.
     *
     * @param array<string, mixed> $content
     */
    public function getIntro(array $content): string
    {
        $intro = str_replace('\\/', '/', $this->text($content, 'intro'));

        return trim(strip_tags($intro)) !== '' ? $intro : '';
    }

    /**
     * A missing `is_open` reads as the schema default (on), so an imported row behaves like one made in
     * the editor.
     *
     * @param array<string, mixed> $row
     */
    private function isOpen(array $row): bool
    {
        return !array_key_exists('is_open', $row) || !empty($row['is_open']);
    }

    private function email(string $value): ?string
    {
        $email = filter_var($value, FILTER_VALIDATE_EMAIL);

        return is_string($email) ? $email : null;
    }

    private function mailto(string $email, string $subject): string
    {
        return 'mailto:' . $email . '?subject=' . rawurlencode($subject);
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
