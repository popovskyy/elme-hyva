<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\Theme\ViewModel\ContentPage;
use Elme\Theme\ViewModel\Team;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see Team
 */
class TeamTest extends TestCase
{
    private function team(): Team
    {
        return new Team(
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(Filesystem::class),
            $this->createMock(CmsPage::class),
            $this->createMock(EntryRepositoryInterface::class),
            $this->createMock(ContentPage::class)
        );
    }

    public function testPublishedPeopleKeepRegionLabelsAndMailto(): void
    {
        $people = $this->team()->getPeople([
            'people' => [
                [
                    'name' => 'Helena Yetsenga',
                    'role' => 'VIC Business Development Manager',
                    'email' => 'helena.yetsenga@elmeliving.com',
                    'phone' => '+61 404 383 155',
                    'region' => 'VIC',
                    'is_visible' => true,
                ],
            ],
        ]);

        $this->assertCount(1, $people);
        $this->assertSame('VIC', $people[0]['region_label']);
        $this->assertSame('helena-yetsenga', $people[0]['slug']);
        $this->assertSame('mailto:helena.yetsenga@elmeliving.com', $people[0]['mailto']);
        $this->assertSame('+61404383155', $people[0]['tel']);
    }

    public function testHiddenIncompleteAndUnknownRegionRowsAreLeftOut(): void
    {
        $people = $this->team()->getPeople([
            'people' => [
                [
                    'name' => 'Hidden',
                    'role' => 'CEO',
                    'region' => 'head_office',
                    'is_visible' => false,
                ],
                [
                    'name' => 'No Role',
                    'role' => ' ',
                    'region' => 'VIC',
                ],
                [
                    'name' => 'Bad Region',
                    'role' => 'Rep',
                    'region' => 'NZ',
                ],
                'not a row',
            ],
        ]);

        $this->assertSame([], $people);
    }

    public function testTelHrefNormalisesAuNumbers(): void
    {
        $team = $this->team();

        $this->assertSame('+61404383155', $team->telHref('+61 404 383 155'));
        $this->assertSame('+61419361373', $team->telHref('+ 61419 361 373'));
        $this->assertSame('+61370679725', $team->telHref('03 7067 9725'));
        $this->assertSame('', $team->telHref('  '));
    }

    public function testTitleAndIntroFallbacks(): void
    {
        $team = $this->team();

        $this->assertSame(Team::DEFAULT_TITLE, $team->getTitle(['title' => ' ']));
        $this->assertSame('', $team->getIntro(['intro' => '<p> </p>']));
        $this->assertSame('<p>One.</p>', $team->getIntro(['intro' => '<p>One.<\\/p>']));
        $this->assertSame('', $team->getHeading([]));
    }

    public function testChipsStartWithAllThenEveryRegion(): void
    {
        $chips = $this->team()->getChips();

        $this->assertSame('all', $chips[0]['code']);
        $this->assertSame('SA_TAS', $chips[array_key_last($chips)]['code']);
        $this->assertSame('SA-TAS', $chips[array_key_last($chips)]['label']);
    }

    public function testPresentRegionsAreUniqueInOrder(): void
    {
        $present = $this->team()->getPresentRegions([
            ['region' => 'VIC'],
            ['region' => 'NSW'],
            ['region' => 'VIC'],
        ]);

        $this->assertSame(['VIC', 'NSW'], $present);
    }
}
