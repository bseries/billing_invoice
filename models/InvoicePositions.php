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

namespace billing_invoice\models;

use Exception;
use AD\Finance\Price;
use billing_invoice\models\Invoices;
use billing_core\models\TaxTypes;
use ecommerce_core\models\Products;

// In the moment of generating an invoice position the price is finalized.
class InvoicePositions extends \base_core\models\Base {

	protected $_meta = [
		'source' => 'billing_invoice_positions'
	];

	protected $_actsAs = [
		'base_core\extensions\data\behavior\RelationsPlus',
		'base_core\extensions\data\behavior\Timestamp',
		'base_core\extensions\data\behavior\Localizable' => [
			'fields' => [
				'amount' => 'money',
				'quantity' => 'decimal'
			]
		],
		'li3_taggable\extensions\data\behavior\Taggable' => [
			'field' => 'tags',
			'tagsModel' => 'base_tag\models\Tags',
			'filters' => ['strtolower']
		],
	];

	public $belongsTo = [
		'User' => [
			'to' => 'base_core\models\Users',
			'key' => 'user_id'
		],
		'Invoice' => [
			'to' => 'billing_invoice\models\Invoices',
			'key' => 'billing_invoice_id'
		]
	];

	public static function pending($user) {
		return static::find('all', [
			'conditions' => [
				'user_id' => $user->id,
				'billing_invoice_id' => null
			]
		]);
	}

	public function amount($entity) {
		return new Price(
			(integer) $entity->amount,
			$entity->amount_currency,
			$entity->amount_type,
			(integer) $entity->amount_rate
		);
	}

	public function total($entity) {
		return $entity->amount()->multiply($entity->quantity);
	}

	public function taxType($entity) {
		return TaxTypes::find('first', ['conditions' => ['name' => $entity->tax_type]]);
	}

	// Assumes format "Foobar (#12345)".
	public function product($entity) {
		if (!preg_match('/\(#(.*)\)/', $entity->description, $matches)) {
			return false;
		}
		return Products::find('first', [
			'conditions' => [
				'number' => $matches[1]
			]
		]);
	}

	/* Deprecated */

	// Assumes format "Foobar (#12345)".
	public function itemNumber($entity) {
		trigger_error('itemNumber() has been deprecated, use product() instead.', E_USER_DEPRECATED);

		if (!preg_match('/\(#(.*)\)/', $entity->description, $matches)) {
			throw new Exception('Failed to extract item number from description.');
		}
		return $matches[1];
	}

	// Assumes format "Foobar (#12345)".
	public function itemTitle($entity) {
		trigger_error('itemTitle() has been deprecated, use product() instead.', E_USER_DEPRECATED);

		if (!preg_match('/^(.*)\(/', $entity->description, $matches)) {
			throw new Exception('Failed to extract item title from description.');
		}
		return $matches[1];
	}

	public function totalAmount($entity) {
		trigger_error('InvoicePositions::totalAmount has been deprecated in favor of total().', E_USER_DEPRECATED);
		return $entity->total();
	}
}

InvoicePositions::applyFilter('save', function($self, $params, $chain) {
	$data =& $params['data'];

	// Ensure billing_invoice_id is never 0, but NULL
	if (empty($data['billing_invoice_id'])) {
		$data['billing_invoice_id'] = null;
	}
	return $chain->next($self, $params, $chain);
});

?>