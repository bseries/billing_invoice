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
use lithium\util\Set;

extract(Message::aliases());

Widgets::register('invoices', function() use ($t) {
	$formatter = new MoniesFormatter(Environment::get('locale'));

	return [
		'title' => $t('Invoices', ['scope' => 'billing_invoice']),
		'data' => [
			$t('total (this year, ongoing)', ['scope' => 'billing_invoice']) => $formatter->format(Invoices::totalInvoiced(date('Y'))->getNet()),
			$t('total (previous year)', ['scope' => 'billing_invoice']) => $formatter->format(Invoices::totalInvoiced(date('Y') - 1)->getNet()),
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

	$data = [];

	$grouped = InvoicePositions::find('all', [
		'group' => ['user_id'],
		'order' => ['count_positions' => 'DESC', 'created' => 'DESC'],
		'fields' => [
			// FIXME Currently does not calculate value but number
			// of positions as amount calculation is done app side.
			'COUNT(*) AS count_positions', 'user_id', 'User.id'
		],
		'conditions' => [
			'billing_invoice_id' => null,
			// Ignore all users that have been deleted in the meantime.
			'User.id' => ['IS NOT' => null]
		],
		'with' => ['User'],
		'limit' => 10
	]);
	$userIds = Set::extract(array_values($grouped->data()), '/user/id');

	if ($userIds) {
		$positions = InvoicePositions::find('all', [
			'conditions' => [
				'billing_invoice_id' => null,
				'user_id' => $userIds
			],
			'order' => [
				'field(user_id, ' . implode(',', $userIds) . ')',
				'created' => 'DESC'
			],
			'fields' => [
				'id', 'user_id', 'User.number', 'User.id', 'User.name',
				'quantity',
				'amount', 'amount_type', 'amount_currency', 'amount_rate'
			],
			'with' => ['User']
		]);

		foreach ($positions as $position) {
			$user = $position->user();
			$name = $user->number . ' / ' . $user->name;

			if (!isset($data[$name])) {
				$data[$name] = $position->total();
			} else {
				$data[$name] = $data[$name]->add($position->total());
			}
		}
	}
	return [
		'title' => $t('Top unbilled users'),
		'data' => array_map(function($v) use ($formatter) {
			return $formatter->format($v->getNet());
		}, $data),
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
