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

use base_core\extensions\cms\Panes;
use lithium\g11n\Message;

extract(Message::aliases());

Panes::register('billing.invoices', [
	'title' => $t('Invoices', ['scope' => 'billing_invoice']),
	'url' => [
		'library' => 'billing_invoice',
		'controller' => 'Invoices', 'action' => 'index',
		'admin' => true
	],
	'weight' => 40
]);
Panes::register('billing.invoicePositions', [
	'title' => $t('Pending', ['scope' => 'billing_invoice']),
	'url' => [
		'library' => 'billing_invoice',
		'controller' => 'InvoicePositions', 'action' => 'index',
		'admin' => true
	],
	'weight' => 41
]);

?>