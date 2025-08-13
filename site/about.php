<?php
$aboutFile = 'about.json';
$showMessage = false;

if (!file_exists($aboutFile)) {
    $emptyData = ['header' => '', 'content' => ''];
    file_put_contents($aboutFile, json_encode($emptyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $showMessage = true;
} else {
    $jsonData = json_decode(file_get_contents($aboutFile), true);
    $header = trim($jsonData['header'] ?? '');
    $content = trim($jsonData['content'] ?? '');

    if ($header === '' && $content === '') {
        $showMessage = true;
    }
}
?>

<div class="container my-5">
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <?php if (!$showMessage): ?>
        <?php if (!empty($header)): ?>
          <h1 class="card-title display-5 mb-4"><?= htmlspecialchars($header) ?></h1>
        <?php endif; ?>
        <?php if (!empty($content)): ?>
          <p class="card-text fs-5">
            <?= nl2br(htmlspecialchars($content)) ?>
          </p>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert px-3 py-2 rounded">
          La section <strong>« À propos »</strong> n'est pas disponible pour le moment.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
