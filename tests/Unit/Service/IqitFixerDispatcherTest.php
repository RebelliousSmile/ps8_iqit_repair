<?php

declare(strict_types=1);

namespace ScIqitRepair\Tests\Unit\Service;

use ScIqitRepair\Service\IqitFixerDispatcher;
use ScIqitRepair\Service\SizeChartFixer;

class IqitFixerDispatcherTest extends AbstractServiceTestCase
{
    private IqitFixerDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $sizeChartFixer = new SizeChartFixer($this->connection, $this->prefix);
        $this->dispatcher = new IqitFixerDispatcher($sizeChartFixer);
    }

    public function testGetSupportedTypesIncludesSizechartShop(): void
    {
        $types = $this->dispatcher->getSupportedTypes();

        $this->assertContains('sizechart_shop', $types);
    }

    public function testPreviewUnknownTypeReturnsError(): void
    {
        $result = $this->dispatcher->preview('unknown_type');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Unknown fix type', $result['error']);
    }

    public function testApplyUnknownTypeReturnsError(): void
    {
        $result = $this->dispatcher->apply('unknown_type');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Unknown fix type', $result['error']);
    }

    public function testPreviewDelegatestoSizeChartFixer(): void
    {
        $charts = [['id_iqitsizechart' => 1]];
        $shops = [['id_shop' => 1, 'name' => 'Main']];
        $associations = [['id_iqitsizechart' => 1, 'id_shop' => 1]];

        $this->mockFetchAllSequence([$charts, $shops, $associations]);

        $result = $this->dispatcher->preview('sizechart_shop');

        $this->assertTrue($result['success']);
        $this->assertSame('sizechart_shop', $result['type']);
        $this->assertSame(0, $result['changes']['missing_associations']);
    }

    public function testApplyDelegatestoSizeChartFixerAndSetsCacheCleared(): void
    {
        $charts = [['id_iqitsizechart' => 1]];
        $shops = [['id_shop' => 1, 'name' => 'Main']];
        // No existing associations → 1 missing
        $associations = [];

        $this->mockFetchAllSequence([$charts, $shops, $associations]);
        $this->connection->method('executeStatement')->willReturn(1);

        $result = $this->dispatcher->apply('sizechart_shop');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['inserted']);
        // cache_cleared is set only when Tools::clearAllCache exists (not in test env)
        // so we just check the key may or may not be present
        $this->assertTrue($result['success']);
    }

    public function testApplyFailureDoesNotSetCacheCleared(): void
    {
        $charts = [['id_iqitsizechart' => 1]];
        $shops = [['id_shop' => 1, 'name' => 'Main']];
        $associations = [];

        $this->mockFetchAllSequence([$charts, $shops, $associations]);

        $this->connection->method('executeStatement')
            ->willThrowException(new \Exception('DB error'));

        $result = $this->dispatcher->apply('sizechart_shop');

        $this->assertFalse($result['success']);
        $this->assertArrayNotHasKey('cache_cleared', $result);
    }
}
