<?php
// src/components/footer.php

// Tiny escape helper
if (!function_exists('e')) {
  function e($s)
  {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
  }
}


$brand = $brand ?? 'BrightSmile';
$logo_path = $logo_path ?? 'assets/icons/logo.svg';
$description = $description ?? 'BrightSmile offers gentle, modern dental care with clear guidance and advanced technology. Comfortable visits, from checkups to cosmetic treatments.';
$links = $links ?? [
  ['href' => 'index.php', 'label' => 'Home'],
  ['href' => 'appointment.php', 'label' => 'Appointment'],
  ['href' => 'doctors.php', 'label' => 'Doctors'],
  ['href' => 'services.php', 'label' => 'Services'],
  ['href' => 'about.php', 'label' => 'About'],
];

$year = date('Y');
?>

<footer class="footer">
  <div class="footer-content">
    <!-- Left Column -->
    <div class="footer-left">
      <div class="footer-header">
        <img src="<?= e($logo_path) ?>" alt="Logo" class="footer-logo" />
        <div class="brand-name"><?= e($brand) ?></div>
      </div>
      <p><?= e($description) ?></p>
      <p class="copyright">© <?= e($year) ?> <?= e($brand) ?>. All Rights Reserved.</p>
    </div>

    <!-- Right Column -->
    <div class="footer-right">
      <div class="footer-header">
        <div class="links-title">Quick Links</div>
      </div>
      <ul>
        <?php foreach ($links as $link): ?>
          <li>
            <a href="<?= e($link['href'] ?? '#') ?>"><?= e($link['label'] ?? 'Link') ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</footer>