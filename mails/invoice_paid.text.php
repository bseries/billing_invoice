Hello <?= $user->name ?>,

we've received payment for invoice #<?= $item->number ?> from
<?= $this->date->format($item->date, 'date') ?>. The new status
of the invoice is no `paid`.