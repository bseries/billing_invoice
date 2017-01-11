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

namespace billing_invoice\controllers;

use billing_core\models\Currencies;
use billing_core\billing\TaxTypes;
use billing_invoice\models\Invoices;
use billing_invoice\models\InvoicePositions;

class InvoicePositionsController extends \base_core\controllers\BaseController {

	use \base_core\controllers\AdminIndexTrait;
	use \base_core\controllers\AdminAddTrait;
	use \base_core\controllers\AdminEditTrait;
	use \base_core\controllers\AdminDeleteTrait;
	use \base_core\controllers\UsersTrait;

	protected function _all($model, array $query) {
		$query['conditions']['billing_invoice_id'] = null;
		return $model::find('all', $query);
	}

	protected function _paginate($model, array $query) {
		$query['conditions']['billing_invoice_id'] = null;
		return $model::find('count', $query);
	}

	protected function _selects($item = null) {
		$currencies = Currencies::find('list');

		if ($item) {
			$users = $this->_users($item, ['field' => 'user_id', 'empty' => true]);
			$taxTypes = TaxTypes::enum();

			// Prevent user from mistakenly assigning positions to an already "closed"
			// invoice. We still must allow the current invoice (if assigned to) for
			// display purposes.
			$invoices = Invoices::find('list', [
				'conditions' => [
					'user_id' => $item->user_id,
					'OR' => [
						'id' => $item->billing_invoice_id,
						'status' => ['created', 'draft']
					]
				],
				'order' => ['number' => 'DESC']
			]);
			return compact('currencies', 'users', 'taxTypes', 'invoices');
		}
		return compact('currencies');
	}
}

?>