<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251013000004 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Adding hackathon_enabled column to contest table';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', "Migration can only be executed safely on 'mysql'.");
        // Only add the `hackathon_enabled` column to the `contest` table here.
        $sm = $this->connection->getSchemaManager();
        if ($sm->tablesExist(['contest'])) {
            $contestTable = $sm->listTableDetails('contest');
            if (!$contestTable->hasColumn('hackathon_enabled')) {
                $this->addSql("ALTER TABLE contest ADD COLUMN hackathon_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Enable hackathon extension for this contest'");
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', "Migration can only be executed safely on 'mysql'.");
        $sm = $this->connection->getSchemaManager();
        if ($sm->tablesExist(['contest'])) {
            $contestTable = $sm->listTableDetails('contest');
            if ($contestTable->hasColumn('hackathon_enabled')) {
                $this->addSql('ALTER TABLE contest DROP COLUMN hackathon_enabled');
            }
        }
    }
}
