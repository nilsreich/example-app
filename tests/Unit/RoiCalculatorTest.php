<?php

namespace Tests\Unit;

use App\Services\RoiCalculatorService;
use PHPUnit\Framework\TestCase;

class RoiCalculatorTest extends TestCase
{
    private RoiCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new RoiCalculatorService;
    }

    public function test_saved_costs_follow_disponenten_formula(): void
    {
        // 2 Konflikte × 45 Min × 65 €/h = 97,50 €.
        $this->assertSame(97.5, $this->calculator->savedCostsEur(2));
        $this->assertSame(0.0, $this->calculator->savedCostsEur(0));
        $this->assertSame(0.0, $this->calculator->savedCostsEur(-3));
    }

    public function test_automation_rate_is_null_without_base(): void
    {
        $this->assertSame(75.0, $this->calculator->automationRate(3, 4));
        $this->assertNull($this->calculator->automationRate(0, 0));
    }

    public function test_average_confidence_is_null_without_scores(): void
    {
        $this->assertSame(85.0, $this->calculator->averageConfidence([90, 80]));
        $this->assertNull($this->calculator->averageConfidence([]));
    }
}
