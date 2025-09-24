<?php
$brandName = $brandName ?? 'Moni';
$appUrl = $appUrl ?? '#';
$title = $title ?? '';
$range = $range ?? '';
$links = is_array($links ?? null) ? $links : [];
?>
Hola,

Recordatorio: <?= $title ?>
<?php if ($range): ?>Fechas: <?= $range ?>
<?php endif; ?>

Para facilitarte el trámite, aquí tienes los enlaces:
<?php foreach ($links as $lk): if (!isset($lk['url'],$lk['label'])) continue; ?>- <?= $lk['label'] ?>: <?= $lk['url'] ?>
<?php endforeach; ?>

Si ya lo has presentado, puedes ignorar este mensaje.

— <?= $brandName ?>

<?= $appUrl ?>
