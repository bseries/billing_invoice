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
