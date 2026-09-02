<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove symfony_demo_ prefix from tables: post, comment, tag, app_user.';
    }

    public function up(Schema $schema): void
    {
        // Rename only if old table exists (idempotent)
        $this->addSql("DO \$\$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'symfony_demo_post') THEN
        ALTER TABLE symfony_demo_post RENAME TO post;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'symfony_demo_comment') THEN
        ALTER TABLE symfony_demo_comment RENAME TO comment;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'symfony_demo_tag') THEN
        ALTER TABLE symfony_demo_tag RENAME TO tag;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'symfony_demo_user') THEN
        ALTER TABLE symfony_demo_user RENAME TO app_user;
    END IF;
END \$\$");

        // Rename sequences so auto-increment keeps working
        $this->addSql("DO \$\$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.sequences WHERE sequence_name = 'symfony_demo_post_id_seq') THEN
        ALTER SEQUENCE symfony_demo_post_id_seq RENAME TO post_id_seq;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.sequences WHERE sequence_name = 'symfony_demo_comment_id_seq') THEN
        ALTER SEQUENCE symfony_demo_comment_id_seq RENAME TO comment_id_seq;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.sequences WHERE sequence_name = 'symfony_demo_tag_id_seq') THEN
        ALTER SEQUENCE symfony_demo_tag_id_seq RENAME TO tag_id_seq;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.sequences WHERE sequence_name = 'symfony_demo_user_id_seq') THEN
        ALTER SEQUENCE symfony_demo_user_id_seq RENAME TO app_user_id_seq;
    END IF;
END \$\$");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DO \$\$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'post') THEN
        ALTER TABLE post RENAME TO symfony_demo_post;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'comment') THEN
        ALTER TABLE comment RENAME TO symfony_demo_comment;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tag') THEN
        ALTER TABLE tag RENAME TO symfony_demo_tag;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'app_user') THEN
        ALTER TABLE app_user RENAME TO symfony_demo_user;
    END IF;
END \$\$");

        $this->addSql("DO \$\$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.sequences WHERE sequence_name = 'post_id_seq') THEN
        ALTER SEQUENCE post_id_seq RENAME TO symfony_demo_post_id_seq;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.sequences WHERE sequence_name = 'comment_id_seq') THEN
        ALTER SEQUENCE comment_id_seq RENAME TO symfony_demo_comment_id_seq;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.sequences WHERE sequence_name = 'tag_id_seq') THEN
        ALTER SEQUENCE tag_id_seq RENAME TO symfony_demo_tag_id_seq;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.sequences WHERE sequence_name = 'app_user_id_seq') THEN
        ALTER SEQUENCE app_user_id_seq RENAME TO symfony_demo_user_id_seq;
    END IF;
END \$\$");
    }
}
