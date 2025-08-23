<?php
// File paths
const CATALOGUE_FILE = './storage/binary/catalogue.bin';
const CATEGORIES_FILE = './storage/binary/categories.bin';
const CONTACT_FILE = './storage/binary/contact.bin';
const ABOUT_FILE = './storage/binary/about.bin';
const ORDER_FILE = './storage/binary/orders.bin';
define('UPLOADS_DIR', __DIR__ . '/../uploads/plants/');

// Ensure uploads directory exists
if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0777, true);
}

// Orders archive directory (fulfilled orders as ZIP)
define('ORDERS_ARCHIVE_DIR', __DIR__ . '/../orders_archive/');
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

function saveOrders($orders) {
    return saveDataFile(ORDER_FILE, $orders);
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
        setError('Le nom de la catégorie ne peut pas être empty.');
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

    // Update customer fields & total/status (keep cart immutable)
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
?>