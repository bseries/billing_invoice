<?php

use lithium\g11n\Message;

$t = function($message, array $options = []) {
	return Message::translate($message, $options + ['scope' => 'billing_invoice', 'default' => $message]);
};

$this->set([
	'page' => [
		'type' => 'single',
		'title' => $item->number,
		'action' => $t('send'),
		'empty' => false,
		'object' => $t('invoice')
	]
]);

?>
<article>
	<?= $this->form->create(null) ?>
		<div class="grid-row">
			<div class="grid-column-left">
				<?= $this->form->field('from', [
					'type' => 'text',
					'label' => $t('From'),
					'value' => $from
				]) ?>
			</div>
			<div class="grid-column-right"></div>
		</div>

		<div class="grid-row">
			<div class="grid-column-left">
				<?= $this->form->field('to', [
					'type' => 'text',
					'label' => $t('To'),
					'value' => $to
				]) ?>
				<?= $this->form->field('cc', [
					'type' => 'text',
					'label' => $t('CC'),
					'value' => $cc
				]) ?>
				<?php if ($bcc): ?>
				<div class="help">
					<?= $t("The e-mail will additionally be BCC'ed to {:email}", ['email' => $bcc]) ?>
				</div>
				<?php endif ?>

			</div>
			<div class="grid-column-right"></div>
		</div>

		<div class="grid-row">
			<div class="grid-column-left">
				<?= $this->form->field('subject', [
					'type' => 'text',
					'label' => $t('Subject'),
					'value' => $subject
				]) ?>
			</div>
			<div class="grid-column-right"></div>
		</div>

		<div class="grid-row">
			<?= $this->form->field('letter', [
				'type' => 'textarea',
				'label' => $t('Letter'),
				'class' => 'textarea-size--beta',
				'value' => $letter
			]) ?>
		</div>

		<div class="bottom-actions">
			<div class="bottom-actions__left"></div>
			<div class="bottom-actions__right">
				<?= $this->html->link($t('cancel'), [
					'action' => 'edit', 'id' => $item->id
				], ['class' => 'button large']) ?>
				<?= $this->form->button($t('send e-mail'), [
					'type' => 'submit',
					'class' => 'button large save'
				]) ?>
			</div>
		</div>

	<?= $this->form->end() ?>
</article>
