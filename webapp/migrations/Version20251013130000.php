<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251013130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index, foreign key and JSON check for problem_attachment.rubricid';
    }

    public function up(Schema $schema): void
    {
        // Add index
        $this->addSql('ALTER TABLE problem_attachment ADD KEY `rubricid_idx` (`rubricid`)');

        // Add foreign key to rubric table
        $this->addSql('ALTER TABLE problem_attachment ADD CONSTRAINT `FK_problem_attachment_rubricid` FOREIGN KEY (`rubricid`) REFERENCES rubric (`rubricid`) ON DELETE SET NULL');

        // Add JSON check constraint
        $this->addSql('ALTER TABLE problem_attachment ADD CONSTRAINT `chk_problem_attachment_meta_data` CHECK (json_valid(`meta_data`))');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key and index
        $this->addSql('ALTER TABLE problem_attachment DROP FOREIGN KEY `FK_problem_attachment_rubricid`');
        $this->addSql('ALTER TABLE problem_attachment DROP INDEX `rubricid_idx`');

        // Drop JSON check constraint
        $this->addSql('ALTER TABLE problem_attachment DROP CHECK `chk_problem_attachment_meta_data`');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
