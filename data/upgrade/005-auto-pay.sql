ALTER TABLE `users` ADD `is_auto_paying` TINYINT(1)  UNSIGNED  NOT NULL  DEFAULT '0'  COMMENT 'billing' AFTER `is_auto_invoiced`;
