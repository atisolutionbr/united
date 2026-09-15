<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260914180000 extends AbstractMigration
{
 public function getDescription(): string { return 'Preserve integration revisions and reviewed ERP write requests.'; }
 public function up(Schema $schema): void
 {
  $this->addSql('ALTER TABLE erp_connection ADD version INT NOT NULL DEFAULT 1');
  $this->addSql('CREATE TABLE united_connection_revision (id BIGSERIAL PRIMARY KEY, connection_id INT NOT NULL, snapshot JSONB NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
  $this->addSql('CREATE INDEX united_connection_revision_scope ON united_connection_revision(connection_id, id DESC)');
  $this->addSql('INSERT INTO united_connection_revision(connection_id,snapshot) SELECT id,to_jsonb(c) FROM erp_connection c');
  $this->addSql(<<<'SQL'
CREATE FUNCTION united_preserve_connection() RETURNS trigger AS $$
BEGIN
 IF OLD.connection_settings::jsonb IS DISTINCT FROM NEW.connection_settings::jsonb OR OLD.product_mapping::jsonb IS DISTINCT FROM NEW.product_mapping::jsonb OR OLD.connection_method IS DISTINCT FROM NEW.connection_method THEN
  INSERT INTO united_connection_revision(connection_id,snapshot) VALUES (OLD.id,to_jsonb(OLD));
 END IF;
 RETURN NEW;
END; $$ LANGUAGE plpgsql
SQL);
  $this->addSql('CREATE TRIGGER united_connection_history BEFORE UPDATE ON erp_connection FOR EACH ROW EXECUTE FUNCTION united_preserve_connection()');
  $this->addSql("CREATE TABLE united_erp_write (id BIGSERIAL PRIMARY KEY, connection_id INT NOT NULL REFERENCES erp_connection(id), actor_id INT NOT NULL, form VARCHAR(40) NOT NULL, config JSON NOT NULL, payload JSON NOT NULL, record_keys JSON NOT NULL, request_key VARCHAR(64) NOT NULL UNIQUE, status VARCHAR(20) NOT NULL DEFAULT 'review', receipt TEXT, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)");
  $this->addSql('CREATE INDEX united_erp_write_scope ON united_erp_write(connection_id,form,id DESC)');
 }
 public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Integration history and write receipts must be preserved.'); }
}
