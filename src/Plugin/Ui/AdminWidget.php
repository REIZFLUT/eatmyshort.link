<?php

declare(strict_types=1);

namespace App\Plugin\Ui;

/**
 * A card a plugin adds to the dashboard.
 *
 * @api
 *
 * @since 1.0
 */
final class AdminWidget
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $value,
        public readonly ?string $hint = null,
        public readonly ?string $url = null,
    ) {
    }
}
