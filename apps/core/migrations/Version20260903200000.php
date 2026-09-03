<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260903200000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds holding and platform versus ERP access origin.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE company ADD holding_name VARCHAR(160) DEFAULT NULL'); $this->addSql("ALTER TABLE company_membership ADD access_origin VARCHAR(24) NOT NULL DEFAULT 'platform'"); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE company DROP holding_name'); $this->addSql('ALTER TABLE company_membership DROP access_origin'); }
}
