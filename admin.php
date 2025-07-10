<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$catalogue_file = 'catalogue.json';
$catalogue = [];

$categories_file = 'categories.json';
$default_categories = ["Succulents", "Indoor Plants", "Outdoor Plants", "Herbs", "Flowers"];
$categories = $default_categories;

// Load catalogue
if (file_exists($catalogue_file)) {
    $json_content = file_get_contents($catalogue_file);
    $catalogue = json_decode($json_content, true);
    if ($catalogue === null) {
        $catalogue = [];
    }
}

// Load categories from file if exists, else create it with default categories
if (file_exists($categories_file)) {
    $json_cat = file_get_contents($categories_file);
    $loaded_categories = json_decode($json_cat, true);
    if (is_array($loaded_categories)) {
        $categories = $loaded_categories;
    }
} else {
    // Create categories.json with default categories initially
    file_put_contents($categories_file, json_encode($default_categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_plant':
                $new_plant = [
                    'name' => trim($_POST['name']),
                    'price' => trim($_POST['price']),
                    'category' => trim($_POST['category']),
                    'image' => trim($_POST['image']),
                    'description' => trim($_POST['description'])
                ];

                if (empty($new_plant['name']) || empty($new_plant['price']) || empty($new_plant['description'])) {
                    $error = 'Veuillez remplir tous les champs obligatoires.';
                } else {
                    $catalogue[] = $new_plant;
                    if (file_put_contents($catalogue_file, json_encode($catalogue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $message = 'Plante ajoutée avec succès !';
                    } else {
                        $error = 'Erreur lors de l’enregistrement. Vérifiez les permissions du fichier.';
                    }
                }
                break;

            case 'delete_plant':
                $index = (int)$_POST['index'];
                if (isset($catalogue[$index])) {
                    unset($catalogue[$index]);
                    $catalogue = array_values($catalogue);
                    if (file_put_contents($catalogue_file, json_encode($catalogue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $message = 'Plante supprimée avec succès !';
                    } else {
                        $error = 'Erreur lors de la suppression. Vérifiez les permissions du fichier.';
                    }
                }
                break;

            case 'add_category':
                $new_category = trim($_POST['new_category'] ?? '');
                if (empty($new_category)) {
                    $error = 'Le nom de la catégorie ne peut pas être vide.';
                } elseif (in_array($new_category, $categories)) {
                    $error = 'Cette catégorie existe déjà.';
                } else {
                    $categories[] = $new_category;
                    if (file_put_contents($categories_file, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $message = "Catégorie '{$new_category}' ajoutée avec succès !";
                    } else {
                        $error = "Erreur lors de l'enregistrement de la catégorie.";
                    }
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panneau d'administration - Catalogue de Plantes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="container py-4">
    <!-- En-tête -->
    <div class="card admin-card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h1 class="h2 text-success mb-0">
                <i class="fas fa-cog me-2"></i>Panneau d'administration
            </h1>
            <div>
                <a href="index.html" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-eye me-2"></i>Voir le site
                </a>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                </a>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Ajouter une plante -->
    <div class="card admin-card mb-4">
        <div class="card-header">
            <h3 class="h5 mb-0">
                <i class="fas fa-plus me-2"></i>Ajouter une plante
            </h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_plant" />
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom de la plante *</label>
                            <input type="text" class="form-control" id="name" name="name" required />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="price" class="form-label">Prix *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="category" class="form-label">Catégorie</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="image" class="form-label">URL de l'image</label>
                            <input type="url" class="form-control" id="image" name="image" placeholder="https://exemple.com/image.jpg" />
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description *</label>
                    <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Ajouter
                </button>
            </form>
        </div>
    </div>

    <!-- Ajouter une catégorie -->
    <div class="card admin-card mb-4">
        <div class="card-header">
            <h3 class="h5 mb-0">
                <i class="fas fa-folder-plus me-2"></i>Ajouter une catégorie
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-center">
                <input type="hidden" name="action" value="add_category" />
                <div class="col-auto flex-grow-1">
                    <input type="text" name="new_category" class="form-control" placeholder="Nom de la nouvelle catégorie" required />
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Ajouter la catégorie
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Plantes actuelles -->
    <div class="card admin-card">
        <div class="card-header">
            <h3 class="h5 mb-0">
                <i class="fas fa-list me-2"></i>Plantes enregistrées (<?php echo count($catalogue); ?>)
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($catalogue)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-seedling fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucune plante dans le catalogue</h5>
                    <p class="text-muted">Ajoutez votre première plante via le formulaire ci-dessus</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($catalogue as $index => $plant): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card">
                                <img src="<?php echo htmlspecialchars($plant['image']); ?>" 
                                     class="card-img-top" style="height: 150px; object-fit: cover;"
                                     alt="<?php echo htmlspecialchars($plant['name']); ?>"
                                     onerror="this.src='https://via.placeholder.com/300x150/2d5a27/ffffff?text=Plante'">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($plant['name']); ?></h6>
                                    <p class="card-text small text-muted">
                                        <strong>Prix :</strong> $<?php echo htmlspecialchars($plant['price']); ?><br>
                                        <strong>Catégorie :</strong> <?php echo htmlspecialchars($plant['category'] ?? 'N/A'); ?>
                                    </p>
                                    <p class="card-text small">
                                        <?php echo htmlspecialchars(substr($plant['description'], 0, 60)) . '...'; ?>
                                    </p>
                                    <form method="POST" class="d-inline" 
                                          onsubmit="return confirm('Voulez-vous vraiment supprimer cette plante ?')">
                                        <input type="hidden" name="action" value="delete_plant" />
                                        <input type="hidden" name="index" value="<?php echo $index; ?>" />
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash me-1"></i>Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
