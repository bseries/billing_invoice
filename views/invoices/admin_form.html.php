<?php

use base_core\extensions\cms\Settings;
use lithium\g11n\Message;

$t = function($message, array $options = []) {
	return Message::translate($message, $options + ['scope' => 'billing_invoice', 'default' => $message]);
};

$tn = function($message1, $message2, $count, array $options = []) {
	return Message::translate($message1, $options + compact('count') + [
		'default' => $count === 1 ? $message1 : $message2,
		'scope' => 'billing_invoice'
	]);
};

$this->set([
	'page' => [
		'type' => 'single',
		'title' => $item->number,
		'empty' => false,
		'object' => $t('invoice')
	],
	'meta' => [
		'status' => $statuses[$item->status],
		'overdue' => $item->isOverdue() ? $t('overdue') : null,
		'deposit' => $item->isDeposit() ? $t('deposit') : null,
		'final' => $item->isFinal() ? $t('final') : null
	]
]);

?>
<article>
	<?=$this->form->create($item) ?>
		<?php if ($item->exists()): ?>
			<?= $this->form->field('id', ['type' => 'hidden']) ?>
		<?php endif ?>

		<?php if ($useOwner): ?>
			<div class="grid-row">
				<h1><?= $t('Access') ?></h1>

				<div class="grid-column-left"></div>
				<div class="grid-column-right">
					<?= $this->form->field('owner_id', [
						'type' => 'select',
						'label' => $t('Owner'),
						'list' => $users
					]) ?>
				</div>
			</div>
		<?php endif ?>
		<?php if ($useSites): ?>
			<div class="grid-row">
				<h1><?= $t('Sites') ?></h1>

				<div class="grid-column-left"></div>
				<div class="grid-column-right">
					<?= $this->form->field('site', [
						'type' => 'select',
						'label' => $t('Site'),
						'list' => $sites
					]) ?>
				</div>
			</div>
		<?php endif ?>


		<div class="grid-row">
			<div class="grid-column-left">
				<?= $this->form->field('number', [
					'type' => 'text',
					'label' => $t('Number'),
					'class' => 'use-for-title',
					'placeholder' => $autoNumber ? $t('Will autogenerate number.') : null,
					'disabled' => $autoNumber && !$item->exists(),
					'readonly' => $autoNumber || $item->exists()
				]) ?>
				<div class="help">
					<?= $t('The reference number uniquely identifies this item and is used especially in correspondence with clients and customers.') ?>
				</div>
			</div>
			<div class="grid-column-right">
				<?= $this->form->field('status', [
					'type' => 'select',
					'label' => $t('Status'),
					'list' => $statuses
				]) ?>
				<?php if (Settings::read('invoice.sendPaidMail')): ?>
				<div class="help">
					<?= $t('The user will be notified by e-mail when the status is changed to “paid”.') ?>
				</div>
				<?php endif ?>
				<?= $this->form->field('date', [
					'type' => 'date',
					'label' => $t('Date'),
					'value' => $item->date ?: date('Y-m-d')
				]) ?>
			</div>
		</div>

		<div class="grid-row">
			<h1 class="h-gamma"><?= $t('User') ?> / <?= $t('Recipient') ?></h1>
			<div class="grid-column-left">
				<?= $this->form->field('address', [
					'type' => 'textarea',
					'label' => $t('Receiving Address'),
					'readonly' => true,
					'value' => $item->address()->format('postal', $locale),
					'placeholder' => $t('Automatically uses address assigned to user.')
				]) ?>
			</div>
			<?php if (!$item->exists()): ?>
			<div class="grid-column-right">
				<?= $this->form->field('user_id', [
					'type' => 'select',
					'label' => $t('User'),
					'list' => $users,
				]) ?>
			</div>
			<?php elseif ($user = $item->user()): ?>
			<div class="grid-column-right">
				<?= $this->form->field('user.number', [
					'label' => $t('Number'),
					'readonly' => true,
					'value' => $user->number
				]) ?>
				<?= $this->form->field('user.name', [
					'label' => $t('Name'),
					'readonly' => true,
					'value' => $user->name
				]) ?>
				<?= $this->form->field('user.email', [
					'label' => $t('Email'),
					'readonly' => true,
					'value' => $user->email
				]) ?>
			</div>
			<div class="actions">
				<?= $this->html->link($t('open user'), [
					'controller' => 'Users',
					'action' => 'edit',
					'id' => $user->id,
					'library' => 'base_core'
				], ['class' => 'button']) ?>
			</div>
			<?php endif ?>
		</div>

		<?php if (Settings::read('invoice.letter')): ?>
			<div class="grid-row">
				<?= $this->form->field('letter', [
					'type' => 'textarea',
					'label' => $t('Letter'),
					'class' => 'textarea-size--gamma',
					'placeholder' => Settings::read('invoice.letter') !== true ? $t('Leave empty to use default letter.') : null
				]) ?>
			</div>
		<?php endif ?>

		<div class="grid-row">
			<section class="grid-column-left">
				<?php if (Settings::read('invoice.terms') !== false): ?>
					<?= $this->form->field('terms', [
						'type' => 'textarea',
						'label' => $t('Terms'),
						'placeholder' => Settings::read('invoice.terms') !== true ? $t('Leave empty to use default terms.') : null
					]) ?>
				<?php endif ?>
			</section>
			<section class="grid-column-right">
				<?= $this->form->field('note', [
					'type' => 'textarea',
					'label' => $t('Note')
				]) ?>
				<div class="help"><?= $t('Visible to recipient.') ?></div>
			</section>
		</div>

		<div class="grid-row">
			<h1 class="h-gamma"><?= $t('Positions') ?></h1>
			<section class="use-nested">
				<table>
					<thead>
						<tr>
							<td class="position-description--f">
							<td class="numeric--f quantity--f"><?= $t('Quantity') ?>
							<td class="currency--f"><?= $t('Currency') ?>
							<td class="price-type--f"><?= $t('Type') ?>
							<td class="money--f price-amount--f"><?= $t('Unit price') ?>
							<td class="numeric--f price-rate--f"><?= $t('Tax rate (%)') ?>
							<td class="money--f position-total--f"><?= $t('Total (net)') ?>
							<td class="actions">
					</thead>
					<tbody>
					<?php foreach ($item->positions() as $key => $child): ?>
						<tr class="nested-item">
							<td class="position-description--f">
								<?= $this->form->field("positions.{$key}.id", [
									'type' => 'hidden',
									'value' => $child->id
								]) ?>
								<?= $this->form->field("positions.{$key}._delete", [
									'type' => 'hidden'
								]) ?>
								<?= $this->form->field("positions.{$key}.description", [
									'type' => 'textarea',
									'label' => false,
									'value' => $child->description,
									'placeholder' => $t('Description'),
									'maxlength' => 250
								]) ?>
								<?= $this->form->field("positions.{$key}.tags", [
									'type' => 'text',
									'label' => false,
									'value' => $child->tags(),
									'placeholder' => $t('Tags'),
									'class' => 'input--tags'
								]) ?>
							<td class="numeric--f quantity--f">
								<?= $this->form->field("positions.{$key}.quantity", [
									'type' => 'text',
									'label' => false,
									'value' => $this->number->format($child->quantity, 'decimal'),
									'class' => 'input--numeric'
								]) ?>
							<td class="currency--f">
								<?= $this->form->field("positions.{$key}.amount_currency", [
									'type' => 'select',
									'label' => false,
									'list' => $currencies,
									'value' => $child->amount_currency
								]) ?>
							<td class="price-type--f">
								<?= $this->form->field("positions.{$key}.amount_type", [
									'type' => 'select',
									'label' => false,
									'value' => $child->amount_type,
									'list' => ['net' => $t('net'), 'gross' => $t('gross')]
								]) ?>
							<td class="money--f price-amount--f">
								<?= $this->form->field("positions.{$key}.amount", [
									'type' => 'text',
									'label' => false,
									'value' => $this->money->format($child->amount, ['currency' => false]),
									'placeholder' => $this->money->format(0, ['currency' => false]),
									'class' => 'input--money'
								]) ?>
							<td class="numeric--f price-rate--f">
								<?= $this->form->field("positions.{$key}.amount_rate", [
									'type' => 'text',
									'label' => false,
									'value' => $child->amount_rate,
									'class' => 'input--numeric'
								]) ?>
							<td class="money--f position-total--f">
								<?= $this->money->format($child->total()->getNet()) ?>
							<td class="actions">
								<?= $this->form->button($t('delete'), ['class' => 'button delete delete-nested']) ?>
					<?php endforeach ?>
					<tr class="nested-add nested-item">
						<td class="position-description--f">
							<?= $this->form->field('positions.new.description', [
								'type' => 'textarea',
								'label' => false,
								'placeholder' => $t('Description'),
								'maxlength' => 250
							]) ?>
							<?= $this->form->field("positions.new.tags", [
								'type' => 'text',
								'label' => false,
								'placeholder' => $t('Tags'),
								'class' => 'input--tags'
							]) ?>
						<td class="numeric--f quantity--f">
							<?= $this->form->field('positions.new.quantity', [
								'type' => 'text',
								'value' => 1,
								'label' => false,
								'class' => 'input--numeric'
							]) ?>
						<td class="currency--f">
							<?= $this->form->field("positions.new.amount_currency", [
								'type' => 'select',
								'label' => false,
								'value' => $item->exists() ? $item->clientGroup()->amountCurrency() : 'EUR',
								'list' => $currencies
							]) ?>
						<td class="price-type--f">
							<?= $this->form->field("positions.new.amount_type", [
								'type' => 'select',
								'label' => false,
								'value' => $item->exists() ? $item->clientGroup()->amountType() : 'net',
								'list' => ['net' => $t('net'), 'gross' => $t('gross')]
							]) ?>
						<td class="money--f price-amount--f">
							<?= $this->form->field('positions.new.amount', [
								'type' => 'text',
								'label' => false,
								'placeholder' => $this->money->format(0, ['currency' => false]),
								'class' => 'input--money'
							]) ?>
						<td class="numeric--f price-rate--f">
							<?= $this->form->field("positions.new.amount_rate", [
								'type' => 'text',
								'value' => $item->exists() ? $item->taxType()->rate() : '19',
								'label' => false,
								'class' => 'input--numeric'
							]) ?>
						<td class="position-total--f">
						<td class="actions">
							<?= $this->form->button($t('delete'), ['class' => 'button delete delete-nested']) ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="9" class="nested-add-action">
								<?= $this->form->button($t('add position'), [
									'type' => 'button',
									'class' => 'button add-nested'
								]) ?>
								<?php if ($pendingPositions->count()): ?>
									<?= $this->html->link($tn('add {:count} pending position', 'add {:count} pending positions', $pendingPositions->count()),
										['action' => 'add_pending', 'id' => $item->id],
										['class' => 'button']
									) ?>
								<?php endif ?>
						<?php if ($item->positions()->count()): ?>
							<tr class="totals totals--subtotal">
								<td colspan="6"><?= $t('Total (net)') ?>
								<td colspan="1"><?= $this->money->format($item->totals()->getNet()) ?>

							<?php foreach ($item->taxes() as $rate => $tax): ?>
							<tr class="totals">
								<td colspan="6"><?= $t('Tax ({:rate}%)', ['rate' => $rate]) ?>
								<td colspan="1"><?= $this->money->format($tax) ?>
							<?php endforeach ?>

							<tr class="totals totals--grandtotal">
								<td colspan="6"><?= $t('Total (gross)') ?>
								<td colspan="1"><?= $this->money->format($item->totals()->getGross()) ?>
						<?php endif ?>
					</tfoot>
				</table>
			</section>
		</div>

		<div class="grid-row">
			<h1 class="h-gamma"><?= $t('Payments') ?></h1>
			<section class="use-nested">
				<table>
					<thead>
						<tr>
							<td><?= $t('Date') ?>
							<td><?= $t('Method') ?>
							<td><?= $t('Currency') ?>
							<td class="money--f"><?= $t('Amount') ?>
							<td class="actions">
					</thead>
					<tbody>
					<?php foreach ($item->payments() as $key => $child): ?>
						<tr class="nested-item">
							<td>
								<?= $this->form->field("payments.{$key}.id", [
									'type' => 'hidden',
									'value' => $child->id
								]) ?>
								<?= $this->form->field("payments.{$key}._delete", [
									'type' => 'hidden'
								]) ?>
								<?= $this->form->field("payments.{$key}.date", [
									'type' => 'date',
									'label' => false,
									'value' => $child->date
								]) ?>
							<td>
								<?= $this->form->field("payments.{$key}.method", [
									'type' => 'text',
									'label' => false,
									'value' => $child->method
								]) ?>
							<td>
								<?= $this->form->field("payments.{$key}.amount_currency", [
									'type' => 'select',
									'label' => false,
									'list' => $currencies,
									'value' => $child->amount_currency
								]) ?>
							<td class="money--f">
								<?= $this->form->field("payments.{$key}.amount", [
									'type' => 'text',
									'label' => false,
									'value' => $this->money->format($child->amount(), ['currency' => false]),
									'placeholder' => $this->money->format(0, ['currency' => false]),
									'class' => 'input--money'
								]) ?>
							<td class="actions">
								<?= $this->form->button($t('delete'), ['class' => 'button delete delete-nested']) ?>
					<?php endforeach ?>
						<tr class="nested-add nested-item">
							<td>
								<?= $this->form->field("payments.new.date", [
									'type' => 'date',
									'label' => false,
									'value' => date('Y-m-d')
								]) ?>
							<td>
								<?= $this->form->field("payments.new.method", [
									'type' => 'text',
									'label' => false
								]) ?>
							<td>
								<?= $this->form->field("payments.new.amount_currency", [
									'type' => 'select',
									'label' => false,
									'list' => $currencies
								]) ?>
							<td class="money--f">
								<?= $this->form->field("payments.new.amount", [
									'type' => 'text',
									'label' => false,
									'placeholder' => $this->money->format(0, ['currency' => false]),
									'class' => 'input--money'
								]) ?>
							<td class="actions">
								<?= $this->form->button($t('delete'), ['class' => 'button delete delete-nested']) ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="5" class="nested-add-action">
								<?= $this->form->button($t('add payment'), ['type' => 'button', 'class' => 'button add-nested']) ?>
						<?php if ($item->payments()->count()): ?>
							<tr class="totals totals--subtotal">
								<td colspan="3"><?= $t('Total') ?>
								<td colspan="2"><?= $this->money->format($item->paid()) ?>
							<tr class="totals totals--grandtotal">
								<td colspan="3"><?= $t('Balance') ?>
								<td colspan="2"><?= $this->money->format($item->balance()) ?>
						<?php endif ?>
					</tfoot>
				</table>
			</section>
		</div>

		<div class="grid-row">
			<h1 class="h-gamma"><?= $t('Deposit') ?></h1>
			<div class="section-help">
				<?= $t('Deposit invoices allow to split a larger invoice into smaller parts.') ?>
				<?= $t('To turn this invoice into a deposit invoice, provide a deposit amount on the right side.') ?>
				<?= $t('If this is the final invoice of previous deposit invoices, select these invoices on the left side.') ?>
			</div>
			<div class="grid-column-left">
				<?= $this->form->field('finalizes', [
					'type' => 'select',
					'multiple' => true,
					'label' => $t('Finalizes these deposit invoices…'),
					'value' => $item->finalizes(['serialized' => false]),
					'list' => $deposits
				]) ?>
				<div class="help">
					<?= $t('A selection of deposit invoices which this invoice finalizes.') ?>
					<?= $t('Only applicable if this invoice is not a deposit invoice by itself.') ?>
				</div>
			</div>
			<div class="grid-column-right">
				<?= $this->form->field('deposit_currency', [
					'type' => 'select',
					'label' => $t('Amount currency'),
					'value' => $item->exists() && $item->isDeposit() ? $item->deposit_currency : 'EUR',
					'list' => $currencies
				]) ?>
				<?= $this->form->field('deposit_type', [
					'type' => 'select',
					'label' => $t('Amount type'),
					'value' => $item->exists() && $item->isDeposit() ? $item->deposit_type : 'net',
					'list' => ['net' => $t('net'), 'gross' => $t('gross')]
				]) ?>
				<?= $this->form->field('deposit', [
					'type' => 'text',
					'label' => $t('Amount'),
					'placeholder' => $this->money->format(0, ['currency' => false]),
					'value' => $item->exists() && $item->isDeposit() ? $this->money->format($item->deposit(), ['currency' => false]) : null,
					'class' => 'input--money'
				]) ?>
				<div class="help">
					<?= $t('Provide an amount to turn this invoice into a deposit invoice.') ?>
					<?= $t('Remove the amount to turn it back into a regular invoice again.') ?>
				</div>
				<?= $this->form->field('deposit_rate', [
					'type' => 'text',
					'label' => $t('Tax rate (%)'),
					'value' => $item->exists() && $item->isDeposit() ? $item->deposit_rate : '19',
					'class' => 'input--numeric'
				]) ?>
			</div>
		</div>

		<div class="bottom-actions">
			<div class="bottom-actions__left">
				<?php if ($item->exists()): ?>
					<?= $this->html->link($t('delete'), [
						'action' => 'delete', 'id' => $item->id
					], ['class' => 'button large delete']) ?>
				<?php endif ?>
			</div>
			<div class="bottom-actions__right">
				<?php if ($item->exists()): ?>
					<?php if ($item->isSendable()): ?>
						<?= $this->html->link($item->status === 'sent' ? $t('prepare to resend e-mail') : $t('prepare e-mail'), [
							'action' => 'send', 'id' => $item->id
						], ['class' => 'button large']) ?>
					<?php endif ?>
					<?= $this->html->link($t('duplicate'), [
						'controller' => 'Invoices',
						'id' => $item->id, 'action' => 'duplicate',
					], ['class' => 'button large']) ?>
					<?= $this->html->link($t('PDF'), [
						'id' => $item->id, 'action' => 'export_pdf', 'library' => 'billing_invoice'
					], ['class' => 'button large', 'download' => "invoice_{$item->number}.pdf"]) ?>
				<?php endif ?>
				<?php if (!$item->isPaidInFull()): ?>
					<?= $this->html->link($t('pay in full'), ['id' => $item->id, 'action' => 'pay_in_full'], ['class' => 'button large']) ?>
				<?php endif ?>
				<?= $this->form->button($t('save'), [
					'type' => 'submit',
					'class' => 'button large save'
				]) ?>
			</div>
		</div>

	<?=$this->form->end() ?>
</article>
