<?php
/**
 * Billing Invoice
 *
 * Copyright (c) 2014 Atelier Disko - All rights reserved.
 *
 * Licensed under the AD General Software License v1.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *
 * You should have received a copy of the AD General Software
 * License. If not, see http://atelierdisko.de/licenses.
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