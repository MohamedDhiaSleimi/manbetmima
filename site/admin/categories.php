<?php
global $categories;
?>

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