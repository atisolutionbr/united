<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 6: Operations & Governance — pipelines, pipeline_executions, alerts, system_metrics, data_governance_records, audit_logs, tenants, cost_records.';
    }

    public function up(Schema $schema): void
    {
        // ── Operations ────────────────────────────────────────────────────────

        $this->addSql("CREATE TABLE pipelines (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            kestra_namespace VARCHAR(255) DEFAULT NULL,
            kestra_flow_id VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'ingestion',
            trigger_type VARCHAR(50) NOT NULL DEFAULT 'manual',
            cron_expression VARCHAR(100) DEFAULT NULL,
            kestra_yaml TEXT DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            last_execution_id VARCHAR(255) DEFAULT NULL,
            last_execution_status VARCHAR(50) DEFAULT NULL,
            last_executed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            next_execution_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            avg_duration_ms INT DEFAULT NULL,
            failure_count INT NOT NULL DEFAULT 0,
            success_count INT NOT NULL DEFAULT 0,
            dataset_slug VARCHAR(191) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN pipelines.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pipelines.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pipelines.last_executed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pipelines.next_execution_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_pipelines_slug ON pipelines (slug)');
        $this->addSql('CREATE INDEX idx_pipelines_type ON pipelines (type)');
        $this->addSql('CREATE INDEX idx_pipelines_status ON pipelines (last_execution_status)');

        $this->addSql("CREATE TABLE pipeline_executions (
            id SERIAL NOT NULL,
            pipeline_id INT DEFAULT NULL,
            kestra_execution_id VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'CREATED',
            started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            duration_ms INT DEFAULT NULL,
            triggered_by VARCHAR(255) DEFAULT NULL,
            trigger_type VARCHAR(50) NOT NULL DEFAULT 'manual',
            logs TEXT DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            retry_count INT NOT NULL DEFAULT 0,
            inputs JSON DEFAULT NULL,
            outputs JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN pipeline_executions.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pipeline_executions.started_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pipeline_executions.finished_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_pe_status ON pipeline_executions (status)');
        $this->addSql('CREATE INDEX idx_pe_created ON pipeline_executions (created_at)');
        $this->addSql('ALTER TABLE pipeline_executions ADD CONSTRAINT fk_pe_pipeline FOREIGN KEY (pipeline_id) REFERENCES pipelines (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE alerts (
            id SERIAL NOT NULL,
            type VARCHAR(80) NOT NULL DEFAULT 'general',
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            source VARCHAR(100) DEFAULT NULL,
            source_id VARCHAR(255) DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            acknowledged_by VARCHAR(255) DEFAULT NULL,
            acknowledged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN alerts.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN alerts.acknowledged_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN alerts.resolved_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_alerts_level ON alerts (level)');
        $this->addSql('CREATE INDEX idx_alerts_status ON alerts (status)');

        $this->addSql("CREATE TABLE system_metrics (
            id SERIAL NOT NULL,
            metric_name VARCHAR(100) NOT NULL,
            metric_type VARCHAR(50) NOT NULL DEFAULT 'gauge',
            value DECIMAL(18,4) NOT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            labels JSON DEFAULT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'symfony',
            recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN system_metrics.recorded_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_sm_source_metric ON system_metrics (source, metric_name)');
        $this->addSql('CREATE INDEX idx_sm_recorded ON system_metrics (recorded_at)');

        // ── Governance ────────────────────────────────────────────────────────

        $this->addSql("CREATE TABLE data_governance_records (
            id SERIAL NOT NULL,
            dataset_id INT DEFAULT NULL,
            dataset_name VARCHAR(255) NOT NULL,
            dataset_slug VARCHAR(191) DEFAULT NULL,
            owner VARCHAR(255) DEFAULT NULL,
            steward VARCHAR(255) DEFAULT NULL,
            classification VARCHAR(30) NOT NULL DEFAULT 'public',
            retention_days INT DEFAULT NULL,
            sensitivity_level VARCHAR(20) NOT NULL DEFAULT 'none',
            lgpd_applicable BOOLEAN NOT NULL DEFAULT FALSE,
            lgpd_basis VARCHAR(30) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            tags JSON DEFAULT NULL,
            tenant_id INT DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN data_governance_records.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN data_governance_records.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_dgr_slug ON data_governance_records (dataset_slug)');
        $this->addSql('CREATE INDEX idx_dgr_classification ON data_governance_records (classification)');

        $this->addSql("CREATE TABLE audit_logs (
            id SERIAL NOT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(100) DEFAULT NULL,
            entity_id VARCHAR(255) DEFAULT NULL,
            user_identifier VARCHAR(255) DEFAULT NULL,
            description TEXT NOT NULL,
            before_value JSON DEFAULT NULL,
            after_value JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            tenant_id INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN audit_logs.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_audit_action ON audit_logs (action)');
        $this->addSql('CREATE INDEX idx_audit_entity ON audit_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_audit_user ON audit_logs (user_identifier)');
        $this->addSql('CREATE INDEX idx_audit_created ON audit_logs (created_at)');

        $this->addSql("CREATE TABLE tenants (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'prefeitura',
            municipio_id VARCHAR(10) DEFAULT NULL,
            estado VARCHAR(2) DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            settings JSON DEFAULT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN tenants.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN tenants.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_tenants_slug ON tenants (slug)');

        $this->addSql("CREATE TABLE cost_records (
            id SERIAL NOT NULL,
            service VARCHAR(50) NOT NULL,
            period_date DATE NOT NULL,
            quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
            unit VARCHAR(30) DEFAULT NULL,
            unit_cost_usd DECIMAL(10,8) NOT NULL DEFAULT 0,
            total_cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0,
            description TEXT DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            tenant_id INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN cost_records.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN cost_records.period_date IS '(DC2Type:date_immutable)'");
        $this->addSql('CREATE INDEX idx_cost_service ON cost_records (service)');
        $this->addSql('CREATE INDEX idx_cost_period ON cost_records (period_date)');

        // ── Default seeds ─────────────────────────────────────────────────────

        $this->addSql("INSERT INTO tenants (name, slug, type, estado, is_active, created_at) VALUES
            ('Plataforma360 · Ambiente Padrão', 'default', 'ambiente', 'PE', true, NOW())
        ");

        $this->addSql("INSERT INTO pipelines (name, slug, kestra_namespace, kestra_flow_id, type, trigger_type, description, is_active, created_at) VALUES
            ('Ingestão CKAN · Turismo MTur', 'ingestion-ckan-turismo', 'plataforma360', 'ingestao-ckan-turismo', 'ingestion', 'cron', 'Pipeline de ingestão diária dos datasets de turismo do CKAN do Ministério do Turismo.', true, NOW()),
            ('Transformação Staging · Turismo', 'transform-staging-turismo', 'plataforma360', 'transform-staging-turismo', 'transformation', 'event', 'Pipeline de normalização e enriquecimento dos dados de agências de turismo para a zona STAGING.', true, NOW()),
            ('Carga Warehouse · Turismo', 'warehouse-turismo', 'plataforma360', 'warehouse-carga-turismo', 'warehouse', 'cron', 'Pipeline de carga dos dados normalizados para o Data Warehouse (schema warehouse.*).', true, NOW()),
            ('Geração de Embeddings', 'embeddings-gen', 'plataforma360', 'embeddings-generation', 'embeddings', 'event', 'Gera embeddings vetoriais dos datasets ativos e indexa no Qdrant para RAG.', false, NOW())
        ");

        $this->addSql("INSERT INTO alerts (type, level, title, message, source, status, created_at) VALUES
            ('general', 'info', 'Fase 6 Implementada', 'Plataforma360 Phase 6: Observabilidade, Orquestração e Governança Enterprise ativas com sucesso.', 'system', 'active', NOW())
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pipeline_executions DROP CONSTRAINT IF EXISTS fk_pe_pipeline');
        $this->addSql('DROP TABLE IF EXISTS cost_records');
        $this->addSql('DROP TABLE IF EXISTS tenants');
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
        $this->addSql('DROP TABLE IF EXISTS data_governance_records');
        $this->addSql('DROP TABLE IF EXISTS system_metrics');
        $this->addSql('DROP TABLE IF EXISTS alerts');
        $this->addSql('DROP TABLE IF EXISTS pipeline_executions');
        $this->addSql('DROP TABLE IF EXISTS pipelines');
    }
}
