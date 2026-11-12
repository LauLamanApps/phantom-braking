<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112134534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Records when a reporter confirmed reporting to the ILT on the prompt after reporting.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident ADD reported_to_authority_via_prompt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident DROP reported_to_authority_via_prompt_at');
    }
}
