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

namespace billing_invoice\controllers;

use base_core\extensions\cms\Settings;
use base_core\extensions\net\http\NotFoundException;
use base_core\models\Mails;
use base_core\security\Gate;
use billing_core\billing\TaxTypes;
use billing_core\models\Currencies;
use billing_invoice\models\InvoicePositions;
use billing_invoice\models\Invoices;
use li3_flash_message\extensions\storage\FlashMessage;
use lithium\g11n\Message;

class InvoicesController extends \base_core\controllers\BaseController {

	use \base_core\controllers\AdminIndexTrait;
	use \base_core\controllers\AdminAddTrait;
	use \base_core\controllers\AdminEditTrait;
	use \base_core\controllers\AdminDeleteTrait;
	use \base_core\controllers\DownloadTrait;
	use \base_core\controllers\UsersTrait;

	public function admin_export_pdf() {
		$model = $this->_model;

		$item = $model::find('first', [
			'conditions' => [
				'id' => $this->request->id
			]
		]);
		if (!$item) {
			throw new NotFoundException();
		}

		$stream = $item->exportAsPdf();
		$this->_renderDownload($stream, 'application/pdf');
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

	public function admin_duplicate() {
		extract(Message::aliases());

		$model = $this->_model;
		$model::pdo()->beginTransaction();

		$item = $model::find('first', [
			'conditions' => [
				'id' => $this->request->id
			]
		]);
		if (!$item) {
			throw new NotFoundException();
		}
		$result = $item->duplicate();

		if ($result) {
			$model::pdo()->commit();
			FlashMessage::write($t('Successfully duplicated.', ['scope' => 'billing_invoice']), [
				'level' => 'success'
			]);
		} else {
			$model::pdo()->rollback();
			FlashMessage::write($t('Failed to duplicate.', ['scope' => 'billing_invoice']), [
				'level' => 'error'
			]);
		}
		return $this->redirect(['action' => 'index']);
	}

	public function admin_add_pending() {
		extract(Message::aliases());

		$model = $this->_model;
		$model::pdo()->beginTransaction();

		$item = $model::find('first', [
			'conditions' => [
				'id' => $this->request->id
			]
		]);
		if (!$item) {
			throw new NotFoundException();
		}

		$result = true;
		foreach (InvoicePositions::pending($item->user()) as $position) {
			$result = $result && $position->save([
				'billing_invoice_id' => $item->id
			], ['whitelist' => ['billing_invoice_id']]);
		}

		if ($result) {
			$model::pdo()->commit();
			FlashMessage::write($t('Successfully added pending positions.', ['scope' => 'billing_invoice']), [
				'level' => 'success'
			]);
		} else {
			$model::pdo()->rollback();
			FlashMessage::write($t('Failed to add pending positions.', ['scope' => 'billing_invoice']), [
				'level' => 'error'
			]);
		}
		return $this->redirect($this->request->referer());
	}

	public function admin_send() {
		extract(Message::aliases());

		$model = $this->_model;

		$item = $model::find('first', [
			'conditions' => [
				'id' => $this->request->id
			]
		]);

		if (!$item) {
			throw new NotFoundException();
		}
		if (!$item->isSendable()) {
			FlashMessage::write($t('Invoice cannot be sent.', ['scope' => 'billing_invoice']), [
				'level' => 'error'
			]);
			return $this->redirect($this->request->referer());
		}
		$recipientUser = $item->user();
		$senderUser = Gate::user(true);
		$letter = Settings::read('invoice.letter');

		$to = "{$recipientUser->name} <$recipientUser->email>";
		$from = "{$senderUser['name']} <{$senderUser['email']}>";
		$cc = null;
		$bcc = Settings::read('invoice.bcc');

		$subject = $t('Invoice {:number}', [
			'number' => $item->number,
			'scope' => 'billing_invoice',
			'locale' => $recipientUser->locale
		]);
		$letter = !is_bool($letter) ? (is_callable($letter) ? $letter('mail', $recipientUser, $item) : $letter) : null;

		if ($this->request->data) {
			$result = $item->send([
				'to' => $to = $this->request->data['to'],
				'from' => $from = $this->request->data['from'],
				'cc' => $cc = $this->request->data['cc'],
				'subject' => $subject = $this->request->data['subject'],
				'letter' => $letter = $this->request->data['letter']
			]);
			if (!$result) {
				FlashMessage::write($t('Failed to send invoice.', ['scope' => 'billing_invoice']), [
					'level' => 'error'
				]);
				return $this->redirect($this->request->referer());
			} else {
				FlashMessage::write($t('Invoice successfully sent.', ['scope' => 'billing_invoice']), [
					'level' => 'success'
				]);
				return $this->redirect(['action' => 'edit', 'id' => $item->id]);
			}
		}
		return compact('item', 'to', 'from', 'cc', 'bcc', 'subject', 'letter');
	}

	protected function _selects($item = null) {
		$statuses = Invoices::enum('status');
		$currencies = Currencies::find('list');

		if ($item) {
			$users = $this->_users($item, ['field' => 'user_id', 'empty' => true]);
			$taxTypes = TaxTypes::enum();
			$deposits = Invoices::find('list', [
				'conditions' => [
					'user_id' => $item->user_id,
					'deposit' => ['!=' => 0],
					'id' => ['!=' => $item->id]
				]
			]);
			$pendingPositions = InvoicePositions::pending($item->user());

			return compact('currencies', 'statuses', 'users', 'taxTypes', 'deposits', 'pendingPositions');
		}
		return compact('currencies', 'statuses');
	}
}

?>
