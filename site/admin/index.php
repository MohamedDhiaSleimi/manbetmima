<?php
session_start();

// Security check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit();
}

// Include functions
require_once 'includes/functions.php';

// Store active tab in session
$active_tab = $_SESSION['active_tab'] ?? 'plants';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set active tab based on the action
    $action = $_POST['action'];
    if (strpos($action, 'plant') !== false) {
        $active_tab = 'plants';
    } elseif (strpos($action, 'category') !== false) {
        $active_tab = 'categories';
    } elseif (strpos($action, 'contact') !== false) {
        $active_tab = 'contact';
    } elseif (strpos($action, 'about') !== false) {
        $active_tab = 'about';
    } elseif (strpos($action, 'order') !== false || $action === 'fulfill_order') {
        $active_tab = 'orders';
    }
    $_SESSION['active_tab'] = $active_tab;
}

// Initialize data
$contact_data = loadDataFile(CONTACT_FILE, DEFAULT_CONTACT);
$about_data = loadDataFile(ABOUT_FILE, DEFAULT_ABOUT);
$catalogue = loadDataFile(CATALOGUE_FILE, []);
$orders = loadDataFile(ORDER_FILE, []);
$edit_plant_index = null;
$edit_plant_data = null;
$edit_order_index = null;
$edit_order_data = null;

// Initialize categories with fallback logic
if (file_exists(CATEGORIES_FILE)) {
    $categories = loadDataFile(CATEGORIES_FILE, []);
} else {
    $unique_categories = [];
    foreach ($catalogue as $plant) {
        if (!empty($plant['category']) && !in_array($plant['category'], $unique_categories)) {
            $unique_categories[] = $plant['category'];
        }
    }
    $categories = $unique_categories;
    saveDataFile(CATEGORIES_FILE, $categories);
}

// Process actions
$actions = [
    // Contact & About
    'update_contact'   => ['fn' => 'handleUpdateContact', 'redirect' => true],
    'update_about'     => ['fn' => 'handleUpdateAbout',   'redirect' => true],

    // Plants
    'add_plant'        => ['fn' => 'handleAddPlant',      'redirect' => true],
    'start_edit_plant' => ['fn' => 'handleStartEditPlant','redirect' => false],
    'edit_plant'       => ['fn' => 'handleEditPlant',     'redirect' => true],
    'delete_plant'     => ['fn' => 'handleDeletePlant',   'redirect' => true],

    // Categories
    'add_category'     => ['fn' => 'handleAddCategory',   'redirect' => true],
    'delete_category'  => ['fn' => 'handleDeleteCategory','redirect' => true],

    // Orders
    'start_edit_order' => ['fn' => 'handleStartEditOrder','redirect' => false],
    'edit_order'       => ['fn' => 'handleEditOrder',     'redirect' => true],
    'delete_order'     => ['fn' => 'handleDeleteOrder',   'redirect' => true],
    'fulfill_order'    => ['fn' => 'handleFulfillOrder',  'redirect' => true]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if (isset($actions[$action]) && function_exists($actions[$action]['fn'])) {
        $actions[$action]['fn']();
        if ($actions[$action]['redirect']) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
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
        .file-upload.dragover { border-color: #007bff; background: #f8f9fa; }
        .order-items { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin: 10px 0; }
        .order-item { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee; }
        .order-item:last-child { border-bottom: none; }
        .order-item-details { flex: 2; }
        .order-item-price { flex: 1; text-align: right; }
        .cart-item-editable { background: #f9f9f9; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .cart-item-controls { display: flex; gap: 10px; margin-top: 5px; }
        .cart-item-controls input { width: 60px; }
    </style>
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
            <div class="tab <?php echo $active_tab === 'plants' ? 'active' : ''; ?>" onclick="showTab('plants')">Plants</div>
            <div class="tab <?php echo $active_tab === 'categories' ? 'active' : ''; ?>" onclick="showTab('categories')">Categories</div>
            <div class="tab <?php echo $active_tab === 'contact' ? 'active' : ''; ?>" onclick="showTab('contact')">Contact</div>
            <div class="tab <?php echo $active_tab === 'about' ? 'active' : ''; ?>" onclick="showTab('about')">About</div>
            <div class="tab <?php echo $active_tab === 'orders' ? 'active' : ''; ?>" onclick="showTab('orders')">Orders</div>
        </div>

        <!-- Plants Tab -->
        <div id="plants" class="tab-content <?php echo $active_tab === 'plants' ? 'active' : ''; ?>">
            <?php include 'plants.php'; ?>
        </div>

        <!-- Categories Tab -->
        <div id="categories" class="tab-content <?php echo $active_tab === 'categories' ? 'active' : ''; ?>">
            <?php include 'categories.php'; ?>
        </div>

        <!-- Contact Tab -->
        <div id="contact" class="tab-content <?php echo $active_tab === 'contact' ? 'active' : ''; ?>">
            <?php include 'contact.php'; ?>
        </div>

        <!-- About Tab -->
        <div id="about" class="tab-content <?php echo $active_tab === 'about' ? 'active' : ''; ?>">
            <?php include 'about.php'; ?>
        </div>

        <!-- Orders Tab -->
        <div id="orders" class="tab-content <?php echo $active_tab === 'orders' ? 'active' : ''; ?>">
            <?php include 'orders.php'; ?>
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
            
            // Store active tab in session via AJAX
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'set_active_tab.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('tab=' + tabName);
        }

        // Set initial active tab
        document.addEventListener('DOMContentLoaded', function() {
            showTab('<?php echo $active_tab; ?>');
        });

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
    </script>
</body>
</html>