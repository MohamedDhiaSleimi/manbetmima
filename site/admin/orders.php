<?php
global $orders, $edit_order_index, $edit_order_data;
?>

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
            <label>Order Items</label>
            <div class="order-items">
                <?php 
                $cart = $edit_order_data['cart'] ?? [];
                $total = 0;
                foreach ($cart as $index => $item): 
                    $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                    $total += $itemTotal;
                ?>
                    <div class="cart-item-editable">
                        <div><strong><?php echo htmlspecialchars($item['name'] ?? 'Unknown Item'); ?></strong></div>
                        <div>Size: <?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></div>
                        <div>Price: <?php echo htmlspecialchars($item['price'] ?? 0); ?> TND</div>
                        <div class="cart-item-controls">
                            <label>Quantity:</label>
                            <input type="number" name="cart[<?php echo $index; ?>][quantity]" 
                                   value="<?php echo htmlspecialchars($item['quantity'] ?? 1); ?>" min="1">
                            <input type="hidden" name="cart[<?php echo $index; ?>][name]" 
                                   value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
                            <input type="hidden" name="cart[<?php echo $index; ?>][size]" 
                                   value="<?php echo htmlspecialchars($item['size'] ?? ''); ?>">
                            <input type="hidden" name="cart[<?php echo $index; ?>][price]" 
                                   value="<?php echo htmlspecialchars($item['price'] ?? 0); ?>">
                            <input type="hidden" name="cart[<?php echo $index; ?>][image]" 
                                   value="<?php echo htmlspecialchars($item['image'] ?? ''); ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
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
                
                <div class="order-items">
                    <?php foreach ($order['cart'] ?? [] as $item): ?>
                        <div class="order-item">
                            <div class="order-item-details">
                                <strong><?php echo htmlspecialchars($item['name'] ?? 'Unknown Item'); ?></strong>
                                <div>Size: <?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></div>
                                <div>Quantity: <?php echo htmlspecialchars($item['quantity'] ?? 1); ?></div>
                            </div>
                            <div class="order-item-price">
                                <?php echo htmlspecialchars(($item['price'] ?? 0) * ($item['quantity'] ?? 1)); ?> TND
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

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