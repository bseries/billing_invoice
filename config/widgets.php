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

use AD\Finance\Money\Monies;
use AD\Finance\Money\MoniesIntlFormatter as MoniesFormatter;
use base_core\extensions\cms\Widgets;
use billing_invoice\models\Invoices;
use lithium\core\Environment;
use lithium\g11n\Message;

extract(Message::aliases());

Widgets::register('cashflow', function() use ($t) {
	$formatter = new MoniesFormatter(Environment::get('locale'));

	$invoiced = new Monies();
	$invoices = Invoices::find('all', [
		'conditions' => [
			'status' => [
				'!=' => 'cancelled'
			]
		],
		'fields' => [
			'id'
		]
	]);

	foreach ($invoices as $invoice) {
		foreach ($invoice->totals()->sum() as $rate => $currencies) {
			foreach ($currencies as $currency => $price) {
				$invoiced = $invoiced->add($price->getGross());
			}
		}
	}

	return [
		'data' => [
			$t('invoiced', ['scope' => 'billing_invoice']) => $formatter->format($invoiced),
		]
	];
}, [
	'type' => Widgets::TYPE_COUNTER,
	'group' => Widgets::GROUP_DASHBOARD
]);

?>