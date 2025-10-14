<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251013120000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Create problem_display_data table (display/UX extension for problems)';
    }

    public function up(Schema $schema): void
    {
        // Migration platform guard (same pattern as other migrations)
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', "Migration can only be executed safely on 'mysql'.");

        // Use idempotent CREATE TABLE IF NOT EXISTS statements so this migration
        // can be applied safely even if parts of the schema already exist.

        // Temporarily disable foreign key checks while creating tables to avoid
        // ordering issues; re-enable at the end (many existing migrations do this).
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');

// Table structure for table `submission_deliverable`
    $this->addSql(<<<SQL
CREATE TABLE `submission_deliverable` (
    `deliverableid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `submitid` int(4) unsigned NOT NULL COMMENT 'Submission ID',
    `type` varchar(64) NOT NULL COMMENT 'Deliverable type (e.g. web app, CLI app)',
    `file_type` varchar(32) NOT NULL COMMENT 'File type (e.g. zip, tar.gz, etc)',
    `url` varchar(255) NOT NULL COMMENT 'URL to the deliverable file',
    PRIMARY KEY (`deliverableid`),
    KEY `submitid` (`submitid`),
    CONSTRAINT `submission_deliverable_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deliverables for each submission';
SQL
        );
    
        // Table structure for table `rubric`
    $this->addSql(<<<SQL
CREATE TABLE `rubric` (
    `rubricid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `probid` int(4) unsigned NOT NULL COMMENT 'Problem ID',
    `name` varchar(255) NOT NULL COMMENT 'Rubric name',
    `type` varchar(32) NOT NULL COMMENT 'Rubric type (manual, automated, etc)',
    `weight` float NOT NULL COMMENT 'Rubric weight',
    `threshold` float DEFAULT NULL COMMENT 'Threshold for passing (nullable)',
    `description` text DEFAULT NULL COMMENT 'Rubric description',
    PRIMARY KEY (`rubricid`),
    KEY `probid_idx` (`probid`),
    CONSTRAINT `rubric_ibfk_1` FOREIGN KEY (`probid`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rubrics for grading submissions';
SQL
        );

        // Table structure for table `submission_rubric_score`
        $this->addSql(<<<SQL
CREATE TABLE `submission_rubric_score` (
    `scoreid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `submitid` int(4) unsigned NOT NULL COMMENT 'Submission this score belongs to',
    `rubricid` int(4) unsigned NOT NULL COMMENT 'Rubric this score is for',
    `score` double NOT NULL COMMENT 'Score given for this rubric (0.0 to 1.0)',
    `judge_name` varchar(255) NOT NULL COMMENT 'Name of the judge who scored this',
    `judged_at` datetime NOT NULL COMMENT 'When this score was given',
    `comments` longtext DEFAULT NULL COMMENT 'Optional comments/feedback from the judge',
    PRIMARY KEY (`scoreid`),
    KEY `submitid` (`submitid`),
    KEY `rubricid` (`rubricid`),
    UNIQUE KEY `unique_submission_rubric` (`submitid`, `rubricid`),
    CONSTRAINT `submission_rubric_score_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
    CONSTRAINT `submission_rubric_score_ibfk_2` FOREIGN KEY (`rubricid`) REFERENCES `rubric` (`rubricid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manual rubric scores for hackathon submissions';
SQL
        );

    $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS `problem_display_data` (
    `pdisplayid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
    `problem_id` int(4) unsigned NOT NULL COMMENT 'Problem ID',
    `display_name` varchar(255) DEFAULT NULL COMMENT 'Display/alternate name for the problem',
    `description` text DEFAULT NULL COMMENT 'Rich HTML description for the problem',
    `image_url` varchar(255) DEFAULT NULL COMMENT 'Main image/banner URL for the problem',
    `created_at` datetime NOT NULL COMMENT 'Created at',
    `updated_at` datetime NOT NULL COMMENT 'Last updated at',
    PRIMARY KEY (`pdisplayid`),
    UNIQUE KEY `unique_problem_display_data` (`problem_id`),
    KEY `problem_id_idx` (`problem_id`),
    CONSTRAINT `problem_display_data_ibfk_1` FOREIGN KEY (`problem_id`) REFERENCES `problem` (`probid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Display/UX extension data for problems'
SQL
        );

    // `problem_attachment` and `problem_attachment_content` are created in
    // migration Version20201113094653.php; we avoid creating them here to
    // prevent duplicate-constraint and duplicate-table errors.

        // Ensure `url` column exists on `problem_attachment` (some installs
        // skipped earlier migrations). Use schema manager checks so this is
        // idempotent and safe to apply on databases that already have it.
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['problem_attachment'])) {
            $pa = $sm->listTableDetails('problem_attachment');
            if (!$pa->hasColumn('url')) {
                $this->addSql("ALTER TABLE problem_attachment ADD COLUMN `url` VARCHAR(255) DEFAULT NULL COMMENT 'Attachment URL'");
            }
        }

    // Table structure for table `contest_display_data`
        $this->addSql(<<<SQL
CREATE TABLE `contest_display_data` (
    `contest_id` int(4) unsigned NOT NULL,
    `title` varchar(255) DEFAULT NULL COMMENT 'Display title for the contest',
    `subtitle` varchar(255) DEFAULT NULL COMMENT 'Display subtitle for the contest',
    `banner_url` varchar(255) DEFAULT NULL COMMENT 'Banner image URL or path',
    `description` text DEFAULT NULL COMMENT 'Long description or HTML for contest display',
    `meta_data` json DEFAULT NULL COMMENT 'Flexible meta data for contest display',
    PRIMARY KEY (`contest_id`),
    CONSTRAINT `contest_display_data_ibfk_1` FOREIGN KEY (`contest_id`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Display data for contests (extension table)'
SQL
        );

    // Re-enable foreign key checks after creating tables
    $this->addSql('SET FOREIGN_KEY_CHECKS = 1');

    }

    public function down(Schema $schema): void
    {
        // Platform guard for down() (match other migrations)
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', "Migration can only be executed safely on 'mysql'.");

        // Drop created tables in reverse dependency order. Use IF EXISTS to be safe
        // when parts of the schema may already have been removed or come from
        // other migrations.
        // Remove the `url` column if it was added by this migration and exists
        try {
            $sm = $this->connection->createSchemaManager();
            if ($sm->tablesExist(['problem_attachment'])) {
                $pa = $sm->listTableDetails('problem_attachment');
                if ($pa->hasColumn('url')) {
                    $this->addSql('ALTER TABLE problem_attachment DROP COLUMN url');
                }
            }
        } catch (\Throwable $e) {
            // ignore errors during down migration cleanup
        }

        $this->addSql('DROP TABLE IF EXISTS `submission_rubric_score`');
        $this->addSql('DROP TABLE IF EXISTS `rubric`');
        $this->addSql('DROP TABLE IF EXISTS `submission_deliverable`');
        $this->addSql('DROP TABLE IF EXISTS `contest_display_data`');
        $this->addSql('DROP TABLE IF EXISTS `problem_display_data`');
    }
}