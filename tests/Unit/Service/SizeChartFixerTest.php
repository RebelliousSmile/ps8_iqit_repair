<?php

declare(strict_types=1);

namespace ScIqitRepair\Tests\Unit\Service;

use ScIqitRepair\Service\SizeChartFixer;

class SizeChartFixerTest extends AbstractServiceTestCase
{
    private SizeChartFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new SizeChartFixer($this->connection, $this->prefix);
    }

    public function testGetSupportedTypes(): void
    {
        $this->assertSame(['sizechart_shop'], $this->fixer->getSupportedTypes());
    }

    public function testDiagnoseReturnsOkWhenAllAssociationsExist(): void
    {
        // 2 charts, 2 shops, 4 existing associations → all OK
        $charts = [
            ['id_iqitsizechart' => 1],
            ['id_iqitsizechart' => 2],
        ];
        $shops = [
            ['id_shop' => 10, 'name' => 'Shop A'],
            ['id_shop' => 12, 'name' => 'Shop B'],
        ];
        $associations = [
            ['id_iqitsizechart' => 1, 'id_shop' => 10],
            ['id_iqitsizechart' => 1, 'id_shop' => 12],
            ['id_iqitsizechart' => 2, 'id_shop' => 10],
            ['id_iqitsizechart' => 2, 'id_shop' => 12],
        ];

        $this->mockFetchAllSequence([$charts, $shops, $associations]);

        $result = $this->fixer->diagnose();

        $this->assertSame('ok', $result['status']);
        $this->assertSame(2, $result['total_charts']);
        $this->assertSame(2, $result['total_shops']);
        $this->assertSame(4, $result['existing_associations']);
        $this->assertSame(0, $result['missing_associations']);
        $this->assertEmpty($result['missing']);
    }

    public function testDiagnoseReturnsMissingWhenSomeAssociationsAbsent(): void
    {
        // 2 charts, 2 shops, but only 2 associations → 2 missing
        $charts = [
            ['id_iqitsizechart' => 1],
            ['id_iqitsizechart' => 2],
        ];
        $shops = [
            ['id_shop' => 10, 'name' => 'Shop A'],
            ['id_shop' => 12, 'name' => 'Shop B'],
        ];
        $associations = [
            ['id_iqitsizechart' => 1, 'id_shop' => 10],
            ['id_iqitsizechart' => 2, 'id_shop' => 10],
        ];

        $this->mockFetchAllSequence([$charts, $shops, $associations]);

        $result = $this->fixer->diagnose();

        $this->assertSame('error', $result['status']);
        $this->assertSame(2, $result['missing_associations']);
        $this->assertCount(2, $result['missing']);
        // Both missing should be for shop 12
        foreach ($result['missing'] as $missing) {
            $this->assertSame(12, $missing['id_shop']);
        }
    }

    public function testPreviewReturnsMissingAssociations(): void
    {
        $charts = [['id_iqitsizechart' => 5]];
        $shops = [
            ['id_shop' => 1, 'name' => 'Main'],
            ['id_shop' => 10, 'name' => 'Shop B'],
        ];
        $associations = [['id_iqitsizechart' => 5, 'id_shop' => 1]];

        $this->mockFetchAllSequence([$charts, $shops, $associations]);

        $result = $this->fixer->preview('sizechart_shop');

        $this->assertTrue($result['success']);
        $this->assertSame('sizechart_shop', $result['type']);
        $this->assertArrayHasKey('changes', $result);
        $this->assertArrayHasKey('entries_to_insert', $result);
        $this->assertSame(1, $result['changes']['missing_associations']);
        $this->assertCount(1, $result['entries_to_insert']);
        $this->assertSame(5, $result['entries_to_insert'][0]['id_iqitsizechart']);
        $this->assertSame(10, $result['entries_to_insert'][0]['id_shop']);
    }

    public function testApplyInsertsAllMissingAssociations(): void
    {
        $charts = [
            ['id_iqitsizechart' => 1],
            ['id_iqitsizechart' => 2],
        ];
        $shops = [
            ['id_shop' => 10, 'name' => 'Shop A'],
            ['id_shop' => 12, 'name' => 'Shop B'],
        ];
        $associations = [['id_iqitsizechart' => 1, 'id_shop' => 10]];

        $this->mockFetchAllSequence([$charts, $shops, $associations]);

        $this->connection->expects($this->exactly(3))
            ->method('executeStatement');

        $result = $this->fixer->apply('sizechart_shop');

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['inserted']);
    }

    public function testApplyReturnsSuccessWithInsertedCount(): void
    {
        $charts = [['id_iqitsizechart' => 3]];
        $shops = [
            ['id_shop' => 1, 'name' => 'Shop 1'],
            ['id_shop' => 2, 'name' => 'Shop 2'],
        ];
        $associations = [];

        $this->mockFetchAllSequence([$charts, $shops, $associations]);

        $this->connection->method('executeStatement')->willReturn(1);

        $result = $this->fixer->apply('sizechart_shop');

        $this->assertTrue($result['success']);
        $this->assertSame('sizechart_shop', $result['type']);
        $this->assertSame(2, $result['inserted']);
        $this->assertCount(2, $result['details']);
        foreach ($result['details'] as $detail) {
            $this->assertSame('inserted', $detail['action']);
        }
    }

    public function testPreviewUnsupportedTypeReturnsError(): void
    {
        $result = $this->fixer->preview('unknown_type');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testApplyUnsupportedTypeReturnsError(): void
    {
        $result = $this->fixer->apply('unknown_type');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
}
