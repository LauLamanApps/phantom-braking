<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112153711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sun position and measured weather (Open-Meteo) per report';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident ADD sun_elevation DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD sun_azimuth DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_temperature DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_precipitation DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_snowfall DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_cloud_cover INT DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_weather_code INT DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_visibility INT DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_wind_speed DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_wind_gusts DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_humidity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD measured_solar_radiation DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident DROP sun_elevation');
        $this->addSql('ALTER TABLE incident DROP sun_azimuth');
        $this->addSql('ALTER TABLE incident DROP measured_checked_at');
        $this->addSql('ALTER TABLE incident DROP measured_temperature');
        $this->addSql('ALTER TABLE incident DROP measured_precipitation');
        $this->addSql('ALTER TABLE incident DROP measured_snowfall');
        $this->addSql('ALTER TABLE incident DROP measured_cloud_cover');
        $this->addSql('ALTER TABLE incident DROP measured_weather_code');
        $this->addSql('ALTER TABLE incident DROP measured_visibility');
        $this->addSql('ALTER TABLE incident DROP measured_wind_speed');
        $this->addSql('ALTER TABLE incident DROP measured_wind_gusts');
        $this->addSql('ALTER TABLE incident DROP measured_humidity');
        $this->addSql('ALTER TABLE incident DROP measured_solar_radiation');
    }
}
