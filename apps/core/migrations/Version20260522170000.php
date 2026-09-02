<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 3: Add dataset_column_mappings and dataset_quality_reports for transformation, normalization and staging pipeline.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dataset_column_mappings (
            id SERIAL NOT NULL,
            provider_package_id INT NOT NULL,
            original_column VARCHAR(255) NOT NULL,
            normalized_column VARCHAR(255) NOT NULL,
            target_data_type VARCHAR(30) NOT NULL DEFAULT \'string\',
            normalization_rule VARCHAR(50) DEFAULT NULL,
            required_field BOOLEAN DEFAULT FALSE NOT NULL,
            is_active BOOLEAN DEFAULT TRUE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_column_mapping ON dataset_column_mappings (provider_package_id, original_column)');
        $this->addSql('CREATE INDEX IDX_COLMAP_PACKAGE ON dataset_column_mappings (provider_package_id)');
        $this->addSql("COMMENT ON COLUMN dataset_column_mappings.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN dataset_column_mappings.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE dataset_column_mappings ADD CONSTRAINT FK_COLMAP_PACKAGE FOREIGN KEY (provider_package_id) REFERENCES provider_packages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE dataset_quality_reports (
            id SERIAL NOT NULL,
            raw_file_id INT NOT NULL,
            total_rows INT DEFAULT 0 NOT NULL,
            valid_rows INT DEFAULT 0 NOT NULL,
            invalid_rows INT DEFAULT 0 NOT NULL,
            duplicated_rows INT DEFAULT 0 NOT NULL,
            null_fields INT DEFAULT 0 NOT NULL,
            validation_errors JSON NOT NULL,
            generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_quality_reports_raw_file ON dataset_quality_reports (raw_file_id)');
        $this->addSql('CREATE INDEX idx_quality_reports_generated_at ON dataset_quality_reports (generated_at)');
        $this->addSql("COMMENT ON COLUMN dataset_quality_reports.generated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN dataset_quality_reports.validation_errors IS '(DC2Type:array)'");
        $this->addSql('ALTER TABLE dataset_quality_reports ADD CONSTRAINT FK_QUALITY_RAW_FILE FOREIGN KEY (raw_file_id) REFERENCES raw_files (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("ALTER TABLE raw_files ADD COLUMN staging_path VARCHAR(1024) DEFAULT NULL");
        $this->addSql("ALTER TABLE raw_files ADD COLUMN transformation_status VARCHAR(30) DEFAULT 'pending' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dataset_quality_reports DROP CONSTRAINT FK_QUALITY_RAW_FILE');
        $this->addSql('ALTER TABLE dataset_column_mappings DROP CONSTRAINT FK_COLMAP_PACKAGE');
        $this->addSql('DROP TABLE dataset_quality_reports');
        $this->addSql('DROP TABLE dataset_column_mappings');
        $this->addSql('ALTER TABLE raw_files DROP COLUMN staging_path');
        $this->addSql('ALTER TABLE raw_files DROP COLUMN transformation_status');
    }
}
