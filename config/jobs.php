<?php
/**
 * Billing Invoice
 *
 * Copyright (c) 2015 David Persson - All rights reserved.
 * Copyright (c) 2016 Atelier Disko - All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace billing_invoice\config;

use base_core\async\Jobs;
use base_core\extensions\cms\Settings;
use base_core\models\Users;
use billing_invoice\models\Invoices;

if (Settings::read('invoice.autoInvoice')) {
	Jobs::recur('billing_invoice:auto_invoice', function() {
		Invoices::pdo()->beginTransaction();

		$users = Users::find('all', [
			'conditions' => [
				'is_auto_invoiced' => true
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
		return true;
	}, [
		'frequency' => Jobs::FREQUENCY_LOW,
		'needs' => ['billing_time:invoice_place_timed' => 'optional']
	]);
}

if (Settings::read('invoice.autoSend')) {
	Jobs::recur('billing_invoice:auto_send', function() {
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
		return true;
	}, [
		'frequency' => Jobs::FREQUENCY_LOW,
		'needs' => ['billing_invoice:auto_invoice' => 'optional']
	]);
}

?>