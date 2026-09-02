<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add raw_files and dataset_schemas tables for operational data pipeline.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE raw_files (id SERIAL NOT NULL, dataset_resource_id INT NOT NULL, provider_package_id INT NOT NULL, data_provider_id INT NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, local_path VARCHAR(1024) NOT NULL, mime_type VARCHAR(255) DEFAULT NULL, extension VARCHAR(20) DEFAULT NULL, file_size INT DEFAULT NULL, file_hash VARCHAR(64) DEFAULT NULL, download_status VARCHAR(30) NOT NULL, already_processed BOOLEAN DEFAULT FALSE NOT NULL, downloaded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_raw_files_hash ON raw_files (file_hash)');
        $this->addSql('CREATE INDEX idx_raw_files_status ON raw_files (download_status)');
        $this->addSql('CREATE INDEX idx_raw_files_downloaded_at ON raw_files (downloaded_at)');
        $this->addSql('CREATE INDEX IDX_2DCA2D3D31DF7DF7 ON raw_files (dataset_resource_id)');
        $this->addSql('CREATE INDEX IDX_2DCA2D3D5698BFC5 ON raw_files (provider_package_id)');
        $this->addSql('CREATE INDEX IDX_2DCA2D3DB61F97D7 ON raw_files (data_provider_id)');
        $this->addSql("COMMENT ON COLUMN raw_files.downloaded_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN raw_files.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN raw_files.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE raw_files ADD CONSTRAINT FK_2DCA2D3D31DF7DF7 FOREIGN KEY (dataset_resource_id) REFERENCES dataset_resources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE raw_files ADD CONSTRAINT FK_2DCA2D3D5698BFC5 FOREIGN KEY (provider_package_id) REFERENCES provider_packages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE raw_files ADD CONSTRAINT FK_2DCA2D3DB61F97D7 FOREIGN KEY (data_provider_id) REFERENCES data_providers (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE dataset_schemas (id SERIAL NOT NULL, raw_file_id INT NOT NULL, column_name VARCHAR(255) NOT NULL, detected_type VARCHAR(50) NOT NULL, nullable BOOLEAN DEFAULT TRUE NOT NULL, sample_value TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3A43A16B8B568A13 ON dataset_schemas (raw_file_id)');
        $this->addSql("COMMENT ON COLUMN dataset_schemas.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE dataset_schemas ADD CONSTRAINT FK_3A43A16B8B568A13 FOREIGN KEY (raw_file_id) REFERENCES raw_files (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dataset_schemas DROP CONSTRAINT FK_3A43A16B8B568A13');
        $this->addSql('ALTER TABLE raw_files DROP CONSTRAINT FK_2DCA2D3D31DF7DF7');
        $this->addSql('ALTER TABLE raw_files DROP CONSTRAINT FK_2DCA2D3D5698BFC5');
        $this->addSql('ALTER TABLE raw_files DROP CONSTRAINT FK_2DCA2D3DB61F97D7');
        $this->addSql('DROP TABLE dataset_schemas');
        $this->addSql('DROP TABLE raw_files');
    }
}