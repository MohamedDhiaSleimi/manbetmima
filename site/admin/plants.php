<?php
global $catalogue, $edit_plant_index, $edit_plant_data, $categories;
?>

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
                            <button type="button" class="image-delete" onclick="markForDeletion(this, '<?php echo htmlspecialchars($photo); ?>')">×</button>
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

<script>
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
</script>