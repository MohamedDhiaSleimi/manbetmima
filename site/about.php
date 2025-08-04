<?php
$aboutFile = 'about.json';
$showMessage = false;

// If file doesn't exist, create it with empty content and show fallback message
if (!file_exists($aboutFile)) {
    $emptyData = ['header' => '', 'content' => ''];
    file_put_contents($aboutFile, json_encode($emptyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $showMessage = true;
} else {
    $jsonData = json_decode(file_get_contents($aboutFile), true);
    $header = trim($jsonData['header'] ?? '');
    $content = trim($jsonData['content'] ?? '');

    // Show fallback message only if both header and content are empty
    if ($header === '' && $content === '') {
        $showMessage = true;
    }
}
?>

<div class="plant-card">
  <div class="card-body">
    <?php if (!$showMessage): ?>
      <?php if (!empty($header)): ?>
        <h1 class="card-title card-text"><?= htmlspecialchars($header) ?></h1>
      <?php endif; ?>
      <?php if (!empty($content)): ?>
        <p class="card-text"><?= nl2br(htmlspecialchars($content)) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert alert-warning">
        La section "À propos" n'est pas disponible pour le moment.
      </div>
    <?php endif; ?>
  </div>
</div>
