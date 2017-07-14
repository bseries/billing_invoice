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

namespace billing_invoice\models;

use AD\Finance\Money\Monies;
use AD\Finance\Price;
use AD\Finance\Price\Prices;
use DateInterval;
use DateTime;
use Exception;
use base_address\models\Addresses;
use base_address\models\Contacts;
use base_core\extensions\cms\Settings;
use base_tag\models\Tags;
use billing_core\billing\ClientGroups;
use billing_core\billing\TaxTypes;
use billing_invoice\models\InvoicePositions;
use billing_payment\models\Payments;
use li3_mailer\action\Mailer;
use lithium\analysis\Logger;
use lithium\aop\Filters;
use lithium\core\Libraries;
use lithium\g11n\Message;
use lithium\util\Collection;

// Given our business resides in Germany DE and we're selling services
// which fall und § 3 a Abs. 4 UStG (Katalogleistung).
//
// Denormalizing in order to regenerate invoices
// even when user changes details.
//
// @link http://www.hk24.de/recht_und_steuern/steuerrecht/umsatzsteuer_mehrwertsteuer/umsatzsteuer_mehrwertsteuer_international/367156/USt_grenzueber_Dienstleistungen.html
// @link http://www.revenue.ie/en/tax/vat/leaflets/place-of-supply-of-services.html
// @link http://www.hk24.de/en/international/tax/347922/vat_goods_trading_eu.html
// @link http://www.stuttgart.ihk24.de/recht_und_steuern/steuerrecht/Umsatzsteuer_Verbrauchssteuer/Umsatzsteuer_international/971988/Steuern_und_Abgaben_grenzueberschreitend.html#121
// @link http://www.hk24.de/recht_und_steuern/steuerrecht/umsatzsteuer_mehrwertsteuer/umsatzsteuer_mehrwertsteuer_international/644156/Uebersetzung_Steuerschuldnerschaft_des_Leistungsempfaengers.html
class Invoices extends \base_core\models\Base {

	protected $_meta = [
		'source' => 'billing_invoices'
	];

	protected $_actsAs = [
		'base_core\extensions\data\behavior\Ownable',
		'base_core\extensions\data\behavior\RelationsPlus',
		'base_core\extensions\data\behavior\Timestamp',
		'base_core\extensions\data\behavior\Localizable' => [
			'fields' => [
				'deposit' => 'money'
			]
		],
		'base_core\extensions\data\behavior\ReferenceNumber',
		'base_core\extensions\data\behavior\StatusChange',
		'base_core\extensions\data\behavior\Searchable' => [
			'fields' => [
				'User.name',
				'User.number',
				'Owner.name',
				'Owner.number',
				'number',
				'status',
				'date',
				'address_recipient',
				'address_organization'
			]
		],
		'base_core\extensions\data\behavior\Serializable' => [
			'fields' => [
				'finalizes' => ','
			]
		]
	];

	public $belongsTo = [
		'User' => [
			'to' => 'base_core\models\Users',
			'key' => 'user_id'
		],
		'Owner' => [
			'to' => 'base_core\models\Users',
			'key' => 'owner_id'
		]
	];

	public $hasMany = [
		'Positions' => [
			'to' => 'billing_invoice\models\InvoicePositions',
			'key' => 'billing_invoice_id'
		],
		'Payments' => [
			'to' => 'billing_payment\models\Payments',
			'key' => 'billing_invoice_id'
		],
	];

	public static $enum = [
		'status' => [
			'draft',
			'created',

			'paid',  // paid

			'awaiting-payment',
			'payment-remotely-accepted',
			'payment-error',

			'sent',
			'send-scheduled',

			'cancelled', // storno
			'rejected', // storno
		],
		'frequency' => [
			'monthly',
			'yearly'
		]
	];

	public static function init() {
		$model = static::_object();

		static::behavior('base_core\extensions\data\behavior\ReferenceNumber')->config(
			Settings::read('invoice.number')
		);
	}

	public function positionsGroupedByTags($entity, array $order = [], array $entitiesOptions = []) {
		$seen = [];
		$groups = [];

		if ($order) {
			$groups = array_fill_keys($order, null);
		}

		foreach ($entity->positions() as $position) {
			$group = Tags::create();

			// Search for first dollar prefixed tag and use it
			// as the main tag.
			$tags = $position->tags([
				'serialized' => false,
				'entities' => $entitiesOptions ?: true
			]);

			foreach ($tags as $tag) {
				if ($tag->name[0] === '$') {
					$group = $tag;
					break;
				}
			}

			if (!isset($groups[$group->name])) {
				$groups[$group->name] = [
					'positions' => [],
					'tag' => $group
				];
			}
			$groups[$group->name]['positions'][] = $position;
		}
		return array_filter($groups);
	}

	public function title($entity) {
		return $entity->number;
	}

	public function date($entity) {
		return DateTime::createFromFormat('Y-m-d', $entity->date);
	}

	public function isOverdue($entity) {
		if (!$overdue = Settings::read('invoice.overdueAfter')) {
			return false;
		}
		$date = DateTime::createFromFormat('Y-m-d H:i:s', $entity->date);

		return $entity->total_gross_outstanding && $date->getTimestamp() > strtotime($overdue);
	}

	// Returns Prices.
	public function totals($entity) {
		$result = new Prices();

		foreach ($entity->positions() as $position) {
			$result = $result->add($position->total());
		}
		return $result;
	}

	// Monies keyed by rate.
	public function taxes($entity) {
		$results = [];

		foreach ($entity->totals()->sum() as $rate => $currencies) {
			foreach ($currencies as $currency => $price) {
				if (!isset($results[$rate])) {
					$results[$rate] = new Monies();
				}
				$results[$rate] = $results[$rate]->add($price->getTax());
			}
		}
		return $results;
	}

	// May return positive or negative values.
	// We need to convert to gross here as payments will be gross only.
	public function balance($entity) {
		$result = new Prices();

		if ($entity->isDeposit()) {
			$result = $result->subtract($entity->deposit());
		} else {
			foreach ($entity->positions() as $position) {
				$result = $result->subtract($position->total());
			}
		}
		$result = $result->getGross();

		if ($entity->isFinal()) {
			foreach ($entity->finalizesDeposits() as $deposit) {
				foreach ($deposit->payments() as $payment) {
					$result = $result->add($payment->amount());
				}
			}
		}

		foreach ($entity->payments() as $payment) {
			$result = $result->add($payment->amount());
		}
		return $result;
	}

	// The worth (prices) of the invoice is the total price potentially deduced by
	// previous linked deposit invoices or if this is a deposit invoice by itself
	// the deposit value instead of the invoice value.
	public function worth($entity) {
		if ($entity->isDeposit()) {
			return (new Prices())->add($entity->deposit());
		}
		if ($entity->isFinal()) {
			$result = $entity->totals();

			foreach ($entity->finalizesDeposits() as $deposit) {
				$result = $result->subtract($deposit->deposit());
			}
			return $result;
		}
		return $entity->totals();
	}

	// Returns Monies.
	public function paid($entity) {
		$result = new Monies();

		foreach ($entity->payments() as $payment) {
			$result = $result->add($payment->amount());
		}
		return $result;
	}

	public function pay($entity, $payment) {
		if ($entity->isPaidInFull()) {
			throw new Exception("Invoice is already paid in full.");
		}
		$user = $entity->user();

		$payment->set([
			'billing_invoice_id' => $entity->id,
			'user_id' => $user->id
		]);
		return $payment->save(null, [
			'localize' => false
		]);
	}

	// Generate a payment for each currency in the open positions.
	public function payInFull($entity) {
		if ($entity->isPaidInFull()) {
			throw new Exception("Invoice is already paid in full.");
		}

		foreach ($entity->balance()->sum() as $currency => $money) {
			$payment = Payments::create([
				'method' => 'user',
				'amount_currency' => $currency,
				'amount' => $money->negate()->getAmount(),
				'date' => date('Y-m-d')
			]);
			if (!$entity->pay($payment)) {
				return false;
			}
		}
		return $entity->save([
			'status' => 'paid'
		], [
			'whitelist' => ['status']
		]);
	}

	public function isPaidInFull($entity) {
		foreach ($entity->balance()->sum() as $money) {
			if ($money->getAmount() < 0) {
				return false;
			}
		}
		return true;
	}

	public function address($entity) {
		return Addresses::createFromPrefixed('address_', $entity->data());
	}

	public function statusChange($entity, $from, $to) {
		extract(Message::aliases());

		switch ($to) {
			case 'paid':
				$user = $entity->user();
				$contact = Settings::read('contact.billing');

				if (!Settings::read('invoice.sendPaidMail') || !$user->is_notified) {
					return true;
				}
				return Mailer::deliver('invoice_paid', [
					'library' => 'billing_invoice',
					'to' => $user->email,
					'bcc' => $contact['email'],
					'subject' => $t('Invoice {:number} paid.', [
						'locale' => $user->locale,
						'scope' => 'billing_invoice',
						'number' => $entity->number
					]),
					'data' => [
						'user' => $user,
						'item' => $entity
					]
				]);
			default:
				break;
		}
		return true;
	}

	public function isCancelable($entity) {
		return in_array($entity->status, [
			'created',
			'cancelled',
			'awaiting-payment',
			'payment-error',
		]);
	}

	public function exportAsPdf($entity) {
		extract(Message::aliases());

		$stream = fopen('php://temp', 'w+');

		$user = $entity->user();
		$sender = Contacts::create(Settings::read('contact.billing'));

		$document = Libraries::locate('document', 'Invoice');
		$document = new $document();

		$titleAndSubject = $t('Invoice {:number}', [
			'number' => $entity->number,
			'locale' => $user->locale,
			'scope' => 'billing_invoice'
		]);

		$document
			->type($t('Invoice', [
				'scope' => 'billing_invoice',
				'locale' => $user->locale
			]))
			->subject($titleAndSubject)
			->entity($entity)
			->recipient($user)
			->sender($sender);

		$document->compile();

		$document
			->metaAuthor($sender->name)
			->metaTitle($titleAndSubject);

		$document->render($stream);

		rewind($stream);
		return $stream;
	}

	// Will duplicate invoice and positions, but not the payment positions. A new
	// number will be auto selected.
	public function duplicate($entity) {
		$new = static::create([
			'id' => null,
			'number' => null,  // trigger new number generation
			'created' => null,
			'modified' => null,
			'status' => 'created',
		] + $entity->data());

		if (!$new->save(null, ['localize' => false])) {
			return false;
		}
		foreach ($entity->positions() as $position) {
			$newPosition = InvoicePositions::create([
				'id' => null,
				'billing_invoice_id' => $new->id,
				'created' => null,
				'modified' => null
			] + $position->data());

			if (!$newPosition->save(null, ['localize' => false])) {
				return false;
			}
		}
		return true;
	}

	public function taxType($entity) {
		return TaxTypes::registry($entity->tax_type);
	}

	public function clientGroup($entity) {
		$user = $entity->user();

		return ClientGroups::registry(true)->first(function($item) use ($user) {
			return $item->conditions($user);
		});
	}

	/* Deposit */

	public function isDeposit($entity) {
		return (integer) $entity->deposit !== 0;
	}

	public function isFinal($entity) {
		return (boolean) $entity->finalizes;
	}

	public function deposit($entity) {
		return new Price(
			(integer) $entity->deposit,
			$entity->deposit_currency,
			$entity->deposit_type,
			(integer) $entity->deposit_rate
		);
	}

	public function finalizesDeposits($entity) {
		$results = [];

		foreach ($entity->finalizes(['serialized' => false]) as $id) {
			$result = Invoices::find('first', [
				'conditions' =>  [
					'id' => $id
				]
			]);
			if (!$result) {
				continue;
			}
			$results[] = $result;
		}
		return $results;
	}

	/* Auto invoicing */

	public static function generateFromPending($user, array $data = []) {
		$positions = InvoicePositions::pending($user);

		if (!$positions->count()) {
			return null;
		}
		$invoice = static::create($data + [
			'user_id' => $user->id,
			'tax_type' => $user->tax_type
		]);
		$invoice = $user->address('billing')->copy($invoice, 'address_');

		if (!$invoice->save()) {
			return false;
		}

		foreach ($positions as $position) {
			$result = $position->save([
				'billing_invoice_id' => $invoice->id
			], ['whitelist' => ['billing_invoice_id']]);

			if (!$result) {
				return false;
			}
		}
		return $invoice;
	}

	public static function mustAutoInvoice($user) {
		if (!$user->auto_invoice_frequency) {
			trigger_error("User `{$user->id}` has no auto invoice frequency.", E_USER_NOTICE);
			return false;
		}
		if (!$user->auto_invoiced) {
			return true;
		}
		if (!InvoicePositions::pending($user)->count()) {
			return false;
		}

		$last = DateTime::createFromFormat('Y-m-d H:i:s', $user->auto_invoiced);
		$diff = $last->diff(new DateTime());

		switch ($user->auto_invoice_frequency) {
			case 'monthly':
				return $diff->m >= 1;
			case 'yearly':
				return $diff->y >= 1;
			default:
				throw new Exception("Unsupported frequency `$user->auto_invoice_frequency`.");
		}
		return false;
	}

	public static function nextAutoInvoiceDate($user) {
		if (!$user->auto_invoice_frequency) {
			trigger_error("User `{$user->id}` has not auto invoice frequency.", E_USER_NOTICE);
			return false;
		}
		if (!$user->auto_invoiced) {
			return false;
		}
		$date = DateTime::createFromFormat('Y-m-d', $user->auto_invoiced);

		switch ($user->auto_invoice_frequency) {
			case 'monthly':
				$interval = DateInterval::createFromDateString('1 month');
				break;
			case 'yearly':
				$interval = DateInterval::createFromDateString('1 year');
			break;
			default:
				throw new Exception("Unsupported frequency `$user->auto_invoice_frequency`.");
		}
		return $date->add($interval);
	}

	public static function autoInvoice($user) {
		$invoice = static::generateFromPending($user);

		if ($invoice === null) {
			return true; // No pending positions, no invoice to send.
		}
		if ($invoice === false) {
			return false;
		}
		$message = "Auto invoiced user {$user->id} ({$user->name}) created invoice {$invoice->number}.";
		Logger::debug($message);

		return $user->save([
			'auto_invoiced' => date('Y-m-d H:i:s')
		], ['whitelist' => ['auto_invoiced']]);
	}

	public function send($entity) {
		$user = $entity->user();

		if (!$user->is_notified) {
			return;
		}
		$contact = Settings::read('contact.billing');

		$result = $entity->save(['status' => 'sent'], [
			'whitelist' => ['status'],
			'validate' => false
		]);

		return $result && Mailer::deliver('invoice_sent', [
			'library' => 'billing_invoice',
			'to' => $user->email,
			'bcc' => $contact['email'],
			'subject' => $t('Your invoice {:number}.', [
				'locale' => $user->locale,
				'scope' => 'billing_invoice',
				'number' => $invoice->number
			]),
			'data' => [
				'user' => $user,
				'item' => $entity
			],
			'attach' => [
				[
					'data' => $entity->exportAsPdf(),
					'filename' => 'invoice_' . $entity->number . '.pdf',
					'content-type' => 'application/pdf'
				]
			]
		]);
	}
}

Filters::apply(Invoices::class, 'save', function($params, $next) {
	$entity = $params['entity'];
	$data =& $params['data'];

	if (!$entity->exists()) {
		$entity->user_id = $entity->user_id ?: $data['user_id'];
		$user = $entity->user();

		$group = ClientGroups::registry(true)->first(function($item) use ($user) {
			return $item->conditions($user);
		});
		if (!$group) {
			return false;
		}
		$terms = Settings::read('invoice.terms');
		$letter = Settings::read('invoice.letter');

		$data = array_filter((array) $data) + [
			'user_id' => $user->id,
			'user_vat_reg_no' => $user->vat_reg_no,
			'tax_type' => $group->taxType()->name(),
			'tax_note' => $group->taxType()->note(),
			'date' => date('Y-m-d'),
			'status' => 'created',
			'letter' => !is_bool($letter) ? (is_callable($letter) ? $letter($user) : $letter) : null,
			'terms' => !is_bool($terms) ? (is_callable($terms) ? $terms($user) : $terms) : null
		];
		$data = $user->address('billing')->copy($data, 'address_');
	} else {
		$user = $entity->user();
	}

	if (!$result = $next($params)) {
		return false;
	}
	// Set when we last billed the user, once.
	// $user->save(['invoiced' => date('Y-m-d')], ['whitelist' => ['invoiced', 'modified']]);

	// Save nested positions.
	$new = isset($data['positions']) ? $data['positions'] : [];
	foreach ($new as $key => $value) {
		if ($key === 'new') {
			continue;
		}
		// On nested forms id is always present, but on create empty.
		if (!empty($value['id'])) {
			$item = InvoicePositions::find('first', [
				'conditions' => ['id' => $value['id']]
			]);

			if ($value['_delete']) {
				if (!$item->delete()) {
					return false;
				}
				continue;
			}
		} else {
			$item = InvoicePositions::create($value + [
				'billing_invoice_id' => $entity->id,
				'user_id' => $user->id
			]);
		}
		if (!$item->save($value)) {
			return false;
		}
	}

	// Save nested payments.
	$new = isset($data['payments']) ? $data['payments'] : [];
	foreach ($new as $key => $value) {
		if ($key === 'new') {
			continue;
		}
		if (isset($value['id'])) {
			$item = Payments::find('first', ['conditions' => ['id' => $value['id']]]);

			if ($value['_delete']) {
				if (!$item->delete()) {
					return false;
				}
				continue;
			}
		} else {
			$item = Payments::create([
				'billing_invoice_id' => $entity->id,
				'user_id' => $user->id
			]);
		}
		if (!$item->save($value)) {
			return false;
		}
	}
	return true;
});

Filters::apply(Invoices::class, 'delete', function($params, $next) {
	$entity = $params['entity'];

	if (!$result = $next($params)) {
		return $result;
	}
	$positions = InvoicePositions::find('all', [
		'conditions' => ['billing_invoice_id' => $entity->id]
	]);
	foreach ($positions as $position) {
		if (!$position->delete()) {
			return false;
		}
	}
	$payments = Payments::find('all', [
		'conditions' => ['billing_invoice_id' => $entity->id]
	]);
	foreach ($payments as $payment) {
		if (!$payment->delete()) {
			return false;
		}
	}
	return $result;
});

Invoices::init();

?>