<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_1_2_0() {
	if (!db_column_exists('user_domains_ldap', 'cn_full_name')) {
		db_install_execute("ALTER TABLE `user_domains_ldap`
			ADD `cn_full_name` VARCHAR(50) NULL DEFAULT '',
			ADD `cn_email` VARCHAR(50) NULL DEFAULT ''");
	}

	if (!db_column_exists('poller', 'max_time')) {
		db_install_execute("ALTER TABLE poller
			ADD COLUMN max_time DOUBLE DEFAULT NULL AFTER total_time,
			ADD COLUMN min_time DOUBLE DEFAULT NULL AFTER max_time,
			ADD COLUMN avg_time DOUBLE DEFAULT NULL AFTER min_time,
			ADD COLUMN total_polls INT unsigned DEFAULT '0' AFTER avg_time,
			ADD COLUMN processes INT unsigned DEFAULT '1' AFTER total_polls,
			ADD COLUMN threads INT unsigned DEFAULT '1' AFTER processes");
	}

	if (!db_column_exists('host', 'location')) {
		db_install_execute("ALTER TABLE host
			ADD COLUMN location VARCHAR(40) DEFAULT NULL AFTER hostname,
			ADD INDEX site_id_location(site_id, location)");
	}

	if (!db_table_exists('mailer_attachment')) {
		db_install_execute('CREATE TABLE IF NOT EXISTS `mailer_attachment` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`mailer_item_id` int(10) unsigned NOT NULL,
			`mailer_data_id` int(10) unsigned NOT NULL,
			`filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
			`mime_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`inline` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
			`cid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			PRIMARY KEY (`id`),
			KEY `mailer_item_id` (`mailer_item_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			COMMENT=\'Used for storing mail details before sending\'');
	}

	if (!db_table_exists('mailer_data')) {
		db_install_execute('CREATE TABLE IF NOT EXISTS `mailer_data` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`mailer_item_id` int(10) unsigned NOT NULL,
			`data` blob,
			`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `mailer_item_id` (`mailer_item_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			COMMENT=\'Used for storing large data for mailing purposes\'');
	}

	if (!db_table_exists('mailer_header')) {
		db_install_execute('CREATE TABLE IF NOT EXISTS `mailer_header` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`mailer_data_id` int(10) unsigned NOT NULL,
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			COMMENT=\'Used for storing headers for mailing purposes\'');
	}

	if (!db_table_exists('mailer_item')) {
		db_install_execute('CREATE TABLE IF NOT EXISTS `mailer_item` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`retries` tinyint(2) NOT NULL DEFAULT \'0\',
			`last_retry` timestamp NOT NULL DEFAULT \'0000-00-00 00:00:00\',
			`last_error` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\',
			`body_text_id` int(10) unsigned NOT NULL,
			`body_html_id` int(10) unsigned NOT NULL,
			`body_is_html` tinyint(1) unsigned NOT NULL DEFAULT \'0\',
			`pid` smallint unsigned NOT NULL DEFAULT \'0\',
			`status` tinyint unsigned NOT NULL DEFAULT \'0\',
			PRIMARY KEY (`id`),
			KEY `retries` (`retries`),
			KEY `pid` (`pid`),
			KEY `status` (`status`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			COMMENT=\'Used for storing mail items before sending\'');
	}

	if (!db_table_exists('mailer_recipient')) {
		db_install_execute('CREATE TABLE IF NOT EXISTS `mailer_recipient` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`mailer_item_id` int(10) unsigned NOT NULL,
			`email_type` tinyint(4) unsigned NOT NULL,
			`email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\',
			`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\',
			PRIMARY KEY (`id`),
 			KEY `mailer_item_id` (`mailer_item_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			COMMENT=\'Email recipients for mail items\'');
	}
}
