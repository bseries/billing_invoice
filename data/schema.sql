-- Create syntax for TABLE 'billing_invoice_positions'
CREATE TABLE `billing_invoice_positions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `billing_invoice_id` int(11) unsigned DEFAULT NULL COMMENT 'NULL until assigned to invoice',
  `user_id` int(11) unsigned DEFAULT NULL,
  `virtual_user_id` int(11) unsigned DEFAULT NULL,
  `description` varchar(250) NOT NULL,
  `quantity` decimal(10,2) unsigned NOT NULL DEFAULT '1.00',
  `amount` int(10) NOT NULL,
  `amount_currency` char(3) NOT NULL DEFAULT 'EUR',
  `amount_type` char(5) NOT NULL DEFAULT 'net',
  `amount_rate` int(5) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_invoice_id` (`billing_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'billing_invoices'
CREATE TABLE `billing_invoices` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned DEFAULT NULL,
  `virtual_user_id` int(11) unsigned DEFAULT NULL,
  `number` varchar(100) NOT NULL DEFAULT '',
  `date` date NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'created',
  `user_vat_reg_no` varchar(250) DEFAULT '',
  `address_recipient` varchar(250) DEFAULT '',
  `address_organization` varchar(250) DEFAULT NULL,
  `address_address_line_1` varchar(250) DEFAULT NULL,
  `address_address_line_2` varchar(250) DEFAULT NULL,
  `address_locality` varchar(100) DEFAULT NULL,
  `address_dependent_locality` varchar(100) DEFAULT NULL,
  `address_postal_code` varchar(100) DEFAULT NULL,
  `address_sorting_code` varchar(100) DEFAULT NULL,
  `address_country` char(2) DEFAULT 'DE',
  `address_administrative_area` varchar(200) DEFAULT NULL,
  `address_phone` varchar(200) DEFAULT NULL,
  `terms` text,
  `note` text,
  `tax_type` varchar(20) NOT NULL DEFAULT '',
  `is_locked` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `number` (`number`),
  KEY `user` (`user_id`),
  KEY `virtual_user_id` (`virtual_user_id`)
) ENGINE=InnoDB CHARSET=utf8;

-- Augment other tables
ALTER TABLE `users` ADD `is_auto_invoiced` TINYINT(1)  UNSIGNED  NOT NULL  DEFAULT '0'  COMMENT 'billing' AFTER `is_notified`;
ALTER TABLE `users` ADD `auto_invoiced` DATETIME  NULL  COMMENT 'billing' AFTER `is_auto_invoiced`;
ALTER TABLE `users` ADD `auto_invoice_frequency` VARCHAR(20)  NOT NULL  DEFAULT 'monthly'  COMMENT 'billing'  AFTER `auto_invoiced`;

ALTER TABLE `virtual_users` ADD `is_auto_invoiced` TINYINT(1)  UNSIGNED  NOT NULL  DEFAULT '0'  COMMENT 'billing' AFTER `is_notified`;
ALTER TABLE `virtual_users` ADD `auto_invoiced` DATETIME  NULL  COMMENT 'billing' AFTER `is_auto_invoiced`;
ALTER TABLE `virtual_users` ADD `auto_invoice_frequency` VARCHAR(20)  NOT NULL  DEFAULT 'monthly'  COMMENT 'billing'  AFTER `auto_invoiced`;

