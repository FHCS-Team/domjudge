<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add custom judgehost support for non-traditional problems.
 * Adds fields to Problem and Submission entities, and configuration options.
 */
final class Version20251015120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add custom judgehost support - add fields to Problem and Submission entities';
    }

    public function up(Schema $schema): void
    {
        // Add custom problem fields to Problem table
        $this->addSql(
            'ALTER TABLE problem ' .
            'ADD COLUMN is_custom_problem TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Whether this is a custom problem requiring custom judgehost\', ' .
            'ADD COLUMN custom_config JSON DEFAULT NULL COMMENT \'Custom problem configuration from config.json\', ' .
            'ADD COLUMN custom_judgehost_data JSON DEFAULT NULL COMMENT \'Data returned from custom judgehost registration\', ' .
            'ADD COLUMN project_type VARCHAR(255) DEFAULT NULL COMMENT \'Project type for custom problems (database, nodejs-api, etc.)\''
        );

        // Add JSON validation constraints for custom fields
        $this->addSql('ALTER TABLE problem ADD CONSTRAINT `chk_problem_custom_config` CHECK (json_valid(`custom_config`))');
        $this->addSql('ALTER TABLE problem ADD CONSTRAINT `chk_problem_custom_judgehost_data` CHECK (json_valid(`custom_judgehost_data`))');

        // Add index on is_custom_problem for faster queries
        $this->addSql('ALTER TABLE problem ADD INDEX `idx_is_custom_problem` (`is_custom_problem`)');
        $this->addSql('ALTER TABLE problem ADD INDEX `idx_project_type` (`project_type`)');

        // Add custom submission fields to Submission table
        $this->addSql(
            'ALTER TABLE submission ' .
            'ADD COLUMN custom_judgehost_submission_id VARCHAR(255) DEFAULT NULL COMMENT \'Submission ID from custom judgehost\', ' .
            'ADD COLUMN custom_execution_metadata JSON DEFAULT NULL COMMENT \'Metadata from custom judgehost execution\''
        );

        // Add JSON validation constraint
        $this->addSql('ALTER TABLE submission ADD CONSTRAINT `chk_submission_custom_metadata` CHECK (json_valid(`custom_execution_metadata`))');

        // Add index on custom_judgehost_submission_id for lookups
        $this->addSql('ALTER TABLE submission ADD INDEX `idx_custom_judgehost_submission_id` (`custom_judgehost_submission_id`)');

        // Add configuration options for custom judgehost
        $this->addSql(
            "INSERT INTO configuration (name, value) VALUES " .
            "('custom_judgehost_enabled', '0'), " .
            "('custom_judgehost_url', ''), " .
            "('custom_judgehost_api_key', ''), " .
            "('custom_judgehost_timeout', '300')"
        );
    }

    public function down(Schema $schema): void
    {
        // Remove configuration options
        $this->addSql("DELETE FROM configuration WHERE name IN ('custom_judgehost_enabled', 'custom_judgehost_url', 'custom_judgehost_api_key', 'custom_judgehost_timeout')");

        // Drop submission custom fields
        $this->addSql('ALTER TABLE submission DROP INDEX `idx_custom_judgehost_submission_id`');
        $this->addSql('ALTER TABLE submission DROP CHECK `chk_submission_custom_metadata`');
        $this->addSql('ALTER TABLE submission DROP COLUMN custom_judgehost_submission_id, DROP COLUMN custom_execution_metadata');

        // Drop problem custom fields
        $this->addSql('ALTER TABLE problem DROP INDEX `idx_project_type`');
        $this->addSql('ALTER TABLE problem DROP INDEX `idx_is_custom_problem`');
        $this->addSql('ALTER TABLE problem DROP CHECK `chk_problem_custom_judgehost_data`');
        $this->addSql('ALTER TABLE problem DROP CHECK `chk_problem_custom_config`');
        $this->addSql('ALTER TABLE problem DROP COLUMN is_custom_problem, DROP COLUMN custom_config, DROP COLUMN custom_judgehost_data, DROP COLUMN project_type');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
