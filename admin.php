<?php
session_start();

// Security check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit();
}
// did not use a sql server because one at most they will need to store 250 plants ,and two I am not dealing with the hasle of setting that up 
// File paths
const CATALOGUE_FILE = 'catalogue.json';
const CATEGORIES_FILE = 'categories.json';
const CONTACT_FILE = 'contact.json';
const ABOUT_FILE = 'about.json';

// Default data structures
const DEFAULT_CONTACT = [
    'email' => '', 'phone' => '', 'facebook' => '', 'instagram' => '',
    'whatsapp' => '', 'tiktok' => '', 'twitter' => '', 'bluesky' => ''
];

const DEFAULT_ABOUT = ['header' => '', 'content' => ''];

const PLANT_SIZES = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL'];

const DEFAULT_PLANT_DETAILS = [
    'duree_de_vie' => '',
    'type_de_plante' => '',
    'hauteur_de_plante' => '',
    'diametre_de_la_couronne' => '',
    'temperature_ideale' => '',
    'arrosage' => '',
    'ensoleillement' => '',
];

// Global variables
$message = '';
$error = '';
$edit_plant_index = null;
$edit_plant_data = null;

// Helper Functions
function loadJsonFile($filename, $default = []) {
    if (!file_exists($filename)) {
        file_put_contents($filename, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $default;
    }
    
    $data = json_decode(file_get_contents($filename), true);
    return is_array($data) ? $data : $default;
}

function saveJsonFile($filename, $data) {
    return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function setMessage($msg) {
    global $message;
    $message = $msg;
}

function setError($err) {
    global $error;
    $error = $err;
}


// Initialize data 
$contact_data = loadJsonFile(CONTACT_FILE, DEFAULT_CONTACT);
$about_data = loadJsonFile(ABOUT_FILE, DEFAULT_ABOUT);
$catalogue = loadJsonFile(CATALOGUE_FILE, []);

// Initialize categories with fallback logic
if (file_exists(CATEGORIES_FILE)) {
    $categories = loadJsonFile(CATEGORIES_FILE, []);
} else {
    $unique_categories = [];
    foreach ($catalogue as $plant) {
        if (!empty($plant['category']) && !in_array($plant['category'], $unique_categories)) {
            $unique_categories[] = $plant['category'];
        }
    }
    $categories = $unique_categories;
    saveJsonFile(CATEGORIES_FILE, $categories);
}

// Action Handlers
function handleUpdateContact() {
    global $contact_data;
    
    $updated_contact = [];
    foreach (DEFAULT_CONTACT as $field => $default) {
        $updated_contact[$field] = trim($_POST[$field] ?? '');
    }
    
    if (saveJsonFile(CONTACT_FILE, $updated_contact)) {
        $contact_data = $updated_contact;
        setMessage("Coordonnées mises à jour avec succès.");
    } else {
        setError("Erreur lors de la mise à jour des coordonnées.");
    }
}

function handleUpdateAbout() {
    global $about_data;
    
    $updated_about = [
        'header' => trim($_POST['about_header'] ?? ''),
        'content' => trim($_POST['about_content'] ?? '')
    ];
    
    if (empty($updated_about['header']) || empty($updated_about['content'])) {
        setError('Veuillez remplir tous les champs de la section À propos.');
        return;
    }
    
    if (saveJsonFile(ABOUT_FILE, $updated_about)) {
        $about_data = $updated_about;
        setMessage("Section À propos mise à jour avec succès.");
    } else {
        setError("Erreur lors de la mise à jour de la section À propos.");
    }
}

function handleAddPlant() {
    global $catalogue;
    
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    if (empty($name)) {
        setError('Le nom de la plante est obligatoire.');
        return;
    }
    
    // Handle both old and new format inputs
    $new_plant = processPlantFormData($_POST);
    $new_plant['name'] = $name;
    $new_plant['category'] = $category;
    
    // Validate that we have at least some basic information
    $has_photos = !empty($new_plant['photos']);
    $has_prices = !empty($new_plant['prices']);
    
    // For backward compatibility, also check old format
    if (!$has_photos && !empty($_POST['image'])) {
        $new_plant['photos'][] = trim($_POST['image']);
        $has_photos = true;
    }
    
    if (!$has_prices && !empty($_POST['price'])) {
        $new_plant['prices']['M'] = [
            'price' => trim($_POST['price']),
            'available' => true
        ];
        $has_prices = true;
    }
    
    $catalogue[] = $new_plant;
    if (saveJsonFile(CATALOGUE_FILE, $catalogue)) {
        setMessage('Plante ajoutée avec succès !');
    } else {
        setError('Erreur lors de l\'enregistrement. Vérifiez les permissions du fichier.');
    }
}

function handleStartEditPlant() {
    global $catalogue, $edit_plant_index, $edit_plant_data;
    
    $index = (int)($_POST['index'] ?? -1);
    if (isset($catalogue[$index])) {
        $edit_plant_index = $index;
        $prepared_data = preparePlantForEdit($catalogue[$index]);
        $edit_plant_data = $prepared_data['plant'];
        $edit_plant_data['_photos_text'] = $prepared_data['photos_text'];
        $edit_plant_data['_availability'] = $prepared_data['availability'];
    } else {
        setError('Plante introuvable pour édition.');
    }
}

function handleEditPlant() {
    global $catalogue, $edit_plant_index, $edit_plant_data;
    
    $index = (int)($_POST['index'] ?? -1);
    if (!isset($catalogue[$index])) {
        setError('Plante introuvable pour mise à jour.');
        return;
    }
    
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    if (empty($name)) {
        setError('Le nom de la plante est obligatoire.');
        $edit_plant_index = $index;
        $edit_plant_data = processPlantFormData($_POST);
        $edit_plant_data['name'] = $name;
        $edit_plant_data['category'] = $category;
        return;
    }
    
    // Handle both old and new format inputs
    $updated_plant = processPlantFormData($_POST);
    $updated_plant['name'] = $name;
    $updated_plant['category'] = $category;
   
    $catalogue[$index] = $updated_plant;
    if (saveJsonFile(CATALOGUE_FILE, $catalogue)) {
        setMessage('Plante modifiée avec succès !');
        // Clear edit state on success
        $edit_plant_index = null;
        $edit_plant_data = null;
    } else {
        setError('Erreur lors de la mise à jour. Vérifiez les permissions du fichier.');
        $edit_plant_index = $index;
        $edit_plant_data = $updated_plant;
    }
}

function handleDeletePlant() {
    global $catalogue;
    
    $index = (int)($_POST['index'] ?? -1);
    if (!isset($catalogue[$index])) {
        setError('Plante introuvable pour suppression.');
        return;
    }
    
    unset($catalogue[$index]);
    $catalogue = array_values($catalogue);
    
    if (saveJsonFile(CATALOGUE_FILE, $catalogue)) {
        setMessage('Plante supprimée avec succès !');
    } else {
        setError('Erreur lors de la suppression. Vérifiez les permissions du fichier.');
    }
}

function handleAddCategory() {
    global $categories;
    
    $new_cat = trim($_POST['category_name'] ?? '');
    
    if ($new_cat === '') {
        setError('Le nom de la catégorie ne peut pas être vide.');
        return;
    }
    
    if (in_array($new_cat, $categories)) {
        setError('Cette catégorie existe déjà.');
        return;
    }
    
    $categories[] = $new_cat;
    if (saveJsonFile(CATEGORIES_FILE, $categories)) {
        setMessage('Catégorie ajoutée avec succès !');
    } else {
        setError('Erreur lors de l\'enregistrement de la catégorie.');
    }
}

function handleDeleteCategory() {
    global $categories, $catalogue;
    
    $del_cat = trim($_POST['category_name'] ?? '');
    
    if (!in_array($del_cat, $categories)) {
        setError('La catégorie spécifiée n\'existe pas.');
        return;
    }
    
    // Remove category from list
    $categories = array_values(array_filter($categories, fn($c) => $c !== $del_cat));
    
    // Update plants with deleted category
    foreach ($catalogue as &$plant) {
        if (isset($plant['category']) && $plant['category'] === $del_cat) {
            $plant['category'] = "";
        }
    }
    unset($plant);
    
    // Save both files
    $savedCats = saveJsonFile(CATEGORIES_FILE, $categories);
    $savedPlants = saveJsonFile(CATALOGUE_FILE, $catalogue);
    
    if ($savedCats && $savedPlants) {
        setMessage("Catégorie '$del_cat' supprimée. Les plantes associées ont été mises à jour.");
    } else {
        setError("Erreur lors de la suppression de la catégorie ou la mise à jour des plantes.");
    }
}

// Process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actions = [
        'update_contact' => 'handleUpdateContact',
        'update_about' => 'handleUpdateAbout',
        'add_plant' => 'handleAddPlant',
        'start_edit_plant' => 'handleStartEditPlant',
        'edit_plant' => 'handleEditPlant',
        'delete_plant' => 'handleDeletePlant',
        'add_category' => 'handleAddCategory',
        'delete_category' => 'handleDeleteCategory'
    ];
    
    $action = $_POST['action'];
    if (isset($actions[$action]) && function_exists($actions[$action])) {
        $actions[$action]();
    }
}

// Helper function to process form data for both old and new formats
function processPlantFormData($post_data) {
    $plant = [
        'name' => '',
        'category' => '',
        'photos' => [],
        'prices' => [],
        'details' => DEFAULT_PLANT_DETAILS
    ];
    
    // Process photos - handle both single 'photos' field and multiple URLs
    if (!empty($post_data['photos'])) {
        if (is_array($post_data['photos'])) {
            // If photos is already an array
            foreach ($post_data['photos'] as $photo) {
                $photo = trim($photo);
                if (!empty($photo)) {
                    $plant['photos'][] = $photo;
                }
            }
        } else {
            // If photos is a string (textarea with line breaks)
            $photo_urls = explode("\n", $post_data['photos']);
            foreach ($photo_urls as $url) {
                $url = trim($url);
                if (!empty($url)) {
                    $plant['photos'][] = $url;
                }
            }
        }
    }
    
    // Process prices for all sizes
    foreach (PLANT_SIZES as $size) {
        $price = trim($post_data["price_$size"] ?? '');
        $available = isset($post_data["available_$size"]) && $post_data["available_$size"];
        
        if (!empty($price) || $available) {
            $plant['prices'][$size] = [
                'price' => $price,
                'available' => $available
            ];
        }
    }
    
    // Process all detail fields
    foreach (DEFAULT_PLANT_DETAILS as $field => $default) {
        if (isset($post_data[$field])) {
            $plant['details'][$field] = trim($post_data[$field]);
        }
    }
    
    return $plant;
}

// Enhanced function to prepare plant data for editing forms
function preparePlantForEdit($plant) {
    // Prepare photos as newline-separated string for textarea
    $photos_text = implode("\n", $plant['photos']);
    
    // Prepare availability flags for form checkboxes
    $availability = [];
    foreach (PLANT_SIZES as $size) {
        $availability[$size] = isset($plant['prices'][$size]) && $plant['prices'][$size]['available'];
    }
    
    return [
        'plant' => $plant,
        'photos_text' => $photos_text,
        'availability' => $availability
    ];
}

?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Administration - Manbet MiMa</title>
    <link rel="icon" href="emoji.png" type="image/png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        :root {
            --bg-light: #f0fdf4;
            --bg-dark: #121212;
            --text-light: #2d2d2d;
            --text-dark: #f5f5f5;
            --card-bg-light: #ffffff;
            --card-bg-dark: #1e1e1e;
            --accent-light: #22c55e;
            --accent-dark: #16a34a;
            --desc-light: #6b7280;
            --desc-dark: #cccccc;
            --card-border-dark: #333;
            --success-light: #d1fae5;
            --success-dark: #065f46;
        }

        html {
            transition: background-color 0.5s ease, color 0.5s ease;
        }

        [data-theme="dark"] {
            background-color: var(--bg-dark);
            color: var(--text-dark);
        }

        [data-theme="dark"] body {
            background: var(--bg-dark);
            color: var(--text-dark);
        }

        body {
            font-family: "Segoe UI", sans-serif;
            background: var(--bg-light);
            color: var(--text-light);
            min-height: 100vh;
            transition: background-color 0.5s ease, color 0.5s ease;
        }

        .admin-header {
            background: var(--card-bg-light);
            padding: 1.5rem 0;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 1050;
            transition: background-color 0.3s ease;
        }

        [data-theme="dark"] .admin-header {
            background: var(--card-bg-dark);
        }

        .admin-card {
            background: var(--card-bg-light);
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        [data-theme="dark"] .admin-card {
            background: var(--card-bg-dark);
            border: 1px solid var(--card-border-dark);
            color: var(--text-dark);
        }

        .admin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--card-bg-light);
            color: var(--text-light);
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: #2a2a2a;
            border-color: #404040;
            color: var(--text-dark);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-light);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
            transform: translateY(-1px);
        }

        .btn {
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: var(--accent-light);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }

        .btn-outline-danger:hover {
            transform: translateY(-1px);
        }

        .btn-outline-primary:hover {
            transform: translateY(-1px);
        }

        .plant-card {
            background: var(--card-bg-light);
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .plant-card {
            background: var(--card-bg-dark);
            border: 1px solid var(--card-border-dark);
        }

        .plant-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.1);
        }

        .plant-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }

        .image-fallback {
            height: 200px;
            background: var(--accent-light);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .price-badge {
            background: var(--accent-light);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
        }

        .category-badge {
            background: var(--success-light);
            color: var(--success-dark);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .dark-toggle {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 1.2rem;
            color: var(--accent-light);
            transition: all 0.3s ease;
        }

        .dark-toggle:hover {
            transform: scale(1.1);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
        }

        .section-header {
            border-bottom: 3px solid var(--accent-light);
            padding-bottom: 12px;
            margin-bottom: 24px;
            background: linear-gradient(135deg, var(--accent-light), var(--accent-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--accent-light), var(--accent-dark));
            color: white;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-floating {
            margin-bottom: 20px;
        }

        .form-floating > .form-control,
        .form-floating > .form-select {
            height: auto;
            padding: 16px 12px 8px 12px;
        }

        .form-floating > label {
            padding: 16px 12px;
            color: var(--desc-light);
        }

        [data-theme="dark"] .form-floating > label {
            color: var(--desc-dark);
        }

        .table {
            background: var(--card-bg-light);
            border-radius: 12px;
            overflow: hidden;
        }

        [data-theme="dark"] .table {
            background: var(--card-bg-dark);
            color: var(--text-dark);
        }

        .table th {
            background: var(--accent-light);
            color: white;
            border: none;
            padding: 16px;
        }

        .table td {
            padding: 16px;
            border-color: #e2e8f0;
        }

        [data-theme="dark"] .table td {
            border-color: #404040;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 14px;
            }
            
            .form-control, .form-select {
                padding: 10px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <!-- Modern Header -->
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h2 text-success mb-0">
                        <i class="fas fa-cog me-2"></i>Administration Manbet MiMa
                    </h1>
                    <p class="text-muted mb-0 mt-1">Gérez votre catalogue de plantes</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="dark-toggle" id="themeToggle" title="Mode sombre">
                        <i class="fa-solid fa-moon" id="themeIcon"></i>
                    </span>
                    <a href="index" class="btn btn-outline-secondary">
                        <i class="fas fa-eye me-2"></i>Voir le site
                    </a>
                    <a href="logout" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
     

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
            <div class="admin-card mb-5">
                <div class="card-body p-4">
                    <h3 class="section-header">
                        <i class="fas fa-edit me-2"></i>Modifier la plante
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_plant" />
                        <input type="hidden" name="index" value="<?php echo $edit_plant_index; ?>" />
                        
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="edit_name" name="name" 
                                           value="<?php echo htmlspecialchars($edit_plant_data['name']); ?>" required />
                                    <label for="edit_name">Nom de la plante *</label>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="edit_price" name="price" 
                                           step="0.01" min="0" value="<?php echo htmlspecialchars($edit_plant_data['price']); ?>" required />
                                    <label for="edit_price">Prix (TND) *</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-floating">
                                    <select class="form-select" id="edit_category" name="category">
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                                    <?php if (($edit_plant_data['category'] ?? '') === $cat) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($cat); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="edit_category">Catégorie</label>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-floating">
                                    <input type="url" class="form-control" id="edit_image" name="image" 
                                           value="<?php echo htmlspecialchars($edit_plant_data['image']); ?>" />
                                    <label for="edit_image">URL de l'image</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating">
                            <textarea class="form-control" id="edit_description" name="description" 
                                      style="height: 120px;" required><?php echo htmlspecialchars($edit_plant_data['description']); ?></textarea>
                            <label for="edit_description">Description *</label>
                        </div>

                        <div class="d-flex gap-3 flex-wrap">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                            </button>
                            <a href="admin" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Add Plant Form -->
            <div class="admin-card mb-5">
                <div class="card-body p-4">
                    <h3 class="section-header">
                        <i class="fas fa-plus me-2"></i>Ajouter une nouvelle plante
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_plant" />
                        
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="name" name="name" required />
                                    <label for="name">Nom de la plante *</label>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="price" name="price" 
                                           step="0.01" min="0" required />
                                    <label for="price">Prix (TND) *</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-floating">
                                    <select class="form-select" id="category" name="category">
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>">
                                                <?php echo htmlspecialchars($cat); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="category">Catégorie</label>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-floating">
                                    <input type="url" class="form-control" id="image" name="image" />
                                    <label for="image">URL de l'image</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating">
                            <textarea class="form-control" id="description" name="description" 
                                      style="height: 120px;" required></textarea>
                            <label for="description">Description *</label>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Ajouter la plante
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

<!-- Coordonnées de Contact -->
<div class="container mb-5">
    <div class="card admin-card">
        <div class="card-header">
            <h3 class="h5 mb-0"><i class="fas fa-address-card me-2"></i>Coordonnées de Contact</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_contact" />
                <div class="row g-3">
                    <?php foreach ($default_contact as $key => $val): ?>
                        <div class="col-md-6">
                            <label for="<?php echo $key; ?>" class="form-label text-capitalize"><?php echo ucfirst($key); ?></label>
                            <input type="text" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($contact_data[$key] ?? ''); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-success mt-3">
                    <i class="fas fa-save me-2"></i>Enregistrer les informations
                </button>
            </form>
        </div>
    </div>
</div>
<!-- gestion de page a propos -->
<div class="container mb-5" style="padding-left: 1.5rem; padding-right: 1.5rem;">
    <div class="card admin-card" style="padding: 1.75rem 2rem;">
        <h3 class="section-header mb-4">
            <i class="fas fa-info-circle me-2"></i>Section À propos
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_about" />
            
            <div class="form-floating mb-4">
                <input type="text" class="form-control" id="about_header" name="about_header" 
                       value="<?php echo htmlspecialchars($about_data['header'] ?? ''); ?>" required />
                <label for="about_header">Titre de la section *</label>
            </div>

            <div class="form-floating mb-4">
                <textarea class="form-control" id="about_content" name="about_content" 
                          style="height: 150px;" required><?php echo htmlspecialchars($about_data['content'] ?? ''); ?></textarea>
                <label for="about_content">Contenu de la section *</label>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Enregistrer la section À propos
            </button>
        </form>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>