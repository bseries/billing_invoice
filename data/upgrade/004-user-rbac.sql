ALTER TABLE `billing_invoices` ADD `owner_id` INT(11)  UNSIGNED  NOT NULL  AFTER `id`;
UPDATE `billing_invoices` SET `owner_id` = 1;
