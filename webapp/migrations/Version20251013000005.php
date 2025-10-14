<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251013000005 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add missing fields to problem_attachment and problem_attachment_content (url, meta_data, rubricid, type, meta_data)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', "Migration can only be executed safely on 'mysql'.");

        $sm = $this->connection->getSchemaManager();

        // Add url, meta_data, rubricid to problem_attachment if they don't exist
        if ($sm->tablesExist(['problem_attachment'])) {
            $pa = $sm->listTableDetails('problem_attachment');
            // url is stored in problem_attachment_content (multiple content rows can exist per attachment)
            // ensure description column exists (entity expects it)
            // ensure url column exists on problem_attachment for installs expecting it
            if (!$pa->hasColumn('url')) {
                $this->addSql("ALTER TABLE problem_attachment ADD COLUMN url VARCHAR(255) DEFAULT NULL COMMENT 'Attachment URL'");
            }
            if (!$pa->hasColumn('description')) {
                $this->addSql("ALTER TABLE problem_attachment ADD COLUMN description TEXT DEFAULT NULL COMMENT 'Attachment description'");
            }
            if (!$pa->hasColumn('meta_data')) {
                // JSON requires MySQL 5.7+
                $this->addSql("ALTER TABLE problem_attachment ADD COLUMN meta_data JSON DEFAULT NULL COMMENT 'Flexible meta data for attachment'");
            }
            if (!$pa->hasColumn('rubricid')) {
                $this->addSql("ALTER TABLE problem_attachment ADD COLUMN rubricid INT UNSIGNED DEFAULT NULL COMMENT 'Associated rubric for this attachment'");
            }

            // add indexes if missing
            if (!$pa->hasIndex('rubricid_idx')) {
                $this->addSql('CREATE INDEX rubricid_idx ON problem_attachment (rubricid)');
            }
            if (!$pa->hasIndex('problem_id_idx')) {
                $this->addSql('CREATE INDEX problem_id_idx ON problem_attachment (probid)');
            }
        }

        // Add url, meta_data and type to problem_attachment_content if they don't exist
        if ($sm->tablesExist(['problem_attachment_content'])) {
            $pac = $sm->listTableDetails('problem_attachment_content');
            if (!$pac->hasColumn('url')) {
                $this->addSql("ALTER TABLE problem_attachment_content ADD COLUMN url VARCHAR(255) DEFAULT NULL COMMENT 'Attachment URL'");
            }
            if (!$pac->hasColumn('meta_data')) {
                $this->addSql("ALTER TABLE problem_attachment_content ADD COLUMN meta_data JSON DEFAULT NULL COMMENT 'Flexible meta data for attachment content'");
            }
            if (!$pac->hasColumn('type')) {
                $this->addSql("ALTER TABLE problem_attachment_content ADD COLUMN type VARCHAR(32) DEFAULT NULL COMMENT 'Attachment content type (pre, post, etc)'");
            }
            // do not add redundant index on primary key
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', "Migration can only be executed safely on 'mysql'.");

        $sm = $this->connection->getSchemaManager();
        if ($sm->tablesExist(['problem_attachment'])) {
            $pa = $sm->listTableDetails('problem_attachment');
            if ($pa->hasColumn('meta_data')) {
                $this->addSql('ALTER TABLE problem_attachment DROP COLUMN meta_data');
            }
            if ($pa->hasColumn('description')) {
                $this->addSql('ALTER TABLE problem_attachment DROP COLUMN description');
            }
            if ($pa->hasColumn('url')) {
                $this->addSql('ALTER TABLE problem_attachment DROP COLUMN url');
            }
            if ($pa->hasColumn('rubricid')) {
                $this->addSql('ALTER TABLE problem_attachment DROP COLUMN rubricid');
            }
            if ($pa->hasIndex('rubricid_idx')) {
                $this->addSql('DROP INDEX rubricid_idx ON problem_attachment');
            }
            if ($pa->hasIndex('problem_id_idx')) {
                $this->addSql('DROP INDEX problem_id_idx ON problem_attachment');
            }
        }

        if ($sm->tablesExist(['problem_attachment_content'])) {
            $pac = $sm->listTableDetails('problem_attachment_content');
            if ($pac->hasColumn('url')) {
                $this->addSql('ALTER TABLE problem_attachment_content DROP COLUMN url');
            }
            if ($pac->hasColumn('meta_data')) {
                $this->addSql('ALTER TABLE problem_attachment_content DROP COLUMN meta_data');
            }
            if ($pac->hasColumn('type')) {
                $this->addSql('ALTER TABLE problem_attachment_content DROP COLUMN type');
            }
        }
    }
}
