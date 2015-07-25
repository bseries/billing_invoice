ALTER TABLE `billing_invoices` DROP `virtual_user_id`;
ALTER TABLE `billing_invoices` CHANGE `user_id` `user_id` INT(11)  UNSIGNED  NOT NULL;
ALTER TABLE `billing_invoice_positions` DROP `virtual_user_id`;
ALTER TABLE `billing_invoice_positions` CHANGE `user_id` `user_id` INT(11)  UNSIGNED  NOT NULL;

