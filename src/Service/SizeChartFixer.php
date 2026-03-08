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
 * Fixer for IQIT size chart shop associations
 * Diagnoses and repairs missing entries in iqitsizechart_shop table
 */
class SizeChartFixer extends AbstractFixer
{
    public function getSupportedTypes(): array
    {
        return ['sizechart_shop'];
    }

    public function preview(string $type): array
    {
        return match ($type) {
            'sizechart_shop' => $this->previewSizeChartShop(),
            default => ['error' => 'Unsupported type', 'success' => false],
        };
    }

    public function apply(string $type): array
    {
        return match ($type) {
            'sizechart_shop' => $this->applySizeChartShop(),
            default => ['error' => 'Unsupported type', 'success' => false],
        };
    }

    /**
     * Diagnose missing associations between size charts and shops
     *
     * @return array<string, mixed>
     */
    public function diagnose(): array
    {
        $charts = $this->connection->fetchAllAssociative(
            "SELECT id_iqitsizechart FROM {$this->prefix}iqitsizechart ORDER BY id_iqitsizechart"
        );

        $shops = $this->connection->fetchAllAssociative(
            "SELECT id_shop, name FROM {$this->prefix}shop WHERE active = 1 ORDER BY id_shop"
        );

        $existingAssociations = $this->connection->fetchAllAssociative(
            "SELECT id_iqitsizechart, id_shop FROM {$this->prefix}iqitsizechart_shop"
        );

        // Build a set of existing associations for fast lookup
        $existingSet = [];
        foreach ($existingAssociations as $assoc) {
            $existingSet[$assoc['id_iqitsizechart'] . '_' . $assoc['id_shop']] = true;
        }

        // Compute missing combinations
        $missing = [];
        foreach ($charts as $chart) {
            $chartId = (int) $chart['id_iqitsizechart'];
            foreach ($shops as $shop) {
                $shopId = (int) $shop['id_shop'];
                $key = $chartId . '_' . $shopId;
                if (!isset($existingSet[$key])) {
                    $missing[] = [
                        'id_iqitsizechart' => $chartId,
                        'id_shop' => $shopId,
                    ];
                }
            }
        }

        $missingCount = count($missing);
        $status = $missingCount > 0 ? 'error' : 'ok';

        return [
            'status' => $status,
            'total_charts' => count($charts),
            'total_shops' => count($shops),
            'existing_associations' => count($existingAssociations),
            'missing_associations' => $missingCount,
            'missing' => $missing,
            'shops' => $shops,
        ];
    }

    private function previewSizeChartShop(): array
    {
        $diagnosis = $this->diagnose();

        return [
            'success' => true,
            'type' => 'sizechart_shop',
            'description' => 'Association des guides des tailles a toutes les boutiques',
            'changes' => [
                'total_charts' => $diagnosis['total_charts'],
                'total_shops' => $diagnosis['total_shops'],
                'existing_associations' => $diagnosis['existing_associations'],
                'missing_associations' => $diagnosis['missing_associations'],
            ],
            'entries_to_insert' => $diagnosis['missing'],
            'shops' => $diagnosis['shops'],
        ];
    }

    private function applySizeChartShop(): array
    {
        try {
            $diagnosis = $this->diagnose();
            $inserted = 0;
            $details = [];

            foreach ($diagnosis['missing'] as $entry) {
                $this->connection->executeStatement(
                    "INSERT IGNORE INTO {$this->prefix}iqitsizechart_shop (id_iqitsizechart, id_shop) VALUES (?, ?)",
                    [
                        $entry['id_iqitsizechart'],
                        $entry['id_shop'],
                    ]
                );

                ++$inserted;
                $details[] = [
                    'id_iqitsizechart' => $entry['id_iqitsizechart'],
                    'id_shop' => $entry['id_shop'],
                    'action' => 'inserted',
                ];
            }

            return [
                'success' => true,
                'type' => 'sizechart_shop',
                'inserted' => $inserted,
                'details' => $details,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
