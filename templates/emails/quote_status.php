<?php
$senderName = $senderName ?? 'Tu cliente';
$senderEmail = $senderEmail ?? '';
$platformName = $platformName ?? 'Moni';
$quoteNumber = $quoteNumber ?? '';
$clientName = $clientName ?? '';
$statusLabel = $statusLabel ?? '';
$statusMessage = $statusMessage ?? '';
$publicUrl = $publicUrl ?? '#';
$rejectionReason = $rejectionReason ?? '';
$actedAt = $actedAt ?? '';
$appUrl = $appUrl ?? '#';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($statusLabel) ?> presupuesto <?= htmlspecialchars($quoteNumber) ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f9fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f9fb;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:14px;box-shadow:0 6px 20px rgba(2,12,27,0.06);overflow:hidden;border:1px solid #eef2f7;">
          <tr>
            <td style="padding:18px 22px;border-bottom:1px solid #eef2f7;background:#ffffff;">
              <div style="font-weight:800;font-size:18px;color:#0f172a;letter-spacing:0.1px;"><?= htmlspecialchars($platformName) ?></div>
              <div style="margin-top:4px;font-size:12px;color:#64748b;">Actualización de presupuesto</div>
            </td>
          </tr>
          <tr>
            <td style="padding:22px 22px 8px 22px;">
              <h1 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;color:#0f172a;"><?= htmlspecialchars($statusLabel) ?>: <?= htmlspecialchars($quoteNumber) ?></h1>
              <p style="margin:0 0 14px 0;color:#334155;font-size:14px;"><?= htmlspecialchars($statusMessage) ?></p>
            </td>
          </tr>
          <tr>
            <td style="padding:0 22px 14px 22px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;border-radius:10px;border:1px solid #eef2f7;">
                <tr>
                  <td style="padding:14px 16px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                      <tr>
                        <td style="font-size:13px;color:#64748b;padding-bottom:6px;">Cliente</td>
                        <td style="font-size:13px;color:#64748b;text-align:right;padding-bottom:6px;">Fecha</td>
                      </tr>
                      <tr>
                        <td style="font-size:16px;font-weight:700;color:#0f172a;"><?= htmlspecialchars($clientName ?: 'Cliente') ?></td>
                        <td style="font-size:14px;color:#0f172a;text-align:right;"><?= htmlspecialchars($actedAt) ?></td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <?php if ($rejectionReason !== ''): ?>
          <tr>
            <td style="padding:0 22px 14px 22px;">
              <div style="padding:14px 16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;">
                <div style="font-size:12px;font-weight:700;color:#9a3412;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:6px;">Motivo indicado por el cliente</div>
                <div style="font-size:14px;color:#7c2d12;"><?= nl2br(htmlspecialchars($rejectionReason)) ?></div>
              </div>
            </td>
          </tr>
          <?php endif; ?>
          <tr>
            <td style="padding:6px 22px 18px 22px;text-align:center;">
              <a href="<?= htmlspecialchars($publicUrl) ?>" style="display:inline-block;padding:12px 28px;background:#0FA3B1;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:700;font-size:15px;">Ver presupuesto</a>
            </td>
          </tr>
          <tr>
            <td style="padding:14px 22px;border-top:1px solid #eef2f7;background:#f9fbfd;color:#64748b;font-size:12px;">
              <div>Puedes revisar el documento y seguir gestionándolo desde <a href="<?= htmlspecialchars($appUrl) ?>" style="color:#0FA3B1;text-decoration:none;"><?= htmlspecialchars($platformName) ?></a>.</div>
              <?php if ($senderEmail): ?>
                <div style="margin-top:6px;">Correo de contacto configurado: <a href="mailto:<?= htmlspecialchars($senderEmail) ?>" style="color:#0FA3B1;text-decoration:none;"><?= htmlspecialchars($senderEmail) ?></a></div>
              <?php endif; ?>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
