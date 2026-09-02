<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 4: Data Warehouse, Analytic Models, Metabase integration, Analytics History for territorial intelligence layer.';
    }

    public function up(Schema $schema): void
    {
        // Analytic Models — define how staging data maps to warehouse
        $this->addSql('CREATE TABLE analytic_models (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            description TEXT DEFAULT NULL,
            source_table VARCHAR(255) NOT NULL,
            target_table VARCHAR(255) NOT NULL,
            dimensions JSON NOT NULL DEFAULT \'[]\',
            metrics JSON NOT NULL DEFAULT \'[]\',
            filters JSON NOT NULL DEFAULT \'[]\',
            refresh_strategy VARCHAR(30) NOT NULL DEFAULT \'manual\',
            last_refresh_status VARCHAR(30) DEFAULT NULL,
            last_refreshed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            row_count INT DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql("COMMENT ON COLUMN analytic_models.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN analytic_models.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN analytic_models.last_refreshed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_analytic_models_slug ON analytic_models (slug)');
        $this->addSql('CREATE INDEX idx_analytic_models_active ON analytic_models (is_active)');

        // Metabase Configurations — connection settings for Metabase instance
        $this->addSql('CREATE TABLE metabase_configs (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL DEFAULT \'Metabase Principal\',
            base_url VARCHAR(512) NOT NULL,
            database_name VARCHAR(255) DEFAULT NULL,
            username VARCHAR(255) DEFAULT NULL,
            password_encrypted VARCHAR(1024) DEFAULT NULL,
            secret_key VARCHAR(512) DEFAULT NULL,
            connection_status VARCHAR(30) NOT NULL DEFAULT \'untested\',
            last_tested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql("COMMENT ON COLUMN metabase_configs.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN metabase_configs.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN metabase_configs.last_tested_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN metabase_configs.last_sync_at IS '(DC2Type:datetime_immutable)'");

        // Metabase Dashboards — registered dashboards and questions from Metabase
        $this->addSql('CREATE TABLE metabase_dashboards (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            metabase_id INT DEFAULT NULL,
            embed_url VARCHAR(1024) DEFAULT NULL,
            public_uuid VARCHAR(255) DEFAULT NULL,
            type VARCHAR(30) NOT NULL DEFAULT \'dashboard\',
            dataset VARCHAR(255) DEFAULT NULL,
            origin VARCHAR(255) DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT \'active\',
            allow_embed BOOLEAN NOT NULL DEFAULT FALSE,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            metabase_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql("COMMENT ON COLUMN metabase_dashboards.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN metabase_dashboards.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN metabase_dashboards.metabase_updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_metabase_dashboards_active ON metabase_dashboards (is_active)');

        // Analytics History — audit trail for all warehouse and analytics operations
        $this->addSql('CREATE TABLE analytics_history (
            id SERIAL NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            detail TEXT DEFAULT NULL,
            status VARCHAR(30) NOT NULL,
            duration_ms INT DEFAULT NULL,
            rows_affected INT DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql("COMMENT ON COLUMN analytics_history.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_analytics_history_event_type ON analytics_history (event_type)');
        $this->addSql('CREATE INDEX idx_analytics_history_status ON analytics_history (status)');
        $this->addSql('CREATE INDEX idx_analytics_history_created_at ON analytics_history (created_at)');

        // Warehouse schema — fact and dimension tables for consolidated analytics
        $this->addSql('CREATE SCHEMA IF NOT EXISTS warehouse');

        $this->addSql('CREATE TABLE IF NOT EXISTS warehouse.dim_municipios (
            id SERIAL NOT NULL,
            codigo_ibge VARCHAR(10) NOT NULL,
            nome VARCHAR(255) NOT NULL,
            estado VARCHAR(2) NOT NULL,
            regiao VARCHAR(50) DEFAULT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_dim_municipios_ibge ON warehouse.dim_municipios (codigo_ibge)');

        $this->addSql('CREATE TABLE IF NOT EXISTS warehouse.dim_estados (
            id SERIAL NOT NULL,
            sigla VARCHAR(2) NOT NULL,
            nome VARCHAR(100) NOT NULL,
            regiao VARCHAR(50) NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_dim_estados_sigla ON warehouse.dim_estados (sigla)');

        $this->addSql('CREATE TABLE IF NOT EXISTS warehouse.dim_periodo (
            id SERIAL NOT NULL,
            data DATE NOT NULL,
            ano INT NOT NULL,
            trimestre INT NOT NULL,
            mes INT NOT NULL,
            semana INT NOT NULL,
            dia_semana INT NOT NULL,
            nome_mes VARCHAR(20) NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_dim_periodo_data ON warehouse.dim_periodo (data)');

        $this->addSql('CREATE TABLE IF NOT EXISTS warehouse.dw_turismo_agencias (
            id SERIAL NOT NULL,
            nome_fantasia VARCHAR(255) DEFAULT NULL,
            razao_social VARCHAR(255) DEFAULT NULL,
            cnpj VARCHAR(18) DEFAULT NULL,
            municipio VARCHAR(255) DEFAULT NULL,
            estado VARCHAR(2) DEFAULT NULL,
            regiao VARCHAR(50) DEFAULT NULL,
            situacao_cadastral VARCHAR(50) DEFAULT NULL,
            tipo_agencia VARCHAR(100) DEFAULT NULL,
            data_registro DATE DEFAULT NULL,
            source_table VARCHAR(255) NOT NULL,
            ingested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dw_agencias_estado ON warehouse.dw_turismo_agencias (estado)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dw_agencias_municipio ON warehouse.dw_turismo_agencias (municipio)');

        $this->addSql('CREATE TABLE IF NOT EXISTS warehouse.fact_agencias_turismo (
            id SERIAL NOT NULL,
            dim_periodo_id INT DEFAULT NULL,
            total_agencias INT NOT NULL DEFAULT 0,
            agencias_ativas INT NOT NULL DEFAULT 0,
            municipios_atendidos INT NOT NULL DEFAULT 0,
            estados_atendidos INT NOT NULL DEFAULT 0,
            crescimento_percentual DECIMAL(6,2) DEFAULT NULL,
            generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
            PRIMARY KEY(id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS warehouse.fact_agencias_turismo');
        $this->addSql('DROP TABLE IF EXISTS warehouse.dw_turismo_agencias');
        $this->addSql('DROP TABLE IF EXISTS warehouse.dim_periodo');
        $this->addSql('DROP TABLE IF EXISTS warehouse.dim_estados');
        $this->addSql('DROP TABLE IF EXISTS warehouse.dim_municipios');
        $this->addSql('DROP SCHEMA IF EXISTS warehouse CASCADE');
        $this->addSql('DROP TABLE analytics_history');
        $this->addSql('DROP TABLE metabase_dashboards');
        $this->addSql('DROP TABLE metabase_configs');
        $this->addSql('DROP TABLE analytic_models');
    }
}
