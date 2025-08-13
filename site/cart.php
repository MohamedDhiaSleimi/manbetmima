<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mon Panier - Catalogue de Plantes</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .cart-item {
      border: none;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: all 0.2s;
      overflow: hidden;
    }
    
    .cart-item:hover {
      box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    .item-image {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: 0.5rem;
    }
    
    .item-image-fallback {
      width: 100px;
      height: 100px;
      background: linear-gradient(135deg, #81c784, #66bb6a);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 0.9rem;
      border-radius: 0.5rem;
      text-align: center;
      padding: 0.5rem;
    }
    
    .category-badge {
      background: linear-gradient(135deg, #4caf50, #66bb6a);
      color: white;
      font-size: 0.75rem;
      padding: 0.25rem 0.75rem;
      border-radius: 1rem;
      font-weight: 600;
    }
    
    .quantity-control {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .quantity-btn {
      width: 35px;
      height: 35px;
      border: 2px solid #4caf50;
      background: white;
      color: #4caf50;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s;
      font-weight: bold;
    }
    
    .quantity-btn:hover {
      background: #4caf50;
      color: white;
    }
    
    .quantity-btn:disabled {
      border-color: #ccc;
      color: #ccc;
      cursor: not-allowed;
    }
    
    .quantity-btn:disabled:hover {
      background: white;
      color: #ccc;
    }
    
    .quantity-display {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      border-radius: 0.5rem;
      padding: 0.5rem 1rem;
      font-weight: bold;
      min-width: 60px;
      text-align: center;
    }
    
    .remove-btn {
      color: #dc3545;
      border: none;
      background: none;
      font-size: 1.2rem;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .remove-btn:hover {
      color: #c82333;
      transform: scale(1.1);
    }
    
    .cart-summary {
      background: linear-gradient(135deg, #f8f9fa, #e9ecef);
      border-radius: 1rem;
      padding: 2rem;
      box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    }
    
    .total-price {
      font-size: 1.5rem;
      font-weight: bold;
      color: #4caf50;
    }
    
    .checkout-btn {
      background: linear-gradient(135deg, #4caf50, #66bb6a);
      border: none;
      color: white;
      font-weight: 600;
      padding: 1rem 2rem;
      font-size: 1.1rem;
      border-radius: 0.75rem;
      transition: all 0.3s;
    }
    
    .checkout-btn:hover {
      background: linear-gradient(135deg, #388e3c, #4caf50);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
      color: white;
    }
    
    .continue-shopping-btn {
      background: linear-gradient(135deg, #6c757d, #868e96);
      border: none;
      color: white;
      font-weight: 600;
      padding: 0.75rem 1.5rem;
      border-radius: 0.5rem;
      transition: all 0.3s;
    }
    
    .continue-shopping-btn:hover {
      background: linear-gradient(135deg, #5a6268, #6c757d);
      transform: translateY(-1px);
      color: white;
    }
    
    .empty-cart {
      text-align: center;
      padding: 4rem 2rem;
    }
    
    .empty-cart i {
      font-size: 4rem;
      color: #6c757d;
      margin-bottom: 1rem;
    }
    
    .back-btn {
      position: fixed;
      top: 2rem;
      left: 2rem;
      z-index: 1000;
      background: rgba(255, 255, 255, 0.9);
      border: 2px solid #4caf50;
      color: #4caf50;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      transition: all 0.3s;
      backdrop-filter: blur(10px);
    }
    
    .back-btn:hover {
      background: #4caf50;
      color: white;
      transform: scale(1.1);
    }

    @media (max-width: 768px) {
      .cart-item .row {
        text-align: center;
      }
      
      .item-image,
      .item-image-fallback {
        margin: 0 auto 1rem;
      }
      
      .quantity-control {
        justify-content: center;
        margin: 1rem 0;
      }
    }
  </style>
</head>
<body>

<!-- Back to catalog button -->
<a href="index" class="back-btn" title="Retour au catalogue">
  <i class="fas fa-arrow-left"></i>
</a>

<div class="container py-4">
  <div class="row">
    <div class="col-12">
      <h1 class="text-center mb-4">
        <i class="fas fa-shopping-cart me-2"></i>Mon Panier
      </h1>
    </div>
  </div>
  
  <div class="row">
    <div class="col-lg-8">
      <div id="cartItems">
        <!-- Cart items will be loaded here -->
      </div>
    </div>
    
    <div class="col-lg-4">
      <div class="cart-summary" id="cartSummary">
        <h4 class="mb-3">Résumé de la commande</h4>
        <div class="d-flex justify-content-between mb-2">
          <span>Sous-total:</span>
          <span id="subtotal">0.00 TND</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>Frais de livraison:</span>
          <span id="shipping">5.00 TND</span>
        </div>
        <hr>
        <div class="d-flex justify-content-between mb-3">
          <strong>Total:</strong>
          <strong class="total-price" id="total">5.00 TND</strong>
        </div>
        
        <button class="btn checkout-btn w-100 mb-2" id="checkoutBtn">
          <i class="fas fa-credit-card me-2"></i>Procéder au paiement
        </button>
        
        <button class="btn continue-shopping-btn w-100" onclick="window.location.href='index'">
          <i class="fas fa-arrow-left me-2"></i>Continuer mes achats
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Empty cart message -->
<div class="container" id="emptyCart" style="display: none;">
  <div class="empty-cart">
    <i class="fas fa-shopping-cart"></i>
    <h3 class="mb-3">Votre panier est vide</h3>
    <p class="text-muted mb-4">Découvrez notre belle collection de plantes et commencez votre jardin!</p>
    <button class="btn checkout-btn" onclick="window.location.href='index'">
      <i class="fas fa-seedling me-2"></i>Découvrir nos plantes
    </button>
  </div>
</div>

<!-- Toast for notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="cartToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <i class="fas fa-info-circle text-info me-2"></i>
      <strong class="me-auto">Panier</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body" id="toastMessage">
      Message
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
  let cart = JSON.parse(localStorage.getItem('plantCart') || '[]');
  const SHIPPING_COST = 5.00;
  const FREE_SHIPPING_THRESHOLD = 100.00;

  // Load cart on page load
  document.addEventListener('DOMContentLoaded', function() {
    displayCart();
    updateSummary();
  });

  function displayCart() {
    const cartItemsContainer = document.getElementById('cartItems');
    const emptyCartContainer = document.getElementById('emptyCart');
    const cartSummaryContainer = document.getElementById('cartSummary');

    if (cart.length === 0) {
      cartItemsContainer.innerHTML = '';
      emptyCartContainer.style.display = 'block';
      cartSummaryContainer.style.display = 'none';
      return;
    }

    emptyCartContainer.style.display = 'none';
    cartSummaryContainer.style.display = 'block';

    cartItemsContainer.innerHTML = cart.map((item, index) => `
      <div class="card cart-item mb-3">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-md-2 col-sm-3">
              ${item.image ? 
                `<img src="${item.image}" class="item-image" alt="${item.name}" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                 <div class="item-image-fallback" style="display:none;">${item.name}</div>` :
                `<div class="item-image-fallback">${item.name}</div>`
              }
            </div>
            
            <div class="col-md-4 col-sm-9">
              <h6 class="mb-1">${item.name}</h6>
              ${item.category ? `<span class="category-badge">${item.category}</span>` : ''}
              <p class="text-muted mb-1">Taille: ${item.size}</p>
              <p class="text-success mb-0 fw-bold">${item.price.toFixed(2)} TND</p>
            </div>
            
            <div class="col-md-3 col-sm-6">
              <div class="quantity-control">
                <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.quantity - 1})" 
                        ${item.quantity <= 1 ? 'disabled' : ''}>
                  <i class="fas fa-minus"></i>
                </button>
                <div class="quantity-display">${item.quantity}</div>
                <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.quantity + 1})">
                  <i class="fas fa-plus"></i>
                </button>
              </div>
            </div>
            
            <div class="col-md-2 col-sm-4">
              <div class="text-center">
                <p class="fw-bold mb-1">${(item.price * item.quantity).toFixed(2)} TND</p>
                <button class="remove-btn" onclick="removeFromCart(${index})" title="Supprimer">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    `).join('');
  }

  function updateQuantity(index, newQuantity) {
    if (newQuantity <= 0) {
      removeFromCart(index);
      return;
    }

    cart[index].quantity = newQuantity;
    saveCart();
    displayCart();
    updateSummary();
    showToast(`Quantité mise à jour pour ${cart[index].name}`);
  }

  function removeFromCart(index) {
    const itemName = cart[index].name;
    cart.splice(index, 1);
    saveCart();
    displayCart();
    updateSummary();
    showToast(`${itemName} supprimé du panier`, 'info');
  }

  function updateSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const shipping = subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_COST;
    const total = subtotal + shipping;

    document.getElementById('subtotal').textContent = `${subtotal.toFixed(2)} TND`;
    
    const shippingElement = document.getElementById('shipping');
    if (shipping === 0) {
      shippingElement.innerHTML = '<span class="text-success">Gratuit</span>';
    } else {
      shippingElement.textContent = `${shipping.toFixed(2)} TND`;
    }
    
    document.getElementById('total').textContent = `${total.toFixed(2)} TND`;

    // Update checkout button
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (cart.length === 0) {
      checkoutBtn.disabled = true;
      checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Panier vide';
    } else {
      checkoutBtn.disabled = false;
      checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Procéder au paiement';
    }

    // Show free shipping message
    if (subtotal > 0 && subtotal < FREE_SHIPPING_THRESHOLD) {
      const remaining = FREE_SHIPPING_THRESHOLD - subtotal;
      showToast(`Ajoutez ${remaining.toFixed(2)} TND pour bénéficier de la livraison gratuite!`, 'info');
    }
  }

  function saveCart() {
    localStorage.setItem('plantCart', JSON.stringify(cart));
  }

  function clearCart() {
    cart = [];
    saveCart();
    displayCart();
    updateSummary();
    showToast('Panier vidé', 'info');
  }

  function showToast(message, type = 'success') {
    const toast = document.getElementById('cartToast');
    const toastMessage = document.getElementById('toastMessage');
    const toastHeader = toast.querySelector('.toast-header');
    
    toastMessage.textContent = message;
    
    // Change icon and color based on type
    const icon = toastHeader.querySelector('i');
    switch(type) {
      case 'error':
        icon.className = 'fas fa-exclamation-circle text-danger me-2';
        break;
      case 'info':
        icon.className = 'fas fa-info-circle text-info me-2';
        break;
      case 'warning':
        icon.className = 'fas fa-exclamation-triangle text-warning me-2';
        break;
      default:
        icon.className = 'fas fa-check-circle text-success me-2';
    }
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
  }

  // Checkout functionality
  document.getElementById('checkoutBtn').addEventListener('click', function() {
    if (cart.length === 0) return;

    // Simple checkout simulation
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const shipping = total >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_COST;
    const finalTotal = total + shipping;

    const confirmed = confirm(`Confirmer votre commande?\n\nTotal: ${finalTotal.toFixed(2)} TND\nNombre d'articles: ${cart.reduce((sum, item) => sum + item.quantity, 0)}`);
    
    if (confirmed) {
      // In a real application, this would send the order to a server
      showToast('Commande confirmée! Vous allez recevoir un email de confirmation.', 'success');
      
      // Clear cart after successful order
      setTimeout(() => {
        clearCart();
        window.location.href = 'index';
      }, 2000);
    }
  });

  // Add keyboard shortcuts
  document.addEventListener('keydown', function(e) {
    // Press 'C' to clear cart (with confirmation)
    if (e.key.toLowerCase() === 'c' && e.ctrlKey) {
      e.preventDefault();
      if (cart.length > 0 && confirm('Voulez-vous vraiment vider votre panier?')) {
        clearCart();
      }
    }
    
    // Press Escape to go back to catalog
    if (e.key === 'Escape') {
      window.location.href = 'index';
    }
  });
</script>

</body>
</html>