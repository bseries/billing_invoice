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

use AD\Finance\Money\MoneyIntlFormatter as MoneyFormatter;
use AD\Finance\Money\Monies;
use AD\Finance\Money\MoniesIntlFormatter as MoniesFormatter;
use base_core\extensions\cms\Widgets;
use billing_invoice\models\InvoicePositions;
use billing_invoice\models\Invoices;
use lithium\core\Environment;
use lithium\g11n\Message;

extract(Message::aliases());

Widgets::register('invoices', function() use ($t) {
	$formatter = new MoniesFormatter(Environment::get('locale'));

	$invoiced = new Monies();

	$invoices = Invoices::find('all', [
		'conditions' => [
			'status' => ['!=' => [
				'draft',
				'cancelled'
			]]
		],
		'fields' => [
			'id'
		]
	]);
	foreach ($invoices as $invoice) {
		foreach ($invoice->totals()->sum() as $rate => $currencies) {
			foreach ($currencies as $currency => $price) {
				$invoiced = $invoiced->add($price->getNet());
			}
		}
	}

	$pending = Invoices::find('count', [
		'conditions' => [
			'status'  => [
				'created',
				'awaiting-payment',
				'payment-remotely-accepted',
				'payment-error',
				'sent',
				'send-scheduled'
			]
		]
	]);

	$paid = Invoices::find('count', [
		'conditions' => [
			'status'  => 'paid'
		]
	]);
	$rate = round(($paid * 100) / $invoices->count(), 0);


	return [
		'title' => $t('Invoices', ['scope' => 'billing_invoice']),
		'data' => [
			$t('invoiced', ['scope' => 'billing_invoice']) => $formatter->format($invoiced),
			$t('pending', ['scope' => 'billing_invoice']) => $pending,
			$t('paid rate', ['scope' => 'billing_estimate']) =>  $rate . '%',
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

Widgets::register('pendingInvoicePositions', function() use ($t) {
	$formatter = new MoneyFormatter(Environment::get('locale'));

	$data = [];
	$positions = InvoicePositions::find('all', [
		'conditions' => [
			'billing_invoice_id' => null,
		],
		'order' => [
			'User.number'
		],
		'with' => ['User']
	]);


	foreach ($positions as $position) {
		if (!$user = $position->user()) {
			continue;
		}
		if (!isset($data[$user->number])) {
			$data[$user->number] = $position->total();
		} else {
			$data[$user->number] = $data[$user->number]->add($position->total());
		}
		if (count($data) === 15) {
			// Cannot display that many positions.
			$data = [];
			break;
		}
	}
	return [
		'title' => $t('Pending invoice positions'),
		'data' => array_map(function($v) use ($formatter) { return $formatter->format($v->getNet()); }, $data),
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