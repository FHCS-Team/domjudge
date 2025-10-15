<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add package_path, package_type, and package_source fields to problem table
 * for hackathon project evaluation system
 */
final class Version20251015130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add package metadata fields to problem table for custom hackathon problems';
    }

    public function up(Schema $schema): void
    {
        // Add new fields to problem table
        $this->addSql('ALTER TABLE problem ADD package_path LONGTEXT DEFAULT NULL COMMENT \'Path to the problem package file on the server\'');
        $this->addSql('ALTER TABLE problem ADD package_type VARCHAR(255) DEFAULT NULL COMMENT \'Package type: file, url, git\'');
        $this->addSql('ALTER TABLE problem ADD package_source LONGTEXT DEFAULT NULL COMMENT \'Original package URL or git repository\'');
    }

    public function down(Schema $schema): void
    {
        // Remove added fields
        $this->addSql('ALTER TABLE problem DROP package_path');
        $this->addSql('ALTER TABLE problem DROP package_type');
        $this->addSql('ALTER TABLE problem DROP package_source');
    }
}
