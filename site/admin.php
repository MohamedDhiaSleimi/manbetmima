<?php
session_start();

// Security check
if ( !isset( $_SESSION[ 'admin_logged_in' ] ) || $_SESSION[ 'admin_logged_in' ] !== true ) {
    header( 'Location: login' );
    exit();
}
// did not use a sql server because one at most they will need to store 250 plants, and two I am not dealing with the hasle of setting that up
// File paths
const CATALOGUE_FILE = './storage/binary/catalogue.bin';
const CATEGORIES_FILE = './storage/binary/categories.bin';
const CONTACT_FILE = './storage/binary/contact.bin';
const ABOUT_FILE = './storage/binary/about.bin';
const ORDER_FILE = './storage/binary/orders.bin';
define('UPLOADS_DIR', __DIR__ . '/uploads/plants/');

// Ensure uploads directory exists
if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0777, true);
}

// Orders archive directory (fulfilled orders as ZIP)
define('ORDERS_ARCHIVE_DIR', __DIR__ . '/orders_archive/');
if (!is_dir(ORDERS_ARCHIVE_DIR)) {
    mkdir(ORDERS_ARCHIVE_DIR, 0777, true);
}

const MAX_STORAGE_SIZE = 3096;
// 3096 MB limit
const MAX_FILE_SIZE = 500;
// 5 MB per file

// Default data structures
const DEFAULT_CONTACT = [
    'email' => '', 'phone' => '', 'facebook' => '', 'instagram' => '',
    'whatsapp' => '', 'tiktok' => '', 'twitter' => '', 'bluesky' => ''
];

const DEFAULT_ABOUT = [ 'header' => '', 'content' => '' ];

const PLANT_SIZES = [ 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL' ];

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

function loadDataFile($filename, $default = []) {
    if (!file_exists($filename)) {
        file_put_contents($filename, serialize($default));
        return $default;
    }
    $data = @unserialize(file_get_contents($filename));
    return is_array($data) ? $data : $default;
}

function saveDataFile($filename, $data) {
    return file_put_contents($filename, serialize($data));
}


function setMessage( $msg ) {
    global $message;
    $message = $msg;
}

function setError( $err ) {
    global $error;
    $error = $err;
}

function getStorageInfo() {
    $totalSize = 0;
    $fileCount = 0;

    if ( is_dir( UPLOADS_DIR ) ) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( UPLOADS_DIR, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $files as $file ) {
            if ( $file->isFile() ) {
                $totalSize += $file->getSize();
                $fileCount++;
            }
        }
    }

    $totalSizeMB = $totalSize / ( 1024 * 1024 );
    $usedPercentage = ( MAX_STORAGE_SIZE > 0 ) ? ( $totalSizeMB / MAX_STORAGE_SIZE ) * 100 : 0;

    return [
        'used_mb' => round( $totalSizeMB, 2 ),
        'max_mb' => MAX_STORAGE_SIZE,
        'available_mb' => round( MAX_STORAGE_SIZE - $totalSizeMB, 2 ),
        'used_percentage' => round( $usedPercentage, 1 ),
        'file_count' => $fileCount
    ];
}

function uploadImage($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors du téléchargement du fichier.'];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE * 1024 * 1024) {
        return ['success' => false, 'message' => 'Le fichier est trop volumineux. Taille maximale: ' . MAX_FILE_SIZE . 'MB'];
    }

    // Check storage limit
    $storage = getStorageInfo();
    if ($storage['used_mb'] + ($file['size'] / (1024 * 1024)) > MAX_STORAGE_SIZE) {
        return ['success' => false, 'message' => 'Espace de stockage insuffisant.'];
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.'];
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = UPLOADS_DIR . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Return relative path instead of server path
        $relativePath = 'uploads/plants/' . $filename;
        return ['success' => true, 'path' => $relativePath, 'filename' => $filename];
    }

    return ['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier.'];
}


function deleteImage( $imagePath ) {
    if ( file_exists( $imagePath ) && strpos( $imagePath, UPLOADS_DIR ) === 0 ) {
        return unlink( $imagePath );
    }
    return false;
}

// Initialize data
$contact_data = loadDataFile( CONTACT_FILE, DEFAULT_CONTACT );
$about_data = loadDataFile( ABOUT_FILE, DEFAULT_ABOUT );
$catalogue = loadDataFile( CATALOGUE_FILE, [] );
$orders = loadDataFile( ORDER_FILE, [] );
$edit_order_index = null;
$edit_order_data = null;

// Initialize categories with fallback logic
if ( file_exists( CATEGORIES_FILE ) ) {
    $categories = loadDataFile( CATEGORIES_FILE, [] );
} else {
    $unique_categories = [];
    foreach ( $catalogue as $plant ) {
        if ( !empty( $plant[ 'category' ] ) && !in_array( $plant[ 'category' ], $unique_categories ) ) {
            $unique_categories[] = $plant[ 'category' ];
        }
    }
    $categories = $unique_categories;
    saveDataFile( CATEGORIES_FILE, $categories );
}

// Action Handlers
function handleUpdateContact() {
    global $contact_data;

    $updated_contact = [];
    foreach ( DEFAULT_CONTACT as $field => $default ) {
        $updated_contact[ $field ] = trim( $_POST[ $field ] ?? '' );
    }

    if ( saveDataFile( CONTACT_FILE, $updated_contact ) ) {
        $contact_data = $updated_contact;
        setMessage( 'Coordonnées mises à jour avec succès.' );
    } else {
        setError( 'Erreur lors de la mise à jour des coordonnées.' );
    }
}

function handleUpdateAbout() {
    global $about_data;

    $updated_about = [
        'header' => trim( $_POST[ 'about_header' ] ?? '' ),
        'content' => trim( $_POST[ 'about_content' ] ?? '' )
    ];

    if ( empty( $updated_about[ 'header' ] ) || empty( $updated_about[ 'content' ] ) ) {
        setError( 'Veuillez remplir tous les champs de la section À propos.' );
        return;
    }

    if ( saveDataFile( ABOUT_FILE, $updated_about ) ) {
        $about_data = $updated_about;
        setMessage( 'Section À propos mise à jour avec succès.' );
    } else {
        setError( 'Erreur lors de la mise à jour de la section À propos.' );
    }
}

function handleAddPlant() {
    global $catalogue;

    $name = trim( $_POST[ 'name' ] ?? '' );
    $category = trim( $_POST[ 'category' ] ?? '' );

    if ( empty( $name ) ) {
        setError( 'Le nom de la plante est obligatoire.' );
        return;
    }

    // Handle both old and new format inputs
    $new_plant = processPlantFormData( $_POST );
    $new_plant[ 'name' ] = $name;
    $new_plant[ 'category' ] = $category;

    // Handle image uploads
    $uploadedImages = [];
    if ( !empty( $_FILES[ 'plant_images' ][ 'name' ][ 0 ] ) ) {
        foreach ( $_FILES[ 'plant_images' ][ 'name' ] as $key => $name ) {
            if ( $_FILES[ 'plant_images' ][ 'error' ][ $key ] === UPLOAD_ERR_OK ) {
                $file = [
                    'name' => $_FILES[ 'plant_images' ][ 'name' ][ $key ],
                    'type' => $_FILES[ 'plant_images' ][ 'type' ][ $key ],
                    'tmp_name' => $_FILES[ 'plant_images' ][ 'tmp_name' ][ $key ],
                    'error' => $_FILES[ 'plant_images' ][ 'error' ][ $key ],
                    'size' => $_FILES[ 'plant_images' ][ 'size' ][ $key ]
                ];

                $upload = uploadImage( $file );
                if ( $upload[ 'success' ] ) {
                    $uploadedImages[] = $upload[ 'path' ];
                } else {
                    setError( $upload[ 'message' ] );
                    return;
                }
            }
        }
    }

    if ( !empty( $uploadedImages ) ) {
        $new_plant[ 'photos' ] = $uploadedImages;
    }

    $catalogue[] = $new_plant;
    if ( saveDataFile( CATALOGUE_FILE, $catalogue ) ) {
        setMessage( 'Plante ajoutée avec succès !' );
    } else {
        setError( 'Erreur lors de l\'enregistrement. Vérifiez les permissions du fichier.');
    }
}

function handleStartEditPlant() {
    global $catalogue, $edit_plant_index, $edit_plant_data;
    
    $index = (int)($_POST['index'] ?? -1);
    if (isset($catalogue[$index])) {
        $edit_plant_index = $index;
        $prepared_data = preparePlantForEdit($catalogue[$index]);
        $edit_plant_data = $prepared_data['plant'];
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
        return;
    }
    
    // Process form data using existing function
    $updated_plant = processPlantFormData($_POST);
    $updated_plant['name'] = $name;
    $updated_plant['category'] = $category;
    
    // Keep existing photos
    $updated_plant['photos'] = $catalogue[$index]['photos'] ?? [];
    
    // Handle new image uploads
    if (!empty($_FILES['plant_images']['name'][0])) {
        foreach ($_FILES['plant_images']['name'] as $key => $filename) {
            if ($_FILES['plant_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['plant_images']['name'][$key],
                    'type' => $_FILES['plant_images']['type'][$key],
                    'tmp_name' => $_FILES['plant_images']['tmp_name'][$key],
                    'error' => $_FILES['plant_images']['error'][$key],
                    'size' => $_FILES['plant_images']['size'][$key]
                ];
                
                $upload = uploadImage($file);
                if ($upload['success']) {
                    $updated_plant['photos'][] = $upload['path'];
                } else {
                    setError($upload['message']);
                    return;
                }
            }
        }
    }
    
    // Handle image deletions
    if (!empty($_POST['delete_images'])) {
        $toDelete = $_POST['delete_images'];
        foreach ($toDelete as $imagePath) {
            deleteImage($imagePath);
            $updated_plant['photos'] = array_values(array_filter($updated_plant['photos'], function($photo) use ($imagePath) {
                return $photo !== $imagePath;
            }));
        }
    }
    
    $catalogue[$index] = $updated_plant;
    if (saveDataFile(CATALOGUE_FILE, $catalogue)) {
        setMessage('Plante modifiée avec succès !');
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
    
    // Delete associated images
    if (!empty($catalogue[$index]['photos'])) {
        foreach ($catalogue[$index]['photos'] as $photo) {
            deleteImage($photo);
        }
    }
    
    unset($catalogue[$index]);
    $catalogue = array_values($catalogue);
    
    if (saveDataFile(CATALOGUE_FILE, $catalogue)) {
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
    if (saveDataFile(CATEGORIES_FILE, $categories)) {
        setMessage('Catégorie ajoutée avec succès !');
    } else {
        setError('Erreur lors de l\'enregistrement de la catégorie.' );
    }
}

function handleDeleteCategory() {
    global $categories, $catalogue;

    $del_cat = trim( $_POST[ 'category_name' ] ?? '' );

    if ( !in_array( $del_cat, $categories ) ) {
        setError( 'La catégorie spécifiée n\'existe pas.');
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
    $savedCats = saveDataFile(CATEGORIES_FILE, $categories);
    $savedPlants = saveDataFile(CATALOGUE_FILE, $catalogue);
    
    if ($savedCats && $savedPlants) {
        setMessage("Catégorie '$del_cat' supprimée. Les plantes associées ont été mises à jour.");
    } else {
        setError("Erreur lors de la suppression de la catégorie ou la mise à jour des plantes.");
    }
}

function saveOrders($orders) {
    return saveDataFile(ORDER_FILE, $orders);
}

function handleStartEditOrder() {
    global $orders, $edit_order_index, $edit_order_data;
    $index = (int)($_POST['index'] ?? -1);
    if (isset($orders[$index])) {
        $edit_order_index = $index;
        $edit_order_data = $orders[$index];
    } else {
        setError('Commande introuvable pour édition.');
    }
}

function handleEditOrder() {
    global $orders, $edit_order_index, $edit_order_data;
    $index = (int)($_POST['index'] ?? -1);
    if (!isset($orders[$index])) {
        setError('Commande introuvable pour mise à jour.');
        return;
    }

    // Update customer fields & total/status (keep cart immutable here)
    $orders[$index]['customer']['name']    = trim($_POST['name'] ?? '');
    $orders[$index]['customer']['email']   = trim($_POST['email'] ?? '');
    $orders[$index]['customer']['phone']   = trim($_POST['phone'] ?? '');
    $orders[$index]['customer']['address'] = trim($_POST['address'] ?? '');
    if (isset($_POST['total'])) {
        $orders[$index]['total'] = trim($_POST['total']);
    }
    $orders[$index]['status'] = trim($_POST['status'] ?? ($orders[$index]['status'] ?? 'pending'));

    if (saveOrders($orders)) {
        setMessage('Commande mise à jour avec succès.');
        $edit_order_index = null;
        $edit_order_data = null;
    } else {
        setError('Erreur lors de la mise à jour de la commande.');
        $edit_order_index = $index;
        $edit_order_data = $orders[$index];
    }
}

function handleDeleteOrder() {
    global $orders;
    $index = (int)($_POST['index'] ?? -1);
    if (!isset($orders[$index])) {
        setError('Commande introuvable pour suppression.');
        return;
    }
    unset($orders[$index]);
    $orders = array_values($orders);
    if (saveOrders($orders)) {
        setMessage('Commande supprimée avec succès.');
    } else {
        setError('Erreur lors de la suppression de la commande.');
    }
}

function handleFulfillOrder() {
    global $orders;
    $index = (int)($_POST['index'] ?? -1);
    if (!isset($orders[$index])) {
        setError('Commande introuvable pour exécution.');
        return;
    }

    $order = $orders[$index];
    $order['status'] = 'fulfilled';
    $archiveName = 'order_' . ($order['id'] ?? ('idx'.$index)) . '_' . date('Ymd_His') . '.zip';
    $zipPath = ORDERS_ARCHIVE_DIR . $archiveName;

    if (!class_exists('ZipArchive')) {
        setError("ZipArchive non disponible sur le serveur. Impossible d'archiver la commande.");
        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
        // Store the order JSON inside the zip
        $zip->addFromString('order.bin', json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        // Remove from active orders.bin to save space
        unset($orders[$index]);
        $orders = array_values($orders);

        if (saveOrders($orders)) {
            setMessage('Commande archivée (ZIP) et marquée comme exécutée.');
        } else {
            setError("Commande archivée, mais échec d'écriture dans orders.bin.");
        }
    } else {
        setError("Impossible de créer l'archive ZIP.");
    }
}

// Process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actions = [
        // Contact & About
        'update_contact'     => 'handleUpdateContact',
        'update_about'       => 'handleUpdateAbout',
        // Plants 
        'add_plant'          => 'handleAddPlant',
        'start_edit_plant'   => 'handleStartEditPlant',
        'edit_plant'         => 'handleEditPlant',
        'delete_plant'       => 'handleDeletePlant',
        // Categories
        'add_category'       => 'handleAddCategory',
        'delete_category'    => 'handleDeleteCategory',
        // Orders
        'start_edit_order'   => 'handleStartEditOrder',
        'edit_order'         => 'handleEditOrder',
        'delete_order'       => 'handleDeleteOrder',
        'fulfill_order'      => 'handleFulfillOrder'
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
   // Prepare availability flags for form checkboxes
    $availability = [];
    foreach (PLANT_SIZES as $size) {
        $availability[$size] = isset($plant['prices'][$size]) && $plant['prices'][$size]['available'];
    }
    
    return [
        'plant' => $plant,
        'availability' => $availability
    ];
}

$storage = getStorageInfo();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-bottom: 20px; }
        .tabs { border-bottom: 1px solid #ccc; margin-bottom: 20px; }
        .tab { display: inline-block; padding: 10px 15px; margin-right: 5px; background: #f0f0f0; cursor: pointer; border: 1px solid #ccc; }
        .tab.active { background: #fff; border-bottom: 1px solid #fff; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; }
        .btn { padding: 8px 15px; background: #007bff; color: white; border: none; cursor: pointer; margin-right: 10px; }
        .btn-danger { background: #dc3545; }
        .btn-secondary { background: #6c757d; }
        .alert { padding: 10px; margin-bottom: 15px; border: 1px solid; }
        .alert.success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .plant-item { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; }
        .size-inputs { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
        .size-group { border: 1px solid #ddd; padding: 10px; }
        textarea { height: 100px; resize: vertical; }
        .checkbox-group { display: flex; align-items: center; gap: 5px; }
        .storage-info { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            align-items: center;
        }
        .storage-chart { width: 300px; height: 300px; }
        .storage-details h3 { margin-top: 0; }
        .storage-stat { margin: 10px 0; }
        .image-gallery { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0; }
        .image-item { position: relative; display: inline-block; }
        .image-item img { width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; }
        .image-delete { 
            position: absolute; 
            top: 5px; 
            right: 5px; 
            background: #dc3545; 
            color: white; 
            border: none; 
            border-radius: 50%; 
            width: 20px; 
            height: 20px; 
            cursor: pointer; 
            font-size: 12px;
        }
        .file-upload { border: 2px dashed #ddd; padding: 20px; text-align: center; margin: 10px 0; }
        .file-upload.dragover { border-color: #007bff; background: #f8f9fa; }</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <a href="logout.php" style="float: right;">Logout</a>
        </div>

  <!-- Storage Information -->
        <div class="storage-info">
            <div>
                <canvas id="storageChart" class="storage-chart"></canvas>
            </div>
            <div class="storage-details">
                <h3>Stockage des Images</h3>
                <div class="storage-stat"><strong>Utilisé:</strong> <?php echo $storage['used_mb']; ?> MB (<?php echo $storage['used_percentage']; ?>%)</div>
                <div class="storage-stat"><strong>Disponible:</strong> <?php echo $storage['available_mb']; ?> MB</div>
                <div class="storage-stat"><strong>Total:</strong> <?php echo $storage['max_mb']; ?> MB</div>
                <div class="storage-stat"><strong>Nombre de fichiers:</strong> <?php echo $storage['file_count']; ?></div>
            </div>
        </div>


        <?php if ($message): ?>
            <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="showTab('plants')">Plants</div>
            <div class="tab" onclick="showTab('categories')">Categories</div>
            <div class="tab" onclick="showTab('contact')">Contact</div>
            <div class="tab" onclick="showTab('about')">About</div>
            <div class="tab" onclick="showTab('orders')">Orders</div>
        </div>

        <!-- Plants Tab -->
        <div id="plants" class="tab-content active">
            <h2>Manage Plants</h2>
            
            <?php if ($edit_plant_index !== null): ?>
                <h3>Edit Plant</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_plant">
                    <input type="hidden" name="index" value="<?php echo $edit_plant_index; ?>">
                    
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_plant_data['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($edit_plant_data['category'] ?? '') === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                       
                    <div class="form-group">
                        <label>Current Images</label>
                        <?php if (!empty($edit_plant_data['photos'])): ?>
                            <div class="image-gallery">
                                <?php foreach ($edit_plant_data['photos'] as $photo): ?>
                                    <div class="image-item">
                                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="Plant image">
                                        <button type="button" class="image-delete" onclick="markForDeletion(this, '<?php echo htmlspecialchars( $photo );
        ?>')">×</button>
                                        <input type="hidden" name="existing_images[]" value="<?php echo htmlspecialchars($photo); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No images uploaded.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
    <label>Add New Images</label>
    <div class="file-upload" id="uploadBoxEdit"
         onclick="document.getElementById('plant_images_edit').click()"
         ondragover="event.preventDefault(); this.classList.add('dragover');"
         ondragleave="this.classList.remove('dragover');"
         ondrop="handleDrop(event, 'plant_images_edit')">
        <p>Click to select images or drag and drop</p>
        <p><small>Max <?php echo MAX_FILE_SIZE; ?>MB per file. JPG, PNG, GIF, WebP allowed.</small></p>
    </div>
    <input type="file" id="plant_images_edit" name="plant_images[]" multiple accept="image/*" style="display: none;">
    <div id="previewEdit" class="image-gallery"></div>
</div>

<script>
function handleDrop(event, inputId) {
    event.preventDefault();
    event.currentTarget.classList.remove('dragover');

    const input = document.getElementById(inputId);
    const files = event.dataTransfer.files;

    // Preview images
    const previewContainer = document.getElementById('previewEdit');
    previewContainer.innerHTML = '';
    Array.from(files).forEach(file => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                img.style.border = '1px solid #ccc';
                img.style.marginRight = '10px';
                previewContainer.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    });

    // Attach dropped files to input
    const dataTransfer = new DataTransfer();
    Array.from(files).forEach(file => dataTransfer.items.add(file));
    input.files = dataTransfer.files;
}
</script>

                    
                    <div class="form-group">
                        <label>Prices & Availability</label>
                        <div class="size-inputs">
                            <?php foreach (PLANT_SIZES as $size): ?>
                                <div class="size-group">
                                    <strong><?php echo $size; ?></strong>
                                    <input type="text" name="price_<?php echo $size; ?>" placeholder="Price" class="form-control" style="margin: 5px 0;" 
                                           value="<?php echo htmlspecialchars($edit_plant_data['prices'][$size]['price'] ?? ''); ?>">
                                    <div class="checkbox-group">
                                        <input type="checkbox" name="available_<?php echo $size; ?>" value="1" 
                                               <?php echo ($edit_plant_data['_availability'][$size] ?? false) ? 'checked' : ''; ?>>
                                        <label>Available</label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Plant Details</label>
                        <?php foreach (DEFAULT_PLANT_DETAILS as $field => $default): ?>
                            <div style="margin-bottom: 10px;">
                                <label><?php echo ucfirst(str_replace('_', ' ', $field)); ?></label>
                                <input type="text" name="<?php echo $field; ?>" class="form-control" 
                                       value="<?php echo htmlspecialchars($edit_plant_data['details'][$field] ?? ''); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" class="btn">Update Plant</button>
                    <a href="?" class="btn btn-secondary">Cancel</a>
                </form>
            <?php else: ?>
                <h3>Add New Plant</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_plant">
                    
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Plant Images</label>
                        <div class="file-upload" onclick="document.getElementById('plant_images').click()">
                            <p>Click to select images or drag and drop</p>
                            <p><small>Max <?php echo MAX_FILE_SIZE; ?>MB per file. JPG, PNG, GIF, WebP allowed.</small></p>
                        </div>
                        <input type="file" id="plant_images" name="plant_images[]" multiple accept="image/*" style="display: none;">
                        <div id="image_preview"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Prices & Availability</label>
                        <div class="size-inputs">
                            <?php foreach (PLANT_SIZES as $size): ?>
                                <div class="size-group">
                                    <strong><?php echo $size; ?></strong>
                                    <input type="text" name="price_<?php echo $size; ?>" placeholder="Price" class="form-control" style="margin: 5px 0;">
                                    <div class="checkbox-group">
                                        <input type="checkbox" name="available_<?php echo $size; ?>" value="1">
                                        <label>Available</label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Plant Details</label>
                        <?php foreach (DEFAULT_PLANT_DETAILS as $field => $default): ?>
                            <div style="margin-bottom: 10px;">
                                <label><?php echo ucfirst(str_replace('_', ' ', $field)); ?></label>
                                <input type="text" name="<?php echo $field; ?>" class="form-control">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" class="btn">Add Plant</button>
                </form>
            <?php endif; ?>
            
            <h3>Existing Plants (<?php echo count($catalogue); ?>)</h3>
            <?php if (empty($catalogue)): ?>
                <p>No plants in catalogue.</p>
            <?php else: ?>
                <?php foreach ($catalogue as $index => $plant): ?>
                    <div class="plant-item">
                        <h4><?php echo htmlspecialchars($plant['name']); ?></h4>
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($plant['category'] ?? 'Uncategorized'); ?></p>
                        <p><strong>Photos:</strong> <?php echo count($plant['photos']); ?> images</p>
                        <p><strong>Available sizes:</strong> 
                            <?php 
                            $available_sizes = [];
                            foreach ($plant['prices'] as $size => $data) {
                                if ($data['available']) {
                                    $available_sizes[] = $size . ' ( ' . $data['price'] . ' )';
                                }
                            }
                            echo $available_sizes ? implode(', ', $available_sizes) : 'None';
                            ?>
                        </p>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="start_edit_plant">
                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                            <button type="submit" class="btn">Edit</button>
                        </form>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this plant?');">
                            <input type="hidden" name="action" value="delete_plant">
                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Categories Tab -->
        <div id="categories" class="tab-content">
            <h2>Manage Categories</h2>
            
            <h3>Add Category</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="category_name" class="form-control" required>
                </div>
                <button type="submit" class="btn">Add Category</button>
            </form>
            
            <h3>Existing Categories</h3>
            <?php if (empty($categories)): ?>
                <p>No categories defined.</p>
            <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                    <div style="padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <span><?php echo htmlspecialchars($cat); ?></span>
                        <form method="POST" onsubmit="return confirm('Delete category and remove from all plants?');">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="category_name" value="<?php echo htmlspecialchars($cat); ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Contact Tab -->
        <div id="contact" class="tab-content">
            <h2>Contact Information</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_contact">
                
                <?php foreach (DEFAULT_CONTACT as $field => $default): ?>
                    <div class="form-group">
                        <label><?php echo ucfirst($field); ?></label>
                        <input type="text" name="<?php echo $field; ?>" class="form-control" 
                               value="<?php echo htmlspecialchars($contact_data[$field] ?? ''); ?>">
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" class="btn">Update Contact Info</button>
            </form>
        </div>

        <!-- About Tab -->
        <div id="about" class="tab-content">
            <h2>About Section</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_about">
                
                <div class="form-group">
                    <label>Header</label>
                    <input type="text" name="about_header" class="form-control" 
                           value="<?php echo htmlspecialchars($about_data['header'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="about_content" class="form-control" rows="10"><?php echo htmlspecialchars($about_data['content'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn">Update About Section</button>
            </form>
        </div>

        <!-- Orders Tab -->
        <div id="orders" class="tab-content">
            <h2>Orders</h2>

            <?php if ($edit_order_index !== null): ?>
                <h3>Edit Order</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_order">
                    <input type="hidden" name="index" value="<?php echo $edit_order_index; ?>">

                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" name="name" class="form-control"
                            value="<?php echo htmlspecialchars($edit_order_data['customer']['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Customer Email</label>
                        <input type="email" name="email" class="form-control"
                            value="<?php echo htmlspecialchars($edit_order_data['customer']['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Customer Phone</label>
                        <input type="text" name="phone" class="form-control"
                            value="<?php echo htmlspecialchars($edit_order_data['customer']['phone'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Customer Address</label>
                        <input type="text" name="address" class="form-control"
                            value="<?php echo htmlspecialchars($edit_order_data['customer']['address'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Total (TND)</label>
                        <input type="text" name="total" class="form-control"
                            value="<?php echo htmlspecialchars($edit_order_data['total'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <?php $curr = $edit_order_data['status'] ?? 'pending'; ?>
                        <select name="status" class="form-control">
                            <option value="pending"   <?php echo $curr==='pending'?'selected':''; ?>>pending</option>
                            <option value="paid"      <?php echo $curr==='paid'?'selected':''; ?>>paid</option>
                            <option value="fulfilled" <?php echo $curr==='fulfilled'?'selected':''; ?>>fulfilled</option>
                            <option value="canceled"  <?php echo $curr==='canceled'?'selected':''; ?>>canceled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Items (read-only)</label>
                        <div class="plant-item" style="max-height:220px; overflow:auto; background:#fafafa;">
                            <pre style="white-space:pre-wrap;"><?php echo htmlspecialchars(json_encode($edit_order_data['cart'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                        </div>
                    </div>

                    <button type="submit" class="btn">Update Order</button>
                    <a href="?" class="btn btn-secondary">Cancel</a>
                </form>
            <?php else: ?>
                <h3>Existing Orders (<?php echo count($orders); ?>)</h3>
                <?php if (empty($orders)): ?>
                    <p>No orders found.</p>
                <?php else: ?>
                    <?php foreach ($orders as $index => $order): ?>
                        <div class="plant-item">
                            <h4>Order: <?php echo htmlspecialchars($order['id'] ?? ('#'.($index+1))); ?></h4>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($order['date'] ?? ''); ?></p>
                            <p><strong>Status:</strong> <?php echo htmlspecialchars($order['status'] ?? 'pending'); ?></p>
                            <p><strong>Customer:</strong> 
                                <?php echo htmlspecialchars(($order['customer']['name'] ?? '') . ' | ' . ($order['customer']['email'] ?? '') . ' | ' . ($order['customer']['phone'] ?? '')); ?>
                            </p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($order['customer']['address'] ?? ''); ?></p>
                            <p><strong>Total:</strong> <?php echo htmlspecialchars($order['total'] ?? '0'); ?> TND</p>
                            <p><strong>Items:</strong> <?php echo count($order['cart'] ?? []); ?></p>

                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="start_edit_order">
                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                <button type="submit" class="btn">Edit</button>
                            </form>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Fulfill this order? It will be archived as ZIP and removed from active orders.');">
                                <input type="hidden" name="action" value="fulfill_order">
                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                <button type="submit" class="btn">Fulfill</button>
                            </form>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this order?');">
                                <input type="hidden" name="action" value="delete_order">
                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('storageChart').getContext('2d');
        const storageChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Utilisé ( MB )', 'Disponible ( MB )'],
                datasets: [{
                    data: [<?php echo $storage['used_mb']; ?>, <?php echo $storage['available_mb']; ?>],
                    backgroundColor: ['#007bff', '#e0e0e0']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        function showTab(tabName) {
            // Hide all tab contents
            var contents = document.querySelectorAll('.tab-content');
            contents.forEach(function(content) {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            var tabs = document.querySelectorAll('.tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active' );
        }

        document.addEventListener('DOMContentLoaded', function() {
        // Handle file input change for add plant form
        const plantImagesInput = document.getElementById('plant_images');
        if (plantImagesInput) {
            plantImagesInput.addEventListener('change', function() {
                previewImages(this, 'image_preview');
            });
        }
        
        // Handle file input change for edit plant form
        const plantImagesEditInput = document.getElementById('plant_images_edit');
            if (plantImagesEditInput) {
                plantImagesEditInput.addEventListener('change', function() {
                    previewImages(this, 'previewEdit');
                });
            }
        });

        function previewImages(input, previewContainerId) {
        const previewContainer = document.getElementById(previewContainerId);
        previewContainer.innerHTML = '';
        
        if (input.files) {
            Array.from(input.files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '100px';
                        img.style.height = '100px';
                        img.style.objectFit = 'cover';
                        img.style.border = '1px solid #ccc';
                        img.style.marginRight = '10px';
                        previewContainer.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    }

        function markForDeletion(button, imagePath) {
        const imageItem = button.parentElement;
        
        // Create hidden input for deletion
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_images[]';
        deleteInput.value = imagePath;
        
        // Add to form
        button.closest('form').appendChild(deleteInput);
        
        // Mark visually as deleted
        imageItem.style.opacity = '0.5';
        imageItem.style.position = 'relative';
        
        // Add "DELETED" overlay
        const overlay = document.createElement('div');
        overlay.textContent = 'DELETED';
        overlay.style.position = 'absolute';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.right = '0';
        overlay.style.bottom = '0';
        overlay.style.backgroundColor = 'rgba(220, 53, 69, 0.8)';
        overlay.style.color = 'white';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.fontSize = '12px';
        overlay.style.fontWeight = 'bold';
        
        imageItem.appendChild(overlay);
        
        // Replace delete button with undo button
        button.textContent = '↶';
        button.onclick = function() { undoDeletion(button, imagePath, deleteInput, overlay); };
    }

        function undoDeletion(button, imagePath, deleteInput, overlay) {
            const imageItem = button.parentElement;
            
            // Remove delete input
            deleteInput.remove();
            
            // Remove visual indicators
            imageItem.style.opacity = '1';
            overlay.remove();
            
            // Restore original delete button
            button.textContent = '×';
            button.onclick = function() { markForDeletion(button, imagePath); };
        }

        // Enhanced drag and drop functionality
        function handleDrop(event, inputId) {
            event.preventDefault();
            event.currentTarget.classList.remove('dragover');
            
            const input = document.getElementById(inputId);
            const files = event.dataTransfer.files;
            
            // Set files to input
            input.files = files;
            
            // Trigger preview
            const previewContainerId = inputId === 'plant_images' ? 'image_preview' : 'previewEdit';
            previewImages(input, previewContainerId);
        }

        // Add drag and drop to add plant form
        const uploadBox = document.querySelector('#plants .file-upload');
        if (uploadBox) {
            uploadBox.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            uploadBox.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            uploadBox.addEventListener('drop', function(e) {
                handleDrop(e, 'plant_images');
            });
        }
    </script>
</body>
</html>