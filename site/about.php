<?php
$aboutFile = 'about.bin'; // change extension to indicate binary
$showMessage = false;

if (!file_exists($aboutFile)) {
    $emptyData = ['header' => '', 'content' => ''];
    file_put_contents($aboutFile, serialize($emptyData)); // serialize instead of json_encode
    $showMessage = true;
} else {
    $data = unserialize(file_get_contents($aboutFile)); // unserialize instead of json_decode
    $header = trim($data['header'] ?? '');
    $content = trim($data['content'] ?? '');

    if ($header === '' && $content === '') {
        $showMessage = true;
    }
}
?>


<div class="about-section d-flex align-items-center justify-content-center min-vh-100">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9">
        <div class="glass-card p-5 animate-fade-in">
          <?php if (!$showMessage): ?>
            <?php if (!empty($header)): ?>
              <h1 class="display-4 fw-bold text-center mb-4 text-gradient">
                <?= htmlspecialchars($header) ?>
              </h1>
            <?php endif; ?>
            <?php if (!empty($content)): ?>
              <p class="fs-5 lh-lg text-light text-center">
                <?= nl2br(htmlspecialchars($content)) ?>
              </p>
            <?php endif; ?>
          <?php else: ?>
            <div class="alert alert-warning text-center fs-5 py-3 rounded-3 shadow">
              🌱 La section <strong>« À propos »</strong> n'est pas disponible pour le moment.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  /* Background: vibrant animated gradient */
  .about-section {
    background: linear-gradient(-45deg,rgb(135, 165, 136),rgb(81, 102, 82),rgb(177, 199, 178),rgb(77, 88, 77));
    background-size: 400% 400%;
    animation: gradientShift 15s ease infinite;
    padding: 2rem;
  }

  @keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
  }

  /* Glassmorphism card */
  .glass-card {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
  }

  /* Title gradient */
  .text-gradient {
    background: linear-gradient(90deg, #FFFFFF, #C8E6C9,rgb(201, 210, 202));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  /* Fade-in animation */
  .animate-fade-in {
    animation: fadeInUp 1.2s ease forwards;
    opacity: 0;
    transform: translateY(20px);
  }

  @keyframes fadeInUp {
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>
