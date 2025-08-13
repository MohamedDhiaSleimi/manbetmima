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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <a href="logout.php" style="float: right;">Logout</a>
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
        </div>

        <!-- Plants Tab -->
        <div id="plants" class="tab-content active">
            <h2>Manage Plants</h2>
            
            <?php if ($edit_plant_index !== null): ?>
                <h3>Edit Plant</h3>
                <form method="POST">
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
                        <label>Photos (one URL per line)</label>
                        <textarea name="photos" class="form-control"><?php echo htmlspecialchars($edit_plant_data['_photos_text'] ?? ''); ?></textarea>
                    </div>
                    
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
                <form method="POST">
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
                        <label>Photos (one URL per line)</label>
                        <textarea name="photos" class="form-control"></textarea>
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
                                    $available_sizes[] = $size . ' (' . $data['price'] . ')';
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
    </div>

    <script>
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
            event.target.classList.add('active');
        }
    </script>
</body>
</html>