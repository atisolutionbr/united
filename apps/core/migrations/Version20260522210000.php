<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 5: AI Layer — ai_models, ai_prompts, ai_contexts, ai_agents, ai_interactions, ai_embeddings for hybrid local/external AI with governance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE ai_models (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            provider VARCHAR(50) NOT NULL DEFAULT 'local_ollama',
            model_name VARCHAR(100) NOT NULL,
            endpoint VARCHAR(512) DEFAULT NULL,
            api_key_encrypted VARCHAR(1024) DEFAULT NULL,
            temperature DECIMAL(3,2) DEFAULT NULL,
            max_tokens INT DEFAULT NULL,
            context_window INT DEFAULT NULL,
            supports_embeddings BOOLEAN NOT NULL DEFAULT FALSE,
            is_default BOOLEAN NOT NULL DEFAULT FALSE,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN ai_models.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN ai_models.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_ai_models_slug ON ai_models (slug)');
        $this->addSql('CREATE INDEX idx_ai_models_provider ON ai_models (provider)');
        $this->addSql('CREATE INDEX idx_ai_models_active ON ai_models (is_active)');

        $this->addSql("CREATE TABLE ai_prompts (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            purpose VARCHAR(80) NOT NULL DEFAULT 'general_assistant',
            prompt_template TEXT NOT NULL,
            context_type VARCHAR(100) DEFAULT NULL,
            provider VARCHAR(50) DEFAULT NULL,
            version INT NOT NULL DEFAULT 1,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN ai_prompts.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN ai_prompts.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_ai_prompts_slug ON ai_prompts (slug)');
        $this->addSql('CREATE INDEX idx_ai_prompts_purpose ON ai_prompts (purpose)');

        $this->addSql("CREATE TABLE ai_contexts (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            description TEXT DEFAULT NULL,
            sources JSON NOT NULL DEFAULT '[]',
            warehouse_tables JSON NOT NULL DEFAULT '[]',
            allowed_for_external BOOLEAN NOT NULL DEFAULT FALSE,
            max_rows_context INT DEFAULT 100,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN ai_contexts.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN ai_contexts.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_ai_contexts_slug ON ai_contexts (slug)');

        $this->addSql("CREATE TABLE ai_agents (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            description TEXT DEFAULT NULL,
            agent_type VARCHAR(50) NOT NULL DEFAULT 'dados_publicos',
            default_model_id INT DEFAULT NULL,
            default_context_id INT DEFAULT NULL,
            prompt_id INT DEFAULT NULL,
            tools JSON NOT NULL DEFAULT '[]',
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN ai_agents.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN ai_agents.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_ai_agents_slug ON ai_agents (slug)');
        $this->addSql('ALTER TABLE ai_agents ADD CONSTRAINT fk_ai_agents_model FOREIGN KEY (default_model_id) REFERENCES ai_models (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_agents ADD CONSTRAINT fk_ai_agents_context FOREIGN KEY (default_context_id) REFERENCES ai_contexts (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_agents ADD CONSTRAINT fk_ai_agents_prompt FOREIGN KEY (prompt_id) REFERENCES ai_prompts (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE ai_interactions (
            id SERIAL NOT NULL,
            user_identifier VARCHAR(255) DEFAULT NULL,
            provider VARCHAR(50) NOT NULL,
            model_name VARCHAR(100) NOT NULL,
            agent_slug VARCHAR(191) DEFAULT NULL,
            prompt TEXT NOT NULL,
            response TEXT DEFAULT NULL,
            context_used JSON DEFAULT NULL,
            tokens_input INT DEFAULT NULL,
            tokens_output INT DEFAULT NULL,
            estimated_cost_usd DECIMAL(10,6) DEFAULT NULL,
            duration_ms INT DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'running',
            error_message TEXT DEFAULT NULL,
            is_external_provider BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN ai_interactions.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_ai_interactions_provider ON ai_interactions (provider)');
        $this->addSql('CREATE INDEX idx_ai_interactions_status ON ai_interactions (status)');
        $this->addSql('CREATE INDEX idx_ai_interactions_created_at ON ai_interactions (created_at)');

        $this->addSql("CREATE TABLE ai_embeddings (
            id SERIAL NOT NULL,
            source_type VARCHAR(50) NOT NULL,
            source_id VARCHAR(255) NOT NULL,
            chunk_text TEXT NOT NULL,
            embedding_provider VARCHAR(50) NOT NULL,
            embedding_model VARCHAR(100) NOT NULL,
            vector_id VARCHAR(255) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN ai_embeddings.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_ai_embeddings_source ON ai_embeddings (source_type, source_id)');

        // Seed default prompts
        $this->addSql("INSERT INTO ai_prompts (name, slug, purpose, prompt_template, context_type, version, is_active, created_at) VALUES
            ('Análise de Indicador', 'analise-indicador', 'indicator_analysis',
             'Você é um especialista em inteligência territorial e dados públicos da Plataforma360. Analise o indicador a seguir e forneça: 1) Interpretação do valor, 2) Contexto territorial, 3) Tendência, 4) Recomendações. Indicador: {{indicator_name}} = {{indicator_value}} {{indicator_unit}}. Contexto: {{context}}',
             'indicators', 1, true, NOW()),
            ('Geração de Relatório', 'geracao-relatorio', 'report_generation',
             'Você é um assistente de inteligência governamental da Plataforma360. Gere um relatório executivo com base nos dados fornecidos. Inclua: Resumo executivo, Principais achados, Riscos identificados, Oportunidades, Recomendações estratégicas. Dados: {{data}}. Período de referência: {{period}}.',
             'warehouse', 1, true, NOW()),
            ('Explicação de Dataset', 'explicacao-dataset', 'dataset_explanation',
             'Você é um especialista em dados públicos da Plataforma360. Explique o dataset a seguir de forma clara para um gestor público. Dataset: {{dataset_name}}. Colunas: {{columns}}. Amostra de dados: {{sample}}.',
             'catalog', 1, true, NOW()),
            ('Resumo Executivo', 'resumo-executivo', 'executive_summary',
             'Você é um assistente estratégico da Plataforma360. Gere um resumo executivo em linguagem acessível para gestores públicos. Foque em: situação atual, destaques, riscos e próximos passos. Dados: {{data}}',
             'indicators', 1, true, NOW()),
            ('Comparação Territorial', 'comparacao-territorial', 'territorial_comparison',
             'Você é um especialista em inteligência territorial. Compare os dados entre regiões/municípios fornecidos, destacando: líderes, defasagens, oportunidades e padrões relevantes. Dados: {{data}}',
             'warehouse', 1, true, NOW()),
            ('Diagnóstico de Qualidade', 'diagnostico-qualidade', 'data_quality_diagnosis',
             'Você é um especialista em qualidade de dados. Analise o relatório de qualidade a seguir e forneça: problemas críticos, causas prováveis e recomendações de correção. Relatório: {{quality_report}}',
             'quality', 1, true, NOW()),
            ('Assistente Geral', 'assistente-geral', 'general_assistant',
             'Você é o Assistente de Inteligência Territorial da Plataforma360, especializado em dados públicos, turismo, indicadores governamentais e gestão territorial. Responda sempre com base nos dados reais da plataforma. Contexto disponível: {{context}}. Pergunta: {{question}}',
             null, 1, true, NOW()),
            ('Natural Language to SQL', 'nl-to-sql', 'nl_to_sql',
             'Você é um especialista em SQL para análise de dados públicos. Converta a pergunta a seguir em uma consulta SQL segura (somente SELECT) para o schema warehouse do PostgreSQL. Tabelas disponíveis: {{tables}}. Schema resumido: {{schema}}. Pergunta: {{question}}. REGRAS: apenas SELECT, limite 100 linhas, sem DROP/DELETE/UPDATE/INSERT.',
             'warehouse', 1, true, NOW())
        ");

        // Seed default agents
        $this->addSql("INSERT INTO ai_agents (name, slug, description, agent_type, tools, is_active, created_at) VALUES
            ('Agente Turismo', 'agente-turismo', 'Especialista em dados turísticos. Consulta datasets de agências, regiões e municípios do setor de turismo.', 'turismo', '[\"buscar_indicadores\",\"consultar_warehouse\",\"listar_datasets\"]', true, NOW()),
            ('Agente Dados Públicos', 'agente-dados-publicos', 'Especialista em dados abertos. Consulta catálogo, qualidade, linhagem e warehouse da plataforma.', 'dados_publicos', '[\"listar_datasets\",\"obter_qualidade_dataset\",\"obter_linhagem_dataset\",\"consultar_warehouse\"]', true, NOW()),
            ('Agente Executivo', 'agente-executivo', 'Gera resumos e relatórios para gestores públicos com foco em indicadores estratégicos.', 'executivo', '[\"buscar_indicadores\",\"gerar_relatorio\"]', true, NOW()),
            ('Agente Técnico', 'agente-tecnico', 'Explica pipelines, erros de ingestão, logs de qualidade e metadados técnicos dos datasets.', 'tecnico', '[\"obter_qualidade_dataset\",\"obter_linhagem_dataset\",\"listar_datasets\"]', true, NOW())
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_agents DROP CONSTRAINT IF EXISTS fk_ai_agents_model');
        $this->addSql('ALTER TABLE ai_agents DROP CONSTRAINT IF EXISTS fk_ai_agents_context');
        $this->addSql('ALTER TABLE ai_agents DROP CONSTRAINT IF EXISTS fk_ai_agents_prompt');
        $this->addSql('DROP TABLE IF EXISTS ai_embeddings');
        $this->addSql('DROP TABLE IF EXISTS ai_interactions');
        $this->addSql('DROP TABLE IF EXISTS ai_agents');
        $this->addSql('DROP TABLE IF EXISTS ai_contexts');
        $this->addSql('DROP TABLE IF EXISTS ai_prompts');
        $this->addSql('DROP TABLE IF EXISTS ai_models');
    }
}
