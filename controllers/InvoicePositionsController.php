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

use base_core\models\Users;
use billing_core\models\Currencies;
use billing_core\billing\TaxType;
use billing_invoice\models\Invoices;
use billing_invoice\models\InvoicePositions;

class InvoicePositionsController extends \base_core\controllers\BaseController {

	use \base_core\controllers\AdminIndexTrait;
	use \base_core\controllers\AdminAddTrait;
	use \base_core\controllers\AdminEditTrait;
	use \base_core\controllers\AdminDeleteTrait;

	protected function _all($model, $query) {
		$query['conditions']['billing_invoice_id'] = null;
		return $model::find('all', $query);
	}

	protected function _selects($item = null) {
		$currencies = Currencies::find('list');
		$users = [null => '-'] + Users::find('list', ['order' => 'name']);

		if ($item) {
			$taxTypes = TaxType::enum();
			$invoices = Invoices::find('list', [
				'conditions' => ['user_id' => $item->user_id]
			]);
		}

		return compact('currencies', 'users', 'taxTypes', 'invoices');
	}
}

?>