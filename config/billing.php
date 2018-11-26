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

// When sending out invoice mails or financial notifications, BCC i.e. the billing
// contact email.
Settings::register('invoice.bcc', null);

// Enable/disable auto invoicing feature. Generates invoices from pending invoice
// positions. When enabled must also be enabled on a per user basis.
Settings::register('invoice.autoInvoice', false);

// Enable/disable auto mailing invoices to recipients once one becomes available.
// This will auto send any invoice that is plain created but not sent.
Settings::register('invoice.autoSend', false);

// The default letter to use. Can either be `false` to disable feature, `true` to enable
// it. Provide a text string with the text or a callable which must return the text to
// enable and provide a default text.
//
// When a callable is passed, the first paramter will indiciate the context, in which the
// letter is used. This may be either `'entity'` or `'mail'`.
//
// ```
// Settings::register('...', true);
// Settings::register('...', 'foo');
// Settings::register('...', function($context, $user, $invoice) { return 'foo'; }));
// ```
Settings::register('invoice.letter', function($context, $user, $invoice) {
	$result  = "Dear {$user->name},\n";
	$result .= "\n";

	if ($context === 'mail') {
		$result .= "attached to this e-mail we’re sending you invoice {$item->number}.";
		$result .= "Please see the document for further details.";
	} else {
		$result .= "please find details on the following pages of this invoice.";
	}
	return $result;
});

// The default terms to use. Can either be `false` to disable feature, `true` to enable
// it. Provide a text string with the text or a callable which must return the text to
// enable and provide a default text.
//
// ```
// Settings::register('...', true);
// Settings::register('...', 'foo');
// Settings::register('...', function($user) { return 'foo'; }));
// ```
Settings::register('invoice.terms', false);

?>