<?php
global $contact_data;
?>

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