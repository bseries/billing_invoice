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

use base_core\extensions\cms\Settings;
use lithium\g11n\Message;

extract(Message::aliases());

// Invoice number format specification.
Settings::register('invoice.number', [
	'sort' => '/([0-9]{4}[0-9]{4})/',
	'extract' => '/[0-9]{4}([0-9]{4})/',
	'generate' => '%Y%%04.d'
]);

// Period of time after which an invoice is considered overdue. Set to `false` if invoices
// never get overdue. Set to a `strtotime()` compatible string (i.e. `+2 weeks`) to
// enabled this feature.
Settings::register('invoice.overdueAfter', false);

// Enable/disable sending of notification mails, when an invoice is fully paid.
Settings::register('invoice.sendPaidMail', false);

// Enable/disable auto invoicing feature. Generates invoices from pending invoice
// positions. When enabled must also be enabled on a per user basis.
Settings::register('invoice.autoInvoice', false);

// Enable/disable auto mailing invoices to recipients once one becomes available.
// This will auto send any invoice that is plain created but not sent.
Settings::register('invoice.autoSend', false);

// Enable/disable auto paying feature. When enabled must also be enabled on
// a per user basis.
Settings::register('invoice.autoPay', false);

?>