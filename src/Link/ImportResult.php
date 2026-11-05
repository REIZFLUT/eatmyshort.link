<?php

declare(strict_types=1);

namespace App\Link;

/**
 * Structured outcome of a CSV import run: counters plus a small, bounded list of
 * row findings for display. A preview leaves created/skipped at zero.
 */
final readonly class ImportResult
{
    /**
     * @param list<string>    $columns recognised target fields, in header order
     * @param list<ImportRow> $rows    bounded findings for the report table
     */
    public function __construct(
        public int $total,
        public int $valid,
        public int $invalid,
        public int $duplicates,
        public int $created,
        public int $skipped,
        public array $columns,
        public array $rows,
    ) {
    }
}
