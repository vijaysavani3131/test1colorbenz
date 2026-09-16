<?php

namespace Tests\Unit;

use App\Services\CaseWorkflowService;
use PHPUnit\Framework\TestCase;

class CaseWorkflowServiceTest extends TestCase
{
    public function test_catalog_exposes_all_supported_services_with_unique_slugs(): void
    {
        $service = new CaseWorkflowService();
        $catalog = $service->catalog();
        $slugs = array_column($catalog, 'slug');

        $this->assertCount(7, $catalog);
        $this->assertCount(7, array_unique($slugs));
        $this->assertSame([
            'money_recovery',
            'after_sales',
            'identity_repair',
            'move_life',
            'marriage_sync',
            'new_baby',
            'after_loss',
        ], $slugs);
    }

    public function test_money_recovery_required_fields_are_stable(): void
    {
        $service = new CaseWorkflowService();

        $this->assertSame([
            'merchant',
            'amount',
            'issue_date',
            'reference',
            'summary',
        ], $service->requiredKeys('money_recovery'));
    }

    public function test_after_loss_requires_human_review_first(): void
    {
        $service = new CaseWorkflowService();
        $definition = $service->find('after_loss');

        $this->assertNotNull($definition);
        $this->assertTrue($definition['human_review_first']);
        $this->assertSame('red', $definition['risk_tier']);
    }
}
