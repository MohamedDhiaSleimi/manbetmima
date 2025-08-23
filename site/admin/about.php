<?php
global $about_data;
?>

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