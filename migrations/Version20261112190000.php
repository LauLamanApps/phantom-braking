<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Moves the existing reports, photos and admins from UUIDv4 to v7, with the time in each new ID taken from when the
 * row was created, so all IDs sort by age. Every old ID is kept in renamed_id; old links redirect to the new one.
 *
 * Passkeys are deleted: each device stores the admin's old ID as its user handle, so they can no longer log in.
 * Afterwards, log in with `app:admin:login-link <name>` and add a passkey again.
 */
final class Version20261112190000 extends AbstractMigration
{
    private const string PHOTO_FK = 'fk_4f83267259e53fb9';

    public function getDescription(): string
    {
        return 'Existing IDs to UUIDv7 (old ones kept in renamed_id for redirects); passkeys must be added again';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE renamed_id (old_id UUID NOT NULL, new_id UUID NOT NULL, PRIMARY KEY (old_id))');

        $renamed = [
            'incident' => $this->renameAll('SELECT id, reported_at AS created FROM incident WHERE id::text NOT LIKE \'________-____-7%\''),
            'incident_photo' => $this->renameAll('SELECT p.id, i.reported_at AS created FROM incident_photo p JOIN incident i ON i.id = p.incident_id WHERE p.id::text NOT LIKE \'________-____-7%\''),
            'admin_user' => $this->renameAll('SELECT id, created_at AS created FROM admin_user WHERE id::text NOT LIKE \'________-____-7%\''),
        ];

        foreach (array_chunk(array_merge(...array_values($renamed)), 1000, true) as $chunk) {
            $this->addSql(
                'INSERT INTO renamed_id (old_id, new_id) VALUES '.implode(', ', array_fill(0, \count($chunk), '(?, ?)')),
                array_merge(...array_map(null, array_keys($chunk), array_values($chunk))),
            );
        }

        $this->addSql('DELETE FROM admin_passkey');
        $this->addSql(sprintf('ALTER TABLE incident_photo DROP CONSTRAINT %s', self::PHOTO_FK));
        foreach (array_keys($renamed) as $table) {
            $this->addSql(sprintf('UPDATE %s t SET id = r.new_id FROM renamed_id r WHERE t.id = r.old_id', $table));
        }
        $this->addSql('UPDATE incident_photo p SET incident_id = r.new_id FROM renamed_id r WHERE p.incident_id = r.old_id');
        $this->addSql(sprintf('ALTER TABLE incident_photo ADD CONSTRAINT %s FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE', self::PHOTO_FK));
    }

    public function down(Schema $schema): void
    {
        // Passkeys deleted by up() can't be restored.
        $this->addSql(sprintf('ALTER TABLE incident_photo DROP CONSTRAINT %s', self::PHOTO_FK));
        foreach (['incident', 'incident_photo', 'admin_user'] as $table) {
            $this->addSql(sprintf('UPDATE %s t SET id = r.old_id FROM renamed_id r WHERE t.id = r.new_id', $table));
        }
        $this->addSql('UPDATE incident_photo p SET incident_id = r.old_id FROM renamed_id r WHERE p.incident_id = r.new_id');
        $this->addSql(sprintf('ALTER TABLE incident_photo ADD CONSTRAINT %s FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE', self::PHOTO_FK));
        $this->addSql('DROP TABLE renamed_id');
    }

    /** @return array<string, string> old ID => new UUIDv7 */
    private function renameAll(string $select): array
    {
        $ids = [];
        foreach ($this->connection->fetchAllAssociative($select) as $row) {
            $ids[(string) $row['id']] = self::uuidV7(new \DateTimeImmutable((string) $row['created'], new \DateTimeZone('UTC')));
        }

        return $ids;
    }

    /** Same layout as AbstractId::generate(), at a given moment instead of now. */
    private static function uuidV7(\DateTimeImmutable $at): string
    {
        $bytes = substr(pack('J', (int) $at->format('Uv')), 2).random_bytes(10);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x70);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
