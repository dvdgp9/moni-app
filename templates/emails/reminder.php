<?php
// Variables esperadas: $brandName, $appUrl, $title, $range, $links (array[['label','url']])
$brandName = $brandName ?? 'Moni';
$appUrl = $appUrl ?? '#';
$title = $title ?? '';
$range = $range ?? '';
$links = is_array($links ?? null) ? $links : [];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title) ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f9fb;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f9fb;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:14px;box-shadow:0 6px 20px rgba(2,12,27,0.06);overflow:hidden;border:1px solid #eef2f7;">
          <tr>
            <td style="padding:18px 22px;border-bottom:1px solid #eef2f7;background:#ffffff;">
              <div style="font-weight:800;font-size:18px;color:#0FA3B1;letter-spacing:0.3px;"><?= htmlspecialchars($brandName) ?></div>
            </td>
          </tr>
          <tr>
            <td style="padding:22px 22px 8px 22px;">
              <h1 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;color:#0f172a;">Recordatorio: <?= htmlspecialchars($title) ?></h1>
              <?php if ($range): ?>
                <p style="margin:0 0 8px 0;color:#475569;font-size:14px;">Fechas: <strong><?= htmlspecialchars($range) ?></strong></p>
              <?php endif; ?>
              <p style="margin:8px 0 14px 0;color:#334155;font-size:14px;">Te escribimos para recordarte que ya está abierto el plazo para este trámite. Para facilitarte el proceso, te dejamos los enlaces directos:</p>
            </td>
          </tr>
          <?php if (!empty($links)): ?>
          <tr>
            <td style="padding:6px 22px 6px 22px;">
              <?php foreach ($links as $lk): if (!isset($lk['url'],$lk['label'])) continue; ?>
                <a href="<?= htmlspecialchars($lk['url']) ?>" style="display:inline-block;margin:0 6px 8px 0;padding:10px 14px;background:#0FA3B1;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:600;font-size:14px;"><?= htmlspecialchars($lk['label']) ?> →</a>
              <?php endforeach; ?>
            </td>
          </tr>
          <?php endif; ?>
          <tr>
            <td style="padding:8px 22px 18px 22px;">
              <p style="margin:8px 0 14px 0;color:#64748b;font-size:13px;">Si ya lo has presentado, puedes ignorar este mensaje.</p>
              <p style="margin:0;color:#64748b;font-size:13px;">Un saludo,<br /><?= htmlspecialchars($brandName) ?></p>
            </td>
          </tr>
          <tr>
            <td style="padding:14px 22px;border-top:1px solid #eef2f7;background:#f9fbfd;color:#64748b;font-size:12px;">
              <div>Este correo es informativo. Puedes acceder a la aplicación en <a href="<?= htmlspecialchars($appUrl) ?>" style="color:#0FA3B1;text-decoration:none;"><?= htmlspecialchars($appUrl) ?></a>.</div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
