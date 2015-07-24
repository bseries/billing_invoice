<?php

use base_core\extensions\cms\Settings;
use lithium\g11n\Message;

$t = function($message, array $options = []) {
	return Message::translate($message, $options + ['scope' => 'billing_invoice', 'default' => $message]);
};

$this->set([
	'page' => [
		'type' => 'single',
		'title' => $item->number,
		'empty' => false,
		'object' => $t('invoice')
	],
	'meta' => [
		'status' => $statuses[$item->status]
	]
]);

?>
<article>

	<?=$this->form->create($item) ?>
		<?= $this->form->field('id', ['type' => 'hidden']) ?>

		<div class="grid-row">
			<div class="grid-column-left">
				<?= $this->form->field('number', [
					'type' => 'text',
					'label' => $t('Number'),
					'class' => 'use-for-title'
				]) ?>
				<div class="help"><?= $t('Leave empty to autogenerate number.') ?></div>
			</div>
			<div class="grid-column-right">
				<?= $this->form->field('status', [
					'type' => 'select',
					'label' => $t('Status'),
					'list' => $statuses
				]) ?>
				<?php if (Settings::read('invoice.sendPaidMail')): ?>
				<div class="help">
					<?= $t('The user will be notified by e-mail when the status is changed to `paid`.') ?>
				</div>
				<?php endif ?>
				<?= $this->form->field('date', [
					'type' => 'date',
					'label' => $t('Date'),
					'value' => $item->date ?: date('Y-m-d')
				]) ?>
				<div class="help"><?= $t('Invoice date.') ?></div>
			</div>
		</div>
		<div class="grid-row">
			<h1 class="h-gamma"><?= $t('Recipient') ?></h1>

			<div class="grid-column-left">
				<?= $this->form->field('address', [
					'type' => 'textarea',
					'label' => $t('Address'),
					'disabled' => true,
					'value' => $item->address()->format('postal', $locale)
				]) ?>
			</div>
			<div class="grid-column-right">
				<div class="compound-users">
					<?php
						$user = $item->exists() ? $item->user() : false;
					?>
					<?= $this->form->field('user_id', [
						'type' => 'select',
						'label' => $t('User'),
						'list' => $users,
						'class' => !$user || !$user->isVirtual() ? null : 'hide'
					]) ?>
					<?= $this->form->field('virtual_user_id', [
						'type' => 'select',
						'label' => false,
						'list' => $virtualUsers,
						'class' => $user && $user->isVirtual() ? null : 'hide'
					]) ?>
					<?= $this->form->field('user.is_real', [
						'type' => 'checkbox',
						'label' => $t('real user'),
						'checked' => $user ? !$user->isVirtual() : true
					]) ?>
				</div>
			</div>
		</div>

		<div class="grid-row">
			<h1 class="h-gamma"><?= $t('Positions') ?></h1>
			<section class="use-nested">
				<table>
					<thead>
						<tr>
							<td class="position-description--f"><?= $t('Description') ?>
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
									'type' => 'text',
									'label' => false,
									'value' => $child->description,
									'list' => $positionDescriptions
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
								'type' => 'text',
								'label' => false,
								'list' => $positionDescriptions
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
								'value' => $item->exists() ? $item->clientGroup()->amountCurrency : 'EUR',
								'list' => $currencies
							]) ?>
						<td class="price-type--f">
							<?= $this->form->field("positions.new.amount_type", [
								'type' => 'select',
								'label' => false,
								'value' => $item->exists() ? $item->clientGroup()->amountType : 'net',
								'list' => ['net' => $t('net'), 'gross' => $t('gross')]
							]) ?>
						<td class="money--f price-amount--f">
							<?= $this->form->field('positions.new.amount', [
								'type' => 'text',
								'label' => false,
								'class' => 'input--money'
							]) ?>
						<td class="numeric--f price-rate--f">
							<?= $this->form->field("positions.new.amount_rate", [
								'type' => 'text',
								'value' => $item->exists() ? $item->taxType()->rate : '19',
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
								<?= $this->form->button($t('add position'), ['type' => 'button', 'class' => 'button add-nested']) ?>
						<tr class="totals">
							<td colspan="6"><?= $t('Total (net)') ?>
							<td><?= $this->money->format($item->totals()->getNet()) ?>

						<?php foreach ($item->taxes() as $rate => $tax): ?>
						<tr class="totals">
							<td colspan="6"><?= $t('Tax ({:rate}%)', ['rate' => $rate]) ?>
							<td><?= $this->money->format($tax) ?>
						<?php endforeach ?>

						<tr class="totals">
							<td colspan="6"><?= $t('Total (gross)') ?>
							<td><?= $this->money->format($item->totals()->getGross()) ?>
					</tfoot>
				</table>
			</section>
		</div>

		<div class="grid-row">
			<section class="grid-column-left">
				<?= $this->form->field('terms', [
					'type' => 'textarea',
					'label' => $t('Terms')
				]) ?>
				<div class="help"><?= $t('Visible to recipient.') ?></div>
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
			<h1 class="h-gamma"><?= $t('Tax') ?></h1>

			<section class="grid-column-right">
				<?= $this->form->field('tax_type', [
					'type' => 'select',
					'list' => $taxTypes,
					'disabled' => true
				]) ?>
				<?= $this->form->field('tax_note', [
					'type' => 'text',
					'label' => $t('Tax note'),
					'value' => $item->tax_note,
					'disabled' => true
				]) ?>
				<div class="help">
					<?= $t('Visible to recipient.') ?>
					<?= $t('Automatically generated.') ?>
				</div>
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
							<td>
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
									'class' => 'input--money'
								]) ?>
							<td class="actions">
								<?= $this->form->button($t('delete'), ['class' => 'button delete delete-nested']) ?>
					</tbody>
					<tfoot>
						<tr class="nested-add-action">
							<td colspan="5">
								<?= $this->form->button($t('add payment'), ['type' => 'button', 'class' => 'button add-nested']) ?>
						<tr class="totals">
							<td colspan="3"><?= $t('Total') ?>
							<td><?= $this->money->format($item->paid()) ?>
						<tr class="totals">
							<td colspan="3"><?= $t('Balance') ?>
							<td><?= $this->money->format($item->balance()) ?>
					</tfoot>
				</table>
			</section>
		</div>

		<div class="bottom-actions">
			<?php if ($item->exists()): ?>
				<?= $this->html->link($t('delete'), [
					'action' => 'delete',
					'id' => $item->id,
				], [
					'class' => 'button delete large'
				]) ?>

				<?= $this->html->link($t('PDF'), [
					'controller' => 'Invoices',
					'id' => $item->id, 'action' => 'export_pdf',
					'library' => 'billing_invoice'
				], ['class' => 'button large']) ?>

				<?php if (!$item->isPaidInFull()): ?>
					<?= $this->html->link($t('pay in full'), ['id' => $item->id, 'action' => 'pay_in_full'], ['class' => 'button large']) ?>
				<?php endif ?>
			<?php endif ?>

			<?= $this->form->button($t('save'), ['type' => 'submit', 'class' => 'save large']) ?>
		</div>

	<?=$this->form->end() ?>
</article>