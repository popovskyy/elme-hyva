<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\StockistCta;
use PHPUnit\Framework\TestCase;

/**
 * @see StockistCta
 */
class StockistCtaTest extends TestCase
{
    public function testPrimaryAndSecondaryRequireBothLabelAndLink(): void
    {
        $cta = new StockistCta();
        $content = [
            'heading' => 'Become a stockist',
            'primary_label' => 'Create an Account',
            'primary_link' => '',
            'secondary_label' => 'Talk to our team',
            'secondary_link' => '/meet-the-team',
        ];

        $this->assertNull($cta->getPrimaryButton($content));
        $this->assertSame(
            ['label' => 'Talk to our team', 'link' => '/meet-the-team'],
            $cta->getSecondaryButton($content)
        );
    }
}
