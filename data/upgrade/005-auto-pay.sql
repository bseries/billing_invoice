ALTER TABLE `users` ADD `is_auto_paying` TINYINT(1)  UNSIGNED  NOT NULL  DEFAULT '0'  COMMENT 'billing' AFTER `is_auto_invoiced`;
ALTER TABLE `users` ADD `payment_method` VARCHAR(100)  NULL  DEFAULT NULL  AFTER `tax_no`;


