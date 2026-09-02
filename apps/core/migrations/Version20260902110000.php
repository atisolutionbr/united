<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores ERP connection modes and product field mappings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE erp_connection ADD connection_method VARCHAR(32) DEFAULT NULL");
        $this->addSql("ALTER TABLE erp_connection ADD connection_settings JSON NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE erp_connection ADD product_mapping JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE erp_connection ADD configured_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE erp_connection DROP connection_method');
        $this->addSql('ALTER TABLE erp_connection DROP connection_settings');
        $this->addSql('ALTER TABLE erp_connection DROP product_mapping');
        $this->addSql('ALTER TABLE erp_connection DROP configured_at');
    }
}
