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

namespace billing_invoice\models;

use AD\Finance\Price;
use Exception;
use billing_core\billing\TaxTypes;
use billing_invoice\models\Invoices;
use ecommerce_core\models\Products;
use lithium\aop\Filters;

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
		'base_core\extensions\data\behavior\Searchable' => [
			'fields' => [
				'description',
				'modified',
				'User.number'
			]
		]
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
		return TaxTypes::registry($entity->tax_type);
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

	/* Statistics */

	public static function topUnbilledUsers() {
		$data = [];

		$positions = InvoicePositions::find('all', [
			'conditions' => [
				'billing_invoice_id' => null
			],
			'fields' => [
				'user_id',
				'amount_currency',
				'amount_type',
				'amount_rate',
				'ROUND(SUM(InvoicePositions.amount * InvoicePositions.quantity)) AS amount'
			],
			'group' => [
				'user_id',
				'amount_currency',
				'amount_type',
				'amount_rate'
			],
			'order' => [
				// By using braces we prevent the data source to add `InvoicePositions.`
				// thus sorting by the original amount, but we want to sort by the
				// calculated summed up amount.
				'(amount)' => 'DESC'
			],
			'limit' => 10,
			'with' => ['User']
		]);
		foreach ($positions as $position) {
			$data[$position->user()->title()] = $position->amount();
		}
		return $data;
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

Filters::apply(InvoicePositions::class, 'save', function($params, $next) {
	$data =& $params['data'];

	// Ensure billing_invoice_id is never 0, but NULL
	if (isset($data['billing_invoice_id']) && empty($data['billing_invoice_id'])) {
		$data['billing_invoice_id'] = null;
	}
	return $next($params);
});

?>
