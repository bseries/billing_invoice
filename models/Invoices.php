<?php
/**
 * Billing Core
 *
 * Copyright (c) 2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace billing_core\models;

use DateTime;
use DateInterval;
use Exception;
use AD\Finance\Price;
use AD\Finance\Price\Prices;
use AD\Finance\Money\Monies;
use lithium\g11n\Message;
use lithium\core\Libraries;
use li3_mailer\action\Mailer;

use base_core\extensions\cms\Settings;
use base_address\models\Contacts;
use base_address\models\Addresses;
use billing_core\models\Payments;
use billing_core\models\InvoicePositions;

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
		'base_core\extensions\data\behavior\User',
		'base_core\extensions\data\behavior\RelationsPlus',
		'base_core\extensions\data\behavior\Timestamp',
		'base_core\extensions\data\behavior\ReferenceNumber',
		'base_core\extensions\data\behavior\StatusChange',
		'base_core\extensions\data\behavior\Searchable' => [
			'fields' => [
				'number',
				'status',
				'date',
				'address_recipient',
				'address_organization',
				'User.number',
				'VirtualUser.number'
			]
		]
	];

	public $belongsTo = [
		'User' => [
			'to' => 'base_core\models\Users',
			'key' => 'user_id'
		],
		'VirtualUser' => [
			'to' => 'base_core\models\VirtualUsers',
			'key' => 'virtual_user_id'
		]
	];

	public $hasMany = [
		'Positions' => [
			'to' => 'billing_core\models\InvoicePositions',
			'key' => 'billing_invoice_id'
		],
		'Payments' => [
			'to' => 'billing_core\models\Payments',
			'key' => 'billing_invoice_id'
		],
	];

	public static $enum = [
		'status' => [
			'created', // open
			'paid',  // paid
			'cancelled', // storno
			'awaiting-payment',
			// 'payment-accepted',
			'payment-remotely-accepted',
			'payment-error',
			'send-scheduled',
			'sent'
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

	public function title($entity) {
		return '#' . $entity->number;
	}

	public function quantity($entity) {
		$result = preg_match('/^([0-9])\sx\s/', $entity->title, $matches);

		if (!$result) {
			return 1;
		}
		return (integer) $matches[1];
	}

	public function date($entity) {
		return DateTime::createFromFormat('Y-m-d', $entity->date);
	}

	public function isOverdue($entity) {
		$date = DateTime::createFromFormat('Y-m-d H:i:s', $entity->date);
		$overdue = Settings::read('invoice.overdueAfter');

		if (!$overdue) {
			return false;
		}
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
		$result = new Monies();

		foreach ($entity->positions() as $position) {
			$result = $result->subtract($position->total()->getGross());
		}
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
			$user->isVirtual() ? 'virtual_user_id' : 'user_id' => $user->id,
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
			// Lock invoice when its got sent.
			case 'sent':
				if ($entity->is_locked) {
					return true;
				}
				return $entity->save(['is_locked' => true], [
					'whitelist' => ['is_locked'],
					'validate' => false
				]);
			case 'paid':
				$user = $entity->user();
				$contact = Settings::read('contact.billing');

				if (!Settings::read('invoice.sendPaidMail') || !$user->is_notified) {
					return true;
				}
				return Mailer::deliver('invoice_paid', [
					'library' => 'billing_core',
					'to' => $user->email,
					'bcc' => $contact['email'],
					'subject' => $t('Invoice #{:number} paid.', [
						'locale' => $user->locale,
						'scope' => 'billing_core',
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

		$document = Libraries::locate('document', 'Invoice');
		$document = new $document();

		$sender = Contacts::create(Settings::read('contact.billing'));

		$document
			->type($t('Invoice', [
				'scope' => 'billing_core',
				'locale' => $user->locale
			]))
			->entity($entity)
			->recipient($user)
			->sender($sender)
			->subject($t('Invoice #{:number}', [
				'number' => $entity->number,
				'locale' => $user->locale,
				'scope' => 'billing_core'
			]))
			->vatRegNo(Settings::read('billing.vatRegNo'));

		if (($settings = Settings::read('service.bank.default')) && isset($settings['holder']))  {
			$document->bank($settings);
		}
		if (($settings = Settings::read('service.paypal.default')) && isset($settings['email']))  {
			$document->paypal($settings);
		}

		$document->compile();
		$document->render($stream);

		rewind($stream);
		return $stream;
	}

	/* Auto invoicing */

	public static function generateFromPending($user, array $data = []) {
		$positions = InvoicePositions::pending($user);
		$terms = Settings::read('billing.paymentTerms');

		if (!$positions->count()) {
			return true;
		}
		$invoice = static::create($data + [
			$user->isVirtual() ? 'virtual_user_id' : 'user_id' => $user->id,
			'user_vat_reg_no' => $user->vat_reg_no,
			'date' => date('Y-m-d'),
			'status' => 'created',
			// 'note' => $t('Order No.') . ': ' . $entity->number,
			'terms' => $terms($user)
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
			trigger_error("User `{$user->id}` has not auto invoice frequency.", E_USER_NOTICE);
			return false;
		}
		if (!$user->auto_invoiced) {
			return true;
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
			continue; // No pending positions, no invoice to send.
		}
		if ($invoice === false) {
			return false;
		}
		return $user->save([
			'auto_invoiced' => date('Y-m-d H:i:s')
		], ['whitelist' => ['auto_invoiced']]);
	}

	public function send($entity) {
		$user = $entity->user();
		$contact = Settings::read('contact.billing');

		if (!$user->is_notified) {
			return;
		}

		$result = $entity->save(['status' => 'sent'], [
			'whitelist' => ['status'],
			'validate' => false,
			'lockWriteThrough' => true
		]);

		return $result && Mailer::deliver('invoice_sent', [
			'library' => 'billing_core',
			'to' => $user->email,
			'bcc' => $contact['email'],
			'subject' => $t('Your invoice #{:number}.', [
				'locale' => $user->locale,
				'scope' => 'billing_core',
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

	/* deprecated */

	public function totalOutstanding($entity) {
		trigger_error('totalOutstanding() is deprecated in favor of balance().', E_USER_DEPRECATED);
		return $entity->balance();
	}

	public function totalAmount($entity) {
		throw new Exception('Replaced by totals().');
	}

	public function totalTax($entity) {
		throw new Exception('Replaced by totals().');
	}
}

Invoices::applyFilter('save', function($self, $params, $chain) {
	$params['options'] += [
		'lockWriteThrough' => false
	];
	$entity = $params['entity'];
	$data = $params['data'];
	$user = $entity->user();

	if ($entity->exists()) {
		$isLocked = Invoices::find('first', [
			'conditions' => ['id' => $entity->id],
			'fields' => ['is_locked']
		])->is_locked;
	} else { // We're creating a brandnew invoice.

		// Set when we last billed the user, once.
		// $user->save(['invoiced' => date('Y-m-d')], ['whitelist' => ['invoiced', 'modified']]);

		// Initial invoices are not locked.
		$isLocked = false;
	}

	if (!$params['options']['lockWriteThrough'] && $isLocked) {
		$params['options']['whitelist'] = (array) $params['options']['whitelist'] + ['status'];
	}
	if (!$result = $chain->next($self, $params, $chain)) {
		return false;
	}

	// Save nested positions.
	if (!empty($params['options']['lockWriteThrough']) || !$isLocked) {
		$new = isset($data['positions']) ? $data['positions'] : [];
		foreach ($new as $key => $value) {
			if ($key === 'new') {
				continue;
			}
			if (isset($value['id'])) {
				$item = InvoicePositions::find('first', ['conditions' => ['id' => $value['id']]]);

				if ($value['_delete']) {
					if (!$item->delete()) {
						return false;
					}
					continue;
				}
			} else {
				$item = InvoicePositions::create($value + [
					'billing_invoice_id' => $entity->id,
					$user->isVirtual() ? 'virtual_user_id' : 'user_id' => $user->id
				]);
			}
			if (!$item->save($value)) {
				return false;
			}
		}
	}

	// Save nested payments; alwas allow writing these.
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
				$user->isVirtual() ? 'virtual_user_id' : 'user_id' => $user->id
			]);
		}
		if (!$item->save($value)) {
			return false;
		}
	}
	return true;
});
Invoices::applyFilter('delete', function($self, $params, $chain) {
	$entity = $params['entity'];
	$result = $chain->next($self, $params, $chain);

	if ($result) {
		$positions = InvoicePositions::find('all', [
			'conditions' => ['billing_invoice_id' => $entity->id]
		]);
		foreach ($positions as $position) {
			$position->delete();
		}
	}
	return $result;
});

Invoices::init();

?>