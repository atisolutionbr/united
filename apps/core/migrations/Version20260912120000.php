<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912120000 extends AbstractMigration
{
    public function getDescription(): string { return 'United: vínculos de processos, documentos e auditoria de integrações.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE united_process_binding (id SERIAL PRIMARY KEY, connection_id INT NOT NULL REFERENCES erp_connection(id) ON DELETE CASCADE, process VARCHAR(40) NOT NULL, label VARCHAR(120) NOT NULL, config JSON NOT NULL, UNIQUE(connection_id, process, label))');
        $this->addSql("CREATE TABLE united_document (id SERIAL PRIMARY KEY, connection_id INT NOT NULL REFERENCES erp_connection(id), kind VARCHAR(40) NOT NULL, actor_id INT NOT NULL, payload JSON NOT NULL, stage VARCHAR(40) NOT NULL DEFAULT 'need', delivery_status VARCHAR(30) NOT NULL DEFAULT 'draft', receipt TEXT, request_key VARCHAR(64) NOT NULL UNIQUE, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->addSql('CREATE TABLE united_process_audit (id SERIAL PRIMARY KEY, connection_id INT NOT NULL REFERENCES erp_connection(id), document_id INT REFERENCES united_document(id), actor_id INT NOT NULL, action VARCHAR(80) NOT NULL, details JSON NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->addSql('CREATE INDEX united_document_scope ON united_document(connection_id, kind, id)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE united_process_audit');
        $this->addSql('DROP TABLE united_document');
        $this->addSql('DROP TABLE united_process_binding');
    }
}
