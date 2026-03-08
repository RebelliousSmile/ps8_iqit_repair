<?php
/**
 * SC IQIT Repair - PrestaShop 8 Module
 *
 * @author    Scriptami
 * @copyright Scriptami
 * @license   Academic Free License version 3.0
 */

declare(strict_types=1);

namespace ScIqitRepair\Service;

/**
 * Dispatches fix operations to the appropriate iqit fixer service
 */
class IqitFixerDispatcher
{
    /** @var array<string, AbstractFixer> */
    private array $fixers = [];

    public function __construct(SizeChartFixer $sizeChart)
    {
        foreach ([$sizeChart] as $fixer) {
            foreach ($fixer->getSupportedTypes() as $type) {
                $this->fixers[$type] = $fixer;
            }
        }
    }

    /**
     * Preview a fix (dry-run)
     *
     * @return array<string, mixed>
     */
    public function preview(string $type): array
    {
        $fixer = $this->fixers[$type] ?? null;
        if ($fixer === null) {
            return ['error' => 'Unknown fix type', 'success' => false];
        }

        return $fixer->preview($type);
    }

    /**
     * Apply a fix
     *
     * @return array<string, mixed>
     */
    public function apply(string $type): array
    {
        $fixer = $this->fixers[$type] ?? null;
        if ($fixer === null) {
            return ['error' => 'Unknown fix type', 'success' => false];
        }

        $result = $fixer->apply($type);

        if ($result['success'] ?? false) {
            $this->clearPrestaShopCache();
            $result['cache_cleared'] = true;
        }

        return $result;
    }

    /**
     * Get all supported fix types
     *
     * @return array<string>
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->fixers);
    }

    /**
     * Clear all PrestaShop caches (Smarty + Symfony)
     */
    private function clearPrestaShopCache(): void
    {
        try {
            if (class_exists(\Tools::class)) {
                \Tools::clearAllCache();
            }
        } catch (\Exception $e) {
            // Silently fail - cache clearing is not critical
        }
    }
}
