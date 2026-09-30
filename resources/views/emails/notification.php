<?php
/**
 * Generic notification email (digests, reminders, event updates, submission
 * status). Everything is escaped here — payloads are built from DB data.
 *
 * @var string $appName
 * @var string $name
 * @var string $heading
 * @var string $intro
 * @var array  $events      [{title, url, when, where, price}]
 * @var array|null $cta     {label, url}
 * @var string $footerNote
 * @var string $prefsUrl
 */
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $h($heading) ?></title>
</head>
<body style="margin:0;padding:0;background:#F6EFE4;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6EFE4;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFDF9;border-radius:16px;overflow:hidden;border:1px solid #E7DCCB;">
          <tr>
            <td align="center" style="background:#F6EFE4;padding:24px 32px;border-bottom:1px solid #F2E9DC;"><img src="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? '', '/') . '/images/brand/n-events-logo.png', ENT_QUOTES, 'UTF-8') ?>" width="70" height="72" alt="N Events" style="display:block;border:0;outline:none;width:70px;height:72px;"></td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <h1 style="margin:0 0 12px;font-size:20px;font-weight:800;color:#4B2E1E;"><?= $h($heading) ?></h1>
              <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#5C4130;">Hi <?= $h($name) ?>, <?= $h($intro) ?></p>

              <?php foreach ($events ?? [] as $ev): ?>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;border:1px solid #E7DCCB;border-radius:12px;">
                  <tr>
                    <td style="padding:14px 16px;">
                      <a href="<?= $h($ev['url']) ?>" style="font-size:15px;font-weight:700;color:#4B2E1E;text-decoration:none;"><?= $h($ev['title']) ?></a>
                      <div style="font-size:13px;color:#7A5238;margin-top:4px;line-height:1.5;">
                        <?php if (!empty($ev['when'])): ?>&#128197; <?= $h($ev['when']) ?><br><?php endif; ?>
                        <?php if (!empty($ev['where'])): ?>&#128205; <?= $h($ev['where']) ?><br><?php endif; ?>
                        <?php if (!empty($ev['price'])): ?><?= $h($ev['price']) ?><?php endif; ?>
                      </div>
                    </td>
                  </tr>
                </table>
              <?php endforeach; ?>

              <?php if (!empty($cta['url'])): ?>
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0 24px;">
                  <tr>
                    <td style="border-radius:10px;background:#4B2E1E;">
                      <a href="<?= $h($cta['url']) ?>" target="_blank" rel="noopener"
                         style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:700;color:#FFFDF9;text-decoration:none;border-radius:10px;">
                        <?= $h($cta['label'] ?? 'Open') ?>
                      </a>
                    </td>
                  </tr>
                </table>
              <?php endif; ?>

              <?php if (!empty($footerNote)): ?>
                <p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:#7A5238;"><?= $h($footerNote) ?></p>
              <?php endif; ?>
              <hr style="border:none;border-top:1px solid #E7DCCB;margin:0 0 16px;">
              <p style="margin:0;font-size:12px;line-height:1.6;color:#7A5238;">
                You're receiving this because of your notification settings.
                <a href="<?= $h($prefsUrl) ?>" style="color:#4B2E1E;">Change email preferences</a>.
              </p>
            </td>
          </tr>
        </table>
        <p style="margin:16px 0 0;font-size:12px;color:#7A5238;">&copy; <?= date('Y') ?> <?= $h($appName) ?>. Proudly built in Tamil Nadu.</p>
      </td>
    </tr>
  </table>
</body>
</html>
