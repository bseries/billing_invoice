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

use billing_core\models\Currencies;
use billing_core\billing\TaxTypes;
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

	public function admin_duplicate() {
		extract(Message::aliases());

		$model = $this->_model;
		$model::pdo()->beginTransaction();

		$item = $model::first($this->request->id);
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
			return compact('currencies', 'statuses', 'users', 'taxTypes', 'deposits');
		}
		return compact('currencies', 'statuses');
	}
}

?>