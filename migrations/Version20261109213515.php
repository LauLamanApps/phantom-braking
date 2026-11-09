<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The complete initial schema (PostgreSQL). Earlier development migrations were folded into this one before going live.
 */
final class Version20261109213515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: incidents, photos, admins with passkeys, Messenger queues.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        // Reports. The plate itself is never stored, only plate_fingerprint (HMAC).
        $this->addSql('CREATE TABLE incident (id UUID NOT NULL, occurred_month VARCHAR(7) NOT NULL, occurred_hour INT NOT NULL, reported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, country VARCHAR(2) NOT NULL, plate_fingerprint VARCHAR(64) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, severity VARCHAR(255) NOT NULL, speed_before INT NOT NULL, speed_after INT DEFAULT NULL, trigger VARCHAR(255) NOT NULL, assist_system VARCHAR(255) NOT NULL, warning_shown BOOLEAN DEFAULT NULL, weather VARCHAR(255) NOT NULL, light VARCHAR(255) NOT NULL, consequence VARCHAR(255) NOT NULL, recurrence VARCHAR(255) NOT NULL, dealer_response VARCHAR(255) NOT NULL, reported_to_authority BOOLEAN NOT NULL, windscreen_replaced_recently BOOLEAN DEFAULT NULL, odometer_km INT DEFAULT NULL, software_version VARCHAR(64) DEFAULT NULL, description TEXT DEFAULT NULL, footage_status VARCHAR(255) DEFAULT NULL, footage_path VARCHAR(255) DEFAULT NULL, footage_mime_type VARCHAR(100) DEFAULT NULL, footage_size INT DEFAULT NULL, footage_publication_consent BOOLEAN DEFAULT NULL, vehicle_make VARCHAR(64) NOT NULL, vehicle_model VARCHAR(128) NOT NULL, vehicle_first_admission DATE DEFAULT NULL, vehicle_vehicle_kind VARCHAR(64) DEFAULT NULL, vehicle_european_category VARCHAR(8) DEFAULT NULL, vehicle_fuel VARCHAR(128) DEFAULT NULL, vehicle_hybrid_class VARCHAR(32) DEFAULT NULL, vehicle_type_approval_number VARCHAR(64) DEFAULT NULL, vehicle_variant VARCHAR(32) DEFAULT NULL, vehicle_execution VARCHAR(64) DEFAULT NULL, vehicle_open_recall BOOLEAN DEFAULT NULL, location_road_number VARCHAR(16) DEFAULT NULL, location_road_type VARCHAR(255) NOT NULL, location_situation VARCHAR(255) NOT NULL, location_latitude DOUBLE PRECISION DEFAULT NULL, location_longitude DOUBLE PRECISION DEFAULT NULL, location_direction VARCHAR(120) DEFAULT NULL, location_speed_limit INT DEFAULT NULL, location_country VARCHAR(2) NOT NULL, media_deletion_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, media_deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_incident_plate ON incident (plate_fingerprint)');
        $this->addSql('CREATE INDEX idx_incident_make ON incident (vehicle_make)');
        $this->addSql('CREATE INDEX idx_incident_occurred ON incident (occurred_at)');

        // Aftermath photos; published_path is the plate-masked public copy.
        $this->addSql('CREATE TABLE incident_photo (status VARCHAR(255) NOT NULL, id UUID NOT NULL, "position" INT NOT NULL, path VARCHAR(255) NOT NULL, mime_type VARCHAR(50) NOT NULL, size INT NOT NULL, width INT NOT NULL, height INT NOT NULL, publication_consent BOOLEAN NOT NULL, incident_id UUID NOT NULL, published_path VARCHAR(255) DEFAULT NULL, masks JSON NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_4f83267259e53fb9 ON incident_photo (incident_id)');
        $this->addSql('ALTER TABLE incident_photo ADD CONSTRAINT fk_4f83267259e53fb9 FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Moderators: a name only, they log in with a passkey.
        $this->addSql('CREATE TABLE admin_user (name VARCHAR(60) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_admin_user_name ON admin_user (name)');

        // Passkeys (WebAuthn public key and counter) per admin.
        $this->addSql('CREATE TABLE admin_passkey (public_key_credential_id TEXT NOT NULL, type VARCHAR(255) NOT NULL, transports JSON NOT NULL, attestation_type VARCHAR(255) NOT NULL, trust_path JSON NOT NULL, aaguid TEXT NOT NULL, credential_public_key TEXT NOT NULL, user_handle VARCHAR(255) NOT NULL, counter INT NOT NULL, other_ui JSON DEFAULT NULL, backup_eligible BOOLEAN DEFAULT NULL, backup_status BOOLEAN DEFAULT NULL, uv_initialized BOOLEAN DEFAULT NULL, id UUID NOT NULL, admin_user_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_admin_passkey_admin ON admin_passkey (admin_user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_admin_passkey_credential ON admin_passkey (public_key_credential_id)');

        // Messenger: async transport.
        $this->addSql('CREATE TABLE __message_bus_queue (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ef712d8afb7336f0e3bd61ce16ba31dbbf396750 ON __message_bus_queue (queue_name, available_at, delivered_at, id)');

        // Messenger: failed messages.
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_75ea56e0fb7336f0e3bd61ce16ba31dbbf396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE __message_bus_queue');
        $this->addSql('DROP TABLE admin_passkey');
        $this->addSql('DROP TABLE admin_user');
        $this->addSql('DROP TABLE incident_photo');
        $this->addSql('DROP TABLE incident');
    }
}
