<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251013000005 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add url, description, meta_data, rubricid and visibility to problem_attachment';
	}

	public function up(Schema $schema): void
	{
		// Add columns only; index/foreign key/check are applied in a later migration
		$this->addSql('ALTER TABLE problem_attachment ADD `url` VARCHAR(255) DEFAULT NULL COMMENT "Attachment URL"');
		$this->addSql('ALTER TABLE problem_attachment ADD `description` TEXT DEFAULT NULL COMMENT "Attachment description"');
		$this->addSql('ALTER TABLE problem_attachment ADD `meta_data` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT "Flexible meta data for attachment"');
		$this->addSql('ALTER TABLE problem_attachment ADD `rubricid` INT UNSIGNED DEFAULT NULL COMMENT "Associated rubric for this attachment"');
		$this->addSql('ALTER TABLE problem_attachment ADD `visibility` VARCHAR(32) DEFAULT NULL COMMENT "Visibility of attachment (public, hidden, private)"');
	}

	public function down(Schema $schema): void
	{
		// Drop the columns we added
		$this->addSql('ALTER TABLE problem_attachment DROP `url`');
		$this->addSql('ALTER TABLE problem_attachment DROP `description`');
		$this->addSql('ALTER TABLE problem_attachment DROP `meta_data`');
		$this->addSql('ALTER TABLE problem_attachment DROP `rubricid`');
		$this->addSql('ALTER TABLE problem_attachment DROP `visibility`');
	}

	public function isTransactional(): bool
	{
		return false;
	}
}

