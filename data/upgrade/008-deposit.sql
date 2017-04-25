ALTER TABLE `billing_invoices` ADD `deposit` INT(10)  UNSIGNED  NULL  DEFAULT NULL  AFTER `tax_note`;
ALTER TABLE `billing_invoices` ADD `deposit_currency` CHAR(3)  NULL  DEFAULT NULL  AFTER `deposit`;
ALTER TABLE `billing_invoices` ADD `deposit_type` CHAR(5)  NULL  DEFAULT NULL  AFTER `deposit_currency`;
ALTER TABLE `billing_invoices` ADD `deposit_rate` INT(5)  UNSIGNED  NULL  DEFAULT NULL  AFTER `deposit_type`;
ALTER TABLE `billing_invoices` ADD `finalizes` VARCHAR(250)  NULL  DEFAULT NULL  COMMENT 'deposit inv ids to be finalized'  AFTER `deposit_rate`;
