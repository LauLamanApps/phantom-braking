<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Where the driving data a car embedded in its dashcam footage is stored, and when the footage was read for it.
 * Run `app:footage:extract-telemetry` afterwards to read the footage that was already there.
 */
final class Version20261115222726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Driving data embedded in dashcam footage (Tesla SEI), stored next to the video';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident ADD footage_telemetry_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD footage_telemetry_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident DROP footage_telemetry_path');
        $this->addSql('ALTER TABLE incident DROP footage_telemetry_checked_at');
    }
}
