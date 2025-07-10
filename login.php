<?php
session_start();

// Hachage SHA-256 de 'admin123'
$admin_password_hash = 'ee674bcc38e082a560bb31b0a73c7e6070191e0d80226a66198247976fe83703';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password'])) {
        $submitted_hash = hash('sha256', $_POST['password']);
        if ($submitted_hash === $admin_password_hash) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit();
        } else {
            $error = 'Mot de passe invalide. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion Admin - Catalogue de Plantes</title>
<link rel="icon" href="emoji.png" type="image/png" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}
.login-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: none;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}
</style>
</head>
<body class="d-flex align-items-center">
<div class="container">
<div class="row justify-content-center">
<div class="col-md-6 col-lg-4">
<div class="card login-card">
<div class="card-body p-5">
<div class="text-center mb-4">
<h1 class="h3 text-success">
<i class="fas fa-lock me-2"></i>Connexion Admin
</h1>
<p class="text-muted">Entrez votre mot de passe pour accéder au panneau d’administration</p>
</div>

<?php if ($error): ?>
<div class="alert alert-danger" role="alert">
<i class="fas fa-exclamation-triangle me-2"></i>
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form method="POST">
<div class="mb-3">
<label for="password" class="form-label">Mot de passe</label>
<input type="password" class="form-control" id="password" name="password" required>
</div>
<button type="submit" class="btn btn-success w-100">
<i class="fas fa-sign-in-alt me-2"></i>Se connecter
</button>
</form>

<div class="text-center mt-3">
<a href="index.html" class="text-decoration-none">
<i class="fas fa-arrow-left me-2"></i>Retour au catalogue
</a>
</div>
</div>
</div>
</div>
</div>
</div>
</body>
</html>
