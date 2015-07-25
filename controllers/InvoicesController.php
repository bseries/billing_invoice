<?php
/**
 * Billing Invoice
 *
 * Copyright (c) 2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace billing_invoice\controllers;

use base_core\models\Users;
use base_core\models\VirtualUsers;
use billing_core\models\Currencies;
use billing_core\models\TaxTypes;
use billing_invoice\models\Invoices;
use li3_flash_message\extensions\storage\FlashMessage;
use lithium\g11n\Message;

class InvoicesController extends \base_core\controllers\BaseController {

	use \base_core\controllers\AdminIndexTrait;
	use \base_core\controllers\AdminAddTrait;
	use \base_core\controllers\AdminEditTrait;
	use \base_core\controllers\AdminDeleteTrait;

	public function admin_export_pdf() {
		extract(Message::aliases());

		$item = Invoices::find('first', [
			'conditions' => [
				'id' => $this->request->id
			]
		]);
		$stream = $item->exportAsPdf();

		$this->_renderDownload(
			$this->_downloadBasename(
				null,
				'invoice',
				$item->number . '.pdf'
			),
			$stream
		);
		fclose($stream);
	}

	public function admin_pay_in_full() {
		extract(Message::aliases());

		$model = $this->_model;
		$model::pdo()->beginTransaction();

		$item = $model::first($this->request->id);
		$result = $item->payInFull();

		if ($result) {
			$model::pdo()->commit();
			FlashMessage::write($t('Successfully paid invoice in full.', ['scope' => 'billing_invoice']), [
				'level' => 'success'
			]);
		} else {
			$model::pdo()->rollback();
			FlashMessage::write($t('Failed to pay invoice in full.', ['scope' => 'billing_invoice']), [
				'level' => 'error'
			]);
		}
		return $this->redirect($this->request->referer());
	}

	protected function _selects($item = null) {
		extract(Message::aliases());

		$statuses = Invoices::enum('status', [
			'created' => $t('created', ['scope' => 'billing_invoice']), // open
			'sent' => $t('sent', ['scope' => 'billing_invoice']), // open
			'paid' => $t('paid', ['scope' => 'billing_invoice']),  // paid
			'cancelled' => $t('cancelled', ['scope' => 'billing_invoice']), // storno

			'awaiting-payment' => $t('awaiting payment', ['scope' => 'billing_invoice']),
			'payment-accepted' => $t('payment accepted', ['scope' => 'billing_invoice']),
			'payment-remotely-accepted' => $t('payment remotely accepted', ['scope' => 'billing_invoice']),
			'payment-error' => $t('payment error', ['scope' => 'billing_invoice']),
		]);
		$currencies = Currencies::find('list');
		$virtualUsers = [null => '-'] + VirtualUsers::find('list', ['order' => 'name']);
		$users = [null => '-'] + Users::find('list', ['order' => 'name']);

		if ($item) {
			$taxTypes = TaxTypes::find('list');
		}
		return compact('currencies', 'statuses', 'users', 'virtualUsers', 'taxTypes');
	}
}

?>