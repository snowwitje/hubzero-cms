<?php

use Hubzero\Content\Migration;

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

/**
 * Migration script for weblinks table changes
 **/
class Migration20130924000014ComWeblinks extends Migration
{
	/**
	 * Up
	 **/
	protected static function up($db)
	{
		$query = "ALTER TABLE `#__weblinks` ENGINE = InnoDB;";
		$db->setQuery($query);
		$db->query();

		if ($db->tableHasField('#__weblinks', 'id'))
		{
			$query = "ALTER TABLE `#__weblinks` CHANGE COLUMN `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT;";
			$db->setQuery($query);
			$db->query();
		}
		if ($db->tableHasField('#__weblinks', 'alias'))
		{
			$query = "ALTER TABLE `#__weblinks` CHANGE COLUMN `alias` `alias` VARCHAR(255) CHARACTER SET 'utf8' COLLATE 'utf8_bin' NOT NULL DEFAULT '';";
			$db->setQuery($query);
			$db->query();
		}
		if ($db->tableHasField('#__weblinks', 'published') && !$db->tableHasField('#__weblinks', 'state'))
		{
			$query = "ALTER TABLE `#__weblinks` CHANGE COLUMN `published` `state` tinyint (1) NOT NULL DEFAULT '0';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'access') && $db->tableHasField('#__weblinks', 'approved'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN  `access` INT(11) NOT NULL DEFAULT '1' AFTER `approved`;";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'language'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `language` char(7) NOT NULL DEFAULT '';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'created'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'created_by'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `created_by` int(10) unsigned NOT NULL DEFAULT '0';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'created_by_alias'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `created_by_alias` varchar(255) NOT NULL DEFAULT '';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'modified'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'modified_by'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `modified_by` int(10) unsigned NOT NULL DEFAULT '0';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'metakey'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `metakey` text NOT NULL;";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'metadesc'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `metadesc` text NOT NULL;";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'metadata'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `metadata` text NOT NULL;";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'featured'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `featured` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Set if link is featured.';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'xreference'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `xreference` varchar(50) NOT NULL COMMENT 'A reference to enable linkages to external data sets.';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'publish_up'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `publish_up` datetime NOT NULL DEFAULT '0000-00-00 00:00:00';";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasField('#__weblinks', 'publish_down'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD COLUMN `publish_down` datetime NOT NULL DEFAULT '0000-00-00 00:00:00';";
			$db->setQuery($query);
			$db->query();
		}
		if ($db->tableHasKey('#__weblinks', 'catid'))
		{
			$query = "ALTER TABLE `#__weblinks` DROP KEY `catid`;";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasKey('#__weblinks', 'idx_access') && $db->tableHasField('#__weblinks', 'access'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD KEY `idx_access` (`access`);";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasKey('#__weblinks', 'idx_checkout') && $db->tableHasField('#__weblinks', 'checked_out'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD KEY `idx_checkout` (`checked_out`);";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasKey('#__weblinks', 'idx_state') && $db->tableHasField('#__weblinks', 'state'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD KEY `idx_state` (`state`);";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasKey('#__weblinks', 'idx_catid') && $db->tableHasField('#__weblinks', 'catid'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD KEY `idx_catid` (`catid`);";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasKey('#__weblinks', 'idx_createdby') && $db->tableHasField('#__weblinks', 'created_by'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD KEY `idx_createdby` (`created_by`);";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasKey('#__weblinks', 'idx_featured_catid') && $db->tableHasField('#__weblinks', 'featured') && $db->tableHasField('#__weblinks', 'catid'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD KEY `idx_featured_catid` (`featured`,`catid`);";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasKey('#__weblinks', 'idx_language') && $db->tableHasField('#__weblinks', 'language'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD KEY `idx_language` (`language`);";
			$db->setQuery($query);
			$db->query();
		}
		if (!$db->tableHasKey('#__weblinks', 'idx_xreference') && $db->tableHasField('#__weblinks', 'xreference'))
		{
			$query = "ALTER TABLE `#__weblinks` ADD KEY `idx_xreference` (`xreference`);";
			$db->setQuery($query);
			$db->query();
		}
	}
}