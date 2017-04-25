<?php

use lithium\g11n\Message;

$t = function($message, array $options = []) {
	return Message::translate($message, $options + ['scope' => 'billing_invoice', 'default' => $message]);
};

$this->set([
	'page' => [
		'type' => 'multiple',
		'object' => $t('invoices')
	]
]);

?>
<article
	class="use-rich-index"
	data-endpoint="<?= $this->url([
		'action' => 'index',
		'page' => '__PAGE__',
		'orderField' => '__ORDER_FIELD__',
		'orderDirection' => '__ORDER_DIRECTION__',
		'filter' => '__FILTER__'
	]) ?>"
>

	<div class="top-actions">
		<?= $this->html->link($t('invoice'), ['action' => 'add'], ['class' => 'button add']) ?>
	</div>

	<?php if ($data->count()): ?>
		<table>
			<thead>
				<tr>
					<td data-sort="date" class="date table-sort"><?= $t('Date') ?>
					<td data-sort="number" class="emphasize number id table-sort"><?= $t('Number') ?>
					<td data-sort="is-split" class="flag is-split"><?= $t('split?') ?>
					<td data-sort="status" class="status table-sort"><?= $t('Status') ?>
					<td data-sort="User.number" class="user table-sort"><?= $t('Recipient') ?>
					<td class="money"><?= $t('Total (net)') ?>
					<td class="money"><?= $t('Balance') ?>
					<td data-sort="modified" class="date modified table-sort desc"><?= $t('Modified') ?>
					<?php if ($useOwner): ?>
						<td class="user"><?= $t('Owner') ?>
					<?php endif ?>
					<td class="actions">
						<?= $this->form->field('search', [
							'type' => 'search',
							'label' => false,
							'placeholder' => $t('Filter'),
							'class' => 'table-search',
							'value' => $this->_request->filter
						]) ?>
			</thead>
			<tbody>
				<?php foreach ($data as $item): ?>
				<tr data-id="<?= $item->id ?>">
					<td class="date">
						<time datetime="<?= $this->date->format($item->date, 'w3c') ?>">
							<?= $this->date->format($item->date, 'date') ?>
						</time>
					<td class="emphasize number"><?= $item->number ?: '–' ?>
					<td class="flag"><i class="material-icons"><?= ($item->isDeposit() || $item->isFinal() ? 'apps' : '') ?></i>
					<td class="status"><?= $item->status ?>
					<td class="user">
						<?= $this->user->link($item->user()) ?>
					<td class="money"><?= $this->price->format($item->totals() , 'net') ?>
					<td class="money"><?= $this->money->format($item->balance()) ?>
					<td class="date modified">
						<time datetime="<?= $this->date->format($item->modified, 'w3c') ?>">
							<?= $this->date->format($item->modified, 'date') ?>
						</time>
					<?php if ($useOwner): ?>
						<td class="user">
							<?= $this->user->link($item->owner()) ?>
					<?php endif ?>
					<td class="actions">
						<?php if (!$item->isPaidInFull()): ?>
							<?= $this->html->link($t('pay in full'), ['id' => $item->id, 'action' => 'pay_in_full'], ['class' => 'button']) ?>
						<?php endif ?>
						<?= $this->html->link($t('PDF'), ['id' => $item->id, 'action' => 'export_pdf', 'library' => 'billing_invoice'], ['class' => 'button']) ?>
						<?= $this->html->link($t('open'), ['id' => $item->id, 'action' => 'edit', 'library' => 'billing_invoice'], ['class' => 'button']) ?>
				<?php endforeach ?>
			</tbody>
		</table>
	<?php else: ?>
		<div class="none-available"><?= $t('No items available, yet.') ?></div>
	<?php endif ?>

	<?=$this->_render('element', 'paging', compact('paginator'), ['library' => 'base_core']) ?>

</article>