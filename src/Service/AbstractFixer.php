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

use Doctrine\DBAL\Connection;

/**
 * Base class for all iqit fixer services
 */
abstract class AbstractFixer
{
    public function __construct(
        protected Connection $connection,
        protected string $prefix
    ) {
    }

    /**
     * @return array<string>
     */
    abstract public function getSupportedTypes(): array;

    /**
     * @return array<string, mixed>
     */
    abstract public function preview(string $type): array;

    /**
     * @return array<string, mixed>
     */
    abstract public function apply(string $type): array;
}
