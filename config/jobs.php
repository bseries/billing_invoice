<?php
/**
 * Billing Core
 *
 * Copyright (c) 2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace billing_core\config;

use DateTime;
use base_core\extensions\cms\Settings;
use base_core\extensions\cms\Jobs;
use base_core\models\Users;
use billing_core\models\Invoices;

// Generates invoices from pending invoice positions.
Jobs::recur('billing_core:auto_invoice', function() {
	Invoices::pdo()->beginTransaction();

	// FIXME Make this work for virtual users, too?
	$users = Users::find('all', [
		'conditions' => [
			'is_auto_invoiced' => true
			// 'is_active' => true
		]
	]);
	foreach ($users as $user) {
		if (!Invoices::mustAutoInvoice($user)) {
			continue;
		}
		if (!Invoices::autoInvoice($user)) {
			Invoices::pdo()->rollback();
			return false;
		}
	}
	Invoices::pdo()->commit();
}, [
	'frequency' => Jobs::FREQUENCY_LOW,
	'depends' => ['billing_time:invoice_place_timed' => 'optional']
]);

// This will auto send any invoice that is plain created but not sent.
Jobs::recur('billing_core:auto_send_invoices', function() {
	if (!Settings::read('invoce.autoSend')) {
		return true;
	}
	$invoices = Invoices::find('all', [
		'status' => 'created'
	]);
	foreach ($invoice as $invoice) {
		Invoices::pdo()->beginTransaction();

		if (!$invoice->send()) {
			Invoices::pdo()->rollback();
			return false;
		}
		Invoices::pdo()->commit();
	}
}, [
	'frequency' => Jobs::FREQUENCY_LOW,
	'depends' => ['billing_core:auto_invoice']
]);

?>