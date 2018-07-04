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
 * License. If not, see https://atelierdisko.de/licenses.
 */

namespace billing_invoice\config;

use AD\Finance\Money\MoneyIntlFormatter as MoneyFormatter;
use AD\Finance\Money\MoniesIntlFormatter as MoniesFormatter;
use base_core\extensions\cms\Widgets;
use billing_invoice\models\InvoicePositions;
use billing_invoice\models\Invoices;
use lithium\core\Environment;
use lithium\g11n\Message;

extract(Message::aliases());

Widgets::register('invoices', function() use ($t) {
	$formatter = new MoniesFormatter(Environment::get('locale'));

	return [
		'title' => $t('Invoices', ['scope' => 'billing_invoice']),
		'data' => [
			$t('total ({:year}, ongoing)', [
				'scope' => 'billing_invoice',
				'year' => date('Y')
			]) => $formatter->format(Invoices::totalInvoiced(date('Y'))->getNet()),
			$t('total ({:year})', [
				'scope' => 'billing_invoice',
				'year' => date('Y') - 1
			]) => $formatter->format(Invoices::totalInvoiced(date('Y') - 1)->getNet()),
			$t('pending', ['scope' => 'billing_invoice']) => Invoices::countPending(),
			$t('paid', ['scope' => 'billing_invoice']) => round(Invoices::paidRate(), 0) . '%',
		],
		'url' => [
			'library' => 'billing_invoice',
			'controller' => 'Invoices',
			'action' => 'index'
		]
	];
}, [
	'type' => Widgets::TYPE_COUNTER,
	'group' => Widgets::GROUP_DASHBOARD
]);

Widgets::register('topUnbilledUsers', function() use ($t) {
	$formatter = new MoneyFormatter(Environment::get('locale'));

	return [
		'title' => $t('Top unbilled users'),
		'data' => array_map(function($v) use ($formatter) {
			return $formatter->format($v->getNet());
		}, InvoicePositions::topUnbilledUsers()),
		'url' => [
			'library' => 'billing_invoice',
			'controller' => 'InvoicePositions',
			'action' => 'index'
		]
	];
}, [
	'type' => Widgets::TYPE_TABLE,
	'group' => Widgets::GROUP_DASHBOARD
]);

?>
