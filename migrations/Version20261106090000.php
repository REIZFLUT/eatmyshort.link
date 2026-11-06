<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Indexes the unique-visitor probe.
 *
 * The click counter no longer answers that question with a RETURNING clause - MySQL cannot do
 * RETURNING on UPDATE - so ClickRecorder runs an indexed SELECT first: "clicked_day = ? AND
 * visitor_hash = ?". With only idx_click_day the engine narrows to a single day and then reads
 * every row of it (measured on a day with ~550 clicks: ~7.4 ms, growing linearly with traffic),
 * whereas a composite index answers it in ~0.16 ms.
 *
 * (clicked_day, visitor_hash) also serves everything that filters on clicked_day alone - day
 * ranges in the statistics and the prune command - which makes the single-column idx_click_day
 * redundant. It is dropped so that the hot INSERT path does not maintain three indexes.
 */
final class Version20261106090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Indexes the unique-visitor probe: (clicked_day, visitor_hash) replaces (clicked_day)';
    }

    public function up(Schema $schema): void
    {
        $click = $schema->getTable('click');
        $click->addIndex(['clicked_day', 'visitor_hash'], 'idx_click_day_visitor');
        $click->dropIndex('idx_click_day');
    }

    public function down(Schema $schema): void
    {
        $click = $schema->getTable('click');
        $click->addIndex(['clicked_day'], 'idx_click_day');
        $click->dropIndex('idx_click_day_visitor');
    }
}
