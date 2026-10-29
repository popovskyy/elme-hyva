<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\Vacancies;
use PHPUnit\Framework\TestCase;

/**
 * A role that should be gone (filled, switched off, half filled in) must not reach the HTML, and a
 * mailto must stay a valid URL whatever the editor typed in the title.
 *
 * @see Vacancies
 */
class VacanciesTest extends TestCase
{
    private const ROLE = [
        'title' => 'Showroom Sales Consultant',
        'state' => 'VIC',
        'employment_type' => 'FULL_TIME',
        'email' => '',
        'is_open' => true,
    ];

    public function testOpenRolesAreListedWithFullStateAndTypeLabel(): void
    {
        $roles = (new Vacancies())->getRoles([
            'email' => 'careers@example.com',
            'roles' => [self::ROLE, ['title' => 'Warehouse Picker', 'state' => 'NSW', 'employment_type' => 'CASUAL']],
        ]);

        $this->assertCount(2, $roles);
        $this->assertSame('Victoria', $roles[0]['state_name']);
        $this->assertSame('Full-time', $roles[0]['employment_label']);
        $this->assertSame('New South Wales', $roles[1]['state_name']);
        $this->assertSame('Casual', $roles[1]['employment_label']);
    }

    public function testSwitchedOffAndIncompleteRolesAreLeftOut(): void
    {
        $roles = (new Vacancies())->getRoles([
            'email' => 'careers@example.com',
            'roles' => [
                ['is_open' => false] + self::ROLE,
                ['is_open' => '0'] + self::ROLE,
                ['title' => '  '] + self::ROLE,
                ['state' => 'XX'] + self::ROLE,
                ['employment_type' => 'INTERN'] + self::ROLE,
                'not a row',
            ],
        ]);

        $this->assertSame([], $roles);
    }

    public function testRoleEmailFallsBackToTheCareersAddress(): void
    {
        $viewModel = new Vacancies();
        $content = ['email' => ' careers@example.com ', 'roles' => [
            self::ROLE,
            ['email' => 'store@example.com'] + self::ROLE,
            ['email' => 'not an address'] + self::ROLE,
        ]];

        $emails = array_column($viewModel->getRoles($content), 'email');

        $this->assertSame(['careers@example.com', 'store@example.com', 'careers@example.com'], $emails);
    }

    public function testNoAddressAnywhereDropsTheRole(): void
    {
        $this->assertSame([], (new Vacancies())->getRoles(['email' => 'nope', 'roles' => [self::ROLE]]));
    }

    public function testMailtoSubjectIsEncoded(): void
    {
        $roles = (new Vacancies())->getRoles([
            'email' => 'careers@example.com',
            'roles' => [['title' => 'Sales & Service Lead?'] + self::ROLE],
        ]);

        $this->assertSame(
            'mailto:careers@example.com?subject=Application%3A%20Sales%20%26%20Service%20Lead%3F%20%28Victoria%29',
            $roles[0]['mailto']
        );
    }

    public function testHeadingAndEmptyTextFallBackToDefaults(): void
    {
        $viewModel = new Vacancies();

        $this->assertSame(Vacancies::DEFAULT_HEADING, $viewModel->getHeading(['heading' => ' ']));
        $this->assertSame(Vacancies::DEFAULT_EMPTY_TEXT, $viewModel->getEmptyText([]));
        $this->assertSame('', $viewModel->getIntro(['intro' => '<p> </p>']));
    }
}
