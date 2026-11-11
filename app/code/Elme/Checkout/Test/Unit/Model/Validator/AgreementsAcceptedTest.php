<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Validator;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Validator\AgreementsAccepted;
use Magento\CheckoutAgreements\Model\AgreementsProvider;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AgreementsAcceptedTest extends TestCase
{
    public function testUntickedAgreementPointsAtItsCheckbox(): void
    {
        try {
            $this->validator(['1', '2'])->validate($this->createStub(Quote::class), ['agreements' => ['1']]);
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(AgreementsAccepted::SECTION, $exception->getSection());
            $this->assertSame('Please accept the terms and conditions.', (string) $exception->getMessage());
            $this->assertSame(['agreement_2'], array_keys($exception->getFieldErrors()));
        }
    }

    public function testNoInputRefusesARequiredAgreement(): void
    {
        try {
            $this->validator(['1'])->validate($this->createStub(Quote::class));
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame('Please accept the terms and conditions.', (string) $exception->getMessage());
            $this->assertSame(['agreement_1'], array_keys($exception->getFieldErrors()));
        }
    }

    #[DoesNotPerformAssertions]
    public function testAllTickedPasses(): void
    {
        $this->validator(['1', '2'])->validate($this->createStub(Quote::class), ['agreements' => ['2', '1']]);
    }

    #[DoesNotPerformAssertions]
    public function testNothingRequiredPasses(): void
    {
        $this->validator([])->validate($this->createStub(Quote::class));
    }

    /**
     * @param string[] $required
     */
    private function validator(array $required): AgreementsAccepted
    {
        $provider = $this->createStub(AgreementsProvider::class);
        $provider->method('getRequiredAgreementIds')->willReturn($required);

        return new AgreementsAccepted($provider);
    }
}
