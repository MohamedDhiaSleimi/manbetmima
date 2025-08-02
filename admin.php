<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$catalogue_file = 'catalogue.json';
$categories_file = 'categories.json';

$catalogue = [];
$categories = [];

// Load or initialize categories.json with fallback logic
if (file_exists($categories_file)) {
    $json_categories = file_get_contents($categories_file);
    $categories = json_decode($json_categories, true);
    if ($categories === null) {
        $categories = [];
    }
} else {
    // categories.json missing
    if (file_exists($catalogue_file)) {
        // Load catalogue.json and extract unique categories
        $json_catalogue = file_get_contents($catalogue_file);
        $catalogue_tmp = json_decode($json_catalogue, true);
        if ($catalogue_tmp === null) {
            $catalogue_tmp = [];
        }
        $unique_categories = [];
        foreach ($catalogue_tmp as $plant) {
            if (!empty($plant['category']) && !in_array($plant['category'], $unique_categories)) {
                $unique_categories[] = $plant['category'];
            }
        }
        $categories = $unique_categories;
        // Save categories.json
        file_put_contents($categories_file, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        // Neither file exists, create both empty
        $categories = [];
        file_put_contents($categories_file, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($catalogue_file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// Load catalogue.json (must be done after above fallback to ensure file exists)
if (file_exists($catalogue_file)) {
    $json_content = file_get_contents($catalogue_file);
    $catalogue = json_decode($json_content, true);
    if ($catalogue === null) {
        $catalogue = [];
    }
} else {
    $catalogue = [];
}


$message = '';
$error = '';

// Determine if editing a plant (show edit form)
$edit_plant_index = null;
$edit_plant_data = null;

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

            case 'start_edit_plant':
                // Show the edit form for selected plant
                $index = (int)($_POST['index'] ?? -1);
                if (isset($catalogue[$index])) {
                    $edit_plant_index = $index;
                    $edit_plant_data = $catalogue[$index];
                } else {
                    $error = 'Plante introuvable pour édition.';
                }
                break;

            case 'edit_plant':
                $index = (int)($_POST['index'] ?? -1);
                if (!isset($catalogue[$index])) {
                    $error = 'Plante introuvable pour mise à jour.';
                    break;
                }

                $updated_plant = [
                    'name' => trim($_POST['name']),
                    'price' => trim($_POST['price']),
                    'category' => trim($_POST['category']),
                    'image' => trim($_POST['image']),
                    'description' => trim($_POST['description']),
                ];

                if (empty($updated_plant['name']) || empty($updated_plant['price']) || empty($updated_plant['description'])) {
                    $error = 'Veuillez remplir tous les champs obligatoires.';
                    $edit_plant_index = $index;
                    $edit_plant_data = $updated_plant; // keep form filled with submitted data
                } else {
                    $catalogue[$index] = $updated_plant;
                    if (file_put_contents($catalogue_file, json_encode($catalogue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $message = 'Plante modifiée avec succès !';
                    } else {
                        $error = 'Erreur lors de la mise à jour. Vérifiez les permissions du fichier.';
                        $edit_plant_index = $index;
                        $edit_plant_data = $updated_plant;
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
                $new_cat = trim($_POST['category_name']);
                if ($new_cat === '') {
                    $error = 'Le nom de la catégorie ne peut pas être vide.';
                } elseif (in_array($new_cat, $categories)) {
                    $error = 'Cette catégorie existe déjà.';
                } else {
                    $categories[] = $new_cat;
                    if (file_put_contents($categories_file, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $message = 'Catégorie ajoutée avec succès !';
                    } else {
                        $error = 'Erreur lors de l’enregistrement de la catégorie.';
                    }
                }
                break;

            case 'delete_category':
                $del_cat = trim($_POST['category_name']);
                if (!in_array($del_cat, $categories)) {
                    $error = 'La catégorie spécifiée n\'existe pas.';
                } else {
                    $categories = array_filter($categories, fn($c) => $c !== $del_cat);
                    $categories = array_values($categories);

                    $default_category = "Uncategorized";
                    if (!in_array($default_category, $categories)) {
                        $categories[] = $default_category;
                    }

                    foreach ($catalogue as &$plant) {
                        if (isset($plant['category']) && $plant['category'] === $del_cat) {
                            $plant['category'] = $default_category;
                        }
                    }
                    unset($plant);

                    $savedCats = file_put_contents($categories_file, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $savedPlants = file_put_contents($catalogue_file, json_encode($catalogue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    if ($savedCats && $savedPlants) {
                        $message = "Catégorie '$del_cat' supprimée. Les plantes associées ont été mises à jour.";
                    } else {
                        $error = "Erreur lors de la suppression de la catégorie ou la mise à jour des plantes.";
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
    <link rel="icon" href="emoji.png" type="image/png" />
    <link rel="icon" href="emoji.png" type="image/png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
       body {
    background: linear-gradient(135deg, #e6f4ea 0%, #c8e6c9 100%);
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
                <h1 class="h2 text-success mb-0"><i class="fas fa-cog me-2"></i>Panneau d'administration</h1>
                <div>
                    <a href="index.html" class="btn btn-outline-secondary me-2"><i class="fas fa-eye me-2"></i>Voir le site</a>
                    <a href="logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($edit_plant_index !== null && $edit_plant_data !== null): ?>
            <!-- Edit Plant Form -->
            <div class="card admin-card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0"><i class="fas fa-edit me-2"></i>Modifier la plante</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_plant" />
                        <input type="hidden" name="index" value="<?php echo $edit_plant_index; ?>" />
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_name" class="form-label">Nom de la plante *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" value="<?php echo htmlspecialchars($edit_plant_data['name']); ?>" required />
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_price" class="form-label">Prix *</label>
                                <div class="input-group">
                                    <span class="input-group-text">TND</span>
                                    <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($edit_plant_data['price']); ?>" required />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_category" class="form-label">Catégorie</label>
                                <select class="form-select" id="edit_category" name="category">
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php if (($edit_plant_data['category'] ?? '') === $cat) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_image" class="form-label">URL de l'image</label>
                                <input type="url" class="form-control" id="edit_image" name="image" placeholder="https://exemple.com/image.jpg" value="<?php echo htmlspecialchars($edit_plant_data['image']); ?>" />
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description *</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" required><?php echo htmlspecialchars($edit_plant_data['description']); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                        <a href="admin.php" class="btn btn-secondary ms-2">Annuler</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Ajouter une plante -->
            <div class="card admin-card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0"><i class="fas fa-plus me-2"></i>Ajouter une plante</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_plant" />
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Nom de la plante *</label>
                                <input type="text" class="form-control" id="name" name="name" required />
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Prix *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Catégorie</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="image" class="form-label">URL de l'image</label>
                                <input type="url" class="form-control" id="image" name="image" placeholder="https://exemple.com/image.jpg" />
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
        <?php endif; ?>

        <!-- Liste des plantes -->
        <div class="card admin-card mb-4">
            <div class="card-header">
                <h3 class="h5 mb-0"><i class="fas fa-list me-2"></i>Plantes enregistrées (<?php echo count($catalogue); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($catalogue)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-seedling fa-3x mb-3"></i>
                        <h5>Aucune plante dans le catalogue</h5>
                        <p>Ajoutez votre première plante via le formulaire ci-dessus</p>
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
                                            <?php echo htmlspecialchars(mb_strimwidth($plant['description'], 0, 60, '...')); ?>
                                        </p>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment supprimer cette plante ?')">
                                            <input type="hidden" name="action" value="delete_plant" />
                                            <input type="hidden" name="index" value="<?php echo $index; ?>" />
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-trash me-1"></i>Supprimer
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline ms-2">
                                            <input type="hidden" name="action" value="start_edit_plant" />
                                            <input type="hidden" name="index" value="<?php echo $index; ?>" />
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-edit me-1"></i>Modifier
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

        <!-- Gestion des catégories -->
        <div class="card admin-card mb-4">
            <div class="card-header">
                <h3 class="h5 mb-0"><i class="fas fa-tags me-2"></i>Gestion des catégories</h3>
            </div>
            <div class="card-body">
                <!-- Ajouter une catégorie -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add_category" />
                    <div class="input-group">
                        <input type="text" class="form-control" name="category_name" placeholder="Nom de la nouvelle catégorie" required />
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Ajouter une catégorie
                        </button>
                    </div>
                </form>

                <!-- Supprimer une catégorie -->
                <?php if (count($categories) === 0): ?>
                    <p class="text-muted">Aucune catégorie disponible.</p>
                <?php else: ?>
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Catégorie</th>
                                <th style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cat); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer la catégorie &quot;<?php echo htmlspecialchars($cat); ?>&quot; ? Les plantes associées seront déplacées vers une catégorie par défaut.');" class="d-inline">
                                            <input type="hidden" name="action" value="delete_category" />
                                            <input type="hidden" name="category_name" value="<?php echo htmlspecialchars($cat); ?>" />
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-trash me-1"></i>Supprimer
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted"><em>Note : Si une catégorie est supprimée, les plantes associées seront déplacées vers la catégorie <strong>Uncategorized</strong>.</em></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
