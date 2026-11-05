<?php

declare(strict_types=1);

namespace App\Link;

/**
 * Finding for one CSV data line. Translated messages, so the template renders
 * them directly. Never holds the entity, only what is needed for the report.
 */
final readonly class ImportRow
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public int $line,
        public string $code,
        public string $targetUrl,
        public ImportRowStatus $status,
        public array $errors = [],
    ) {
    }
}
