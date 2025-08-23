<?php
// Load catalogue and categories from .bin files
$catalogue = [];
$categories = [];

$catalogueFile =  './storage/binary/catalogue.bin';
$categoriesFile =  './storage/binary/categories.bin';

if (file_exists($catalogueFile)) {
    $catalogue = unserialize(file_get_contents($catalogueFile));
}

if (file_exists($categoriesFile)) {
    $categories = unserialize(file_get_contents($categoriesFile));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catalogue de plants</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .plant-image { width:100%; height:260px; object-fit:cover; border-radius:6px; display:block; }
    .image-container { position:relative; min-height:260px; }
    .image-fallback { align-items:center; justify-content:center; display:flex; height:260px; background:#f3f6f4; border-radius:6px; color:#6c757d; font-weight:600; }
    .category-badge { background:#e9f7ef; color:#0f5132; padding:4px 8px; border-radius:6px; font-size:0.85rem; }
    .price-badge { font-weight:700; }
    /* Floating cart button */
    .cart-btn { position:fixed; right:20px; bottom:20px; z-index:1050; background:#198754; color:#fff; border-radius:50px; padding:10px 14px; box-shadow:0 6px 18px rgba(0,0,0,.15); cursor:pointer; border:none; }
    .cart-badge { position:relative; left:-8px; top:-8px; background:#dc3545; color:#fff; border-radius:50%; padding:3px 7px; font-size:0.75rem; }
    .carousel .plant-image { height:260px; }
    @media (max-width:576px) { .plant-image, .image-fallback { height:180px; } }
  </style>
</head>
<body>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3">
    <input
      type="text"
      class="form-control"
      placeholder="Rechercher..."
      id="searchInput"
      style="min-width: 200px;"
    />
    <button class="btn btn-outline-secondary" id="advancedToggle">
      Recherche avancée
    </button>
  </div>
  <div class="advanced-filters mt-3" id="advancedFilters" style="display:none;">
    <div class="row justify-content-center g-2">
      <div class="col-md-4">
        <select class="form-select" id="categoryFilter">
          <option value="">Toutes les catégories</option>
        </select>
      </div>
      <div class="col-md-3">
        <select class="form-select" id="sizeFilter">
          <option value="">Toutes les tailles</option>
          <option value="S">Petit pot en plastique</option>
          <option value="M">Moyen pot en plastique</option>
          <option value="XL">Petit pot en poterie</option>
          <option value="L">Moyen pot en poterie</option>
        </select>
      </div>
      <div class="col-md-3">
        <select class="form-select" id="priceSort">
          <option value="">Trier par prix</option>
          <option value="asc">Prix croissant</option>
          <option value="desc">Prix décroissant</option>
        </select>
      </div>
    </div>
  </div>

  <div class="row mt-4" id="plantsGrid"></div>

  <div class="card text-center d-none mt-4" id="noResults">
    <div class="plant-card py-5">
      <i class="fas fa-seedling fa-3x card-text mb-3"></i>
      <h4 class="card-text">Aucune plant trouvée</h4>
      <p class="card-text">Essayez de modifier vos termes de recherche</p>
    </div>
  </div>
</div>

<!-- Floating Cart Button -->
<button class="cart-btn" id="openCartBtn" aria-label="Ouvrir le panier">
  <i class="fas fa-shopping-basket"></i>
  <span id="cartCount" class="cart-badge" style="display:none;">0</span>
</button>

<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Votre panier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body" id="cartModalBody">
        <!-- items injected here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Continuer</button>
        <button type="button" class="btn btn-danger" id="clearCartBtn">Vider le panier</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast for cart notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="cartToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <i class="fas fa-check-circle text-success me-2"></i>
      <strong class="me-auto">Panier</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body" id="toastMessage">
      plant ajoutée au panier!
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
  let allPlants = <?php echo json_encode($catalogue, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  let allCategories = <?php echo json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  let filteredPlants = [];
  let cart = JSON.parse(localStorage.getItem('plantCart') || '[]');

  const searchInput = document.getElementById("searchInput");
  const categoryFilter = document.getElementById("categoryFilter");
  const sizeFilter = document.getElementById("sizeFilter");
  const priceSort = document.getElementById("priceSort");
  const advancedToggle = document.getElementById("advancedToggle");
  const advancedFilters = document.getElementById("advancedFilters");
  const openCartBtn = document.getElementById('openCartBtn');
  // Update cart count on page load
  updateCartCount();

  advancedToggle.addEventListener("click", () => {
    advancedFilters.style.display = advancedFilters.style.display === "none" || advancedFilters.style.display === "" ? "block" : "none";
  });

  // data loading
  (Array.isArray(allCategories) ? allCategories : []).sort().forEach(cat => {
      const option = document.createElement("option");
      option.value = cat;
      option.textContent = cat;
      categoryFilter.appendChild(option);
  });

 filterAndDisplayPlants();

  function filterAndDisplayPlants() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedCategory = categoryFilter.value;
    const selectedSize = sizeFilter.value;
    const sortOrder = priceSort.value;

    filteredPlants = allPlants.filter((plant) => {
      const nameMatch = plant.name && plant.name.toLowerCase().includes(searchTerm);
      const categoryMatch = plant.category && plant.category.toLowerCase().includes(searchTerm);
      const descriptionMatch = plant.details && plant.details.description && plant.details.description.toLowerCase().includes(searchTerm);
      const matchSearch = !searchTerm || nameMatch || categoryMatch || descriptionMatch;
      
      const matchCategory = !selectedCategory || plant.category === selectedCategory;
      
      const matchSize = !selectedSize || (plant.prices && plant.prices[selectedSize] && plant.prices[selectedSize].available);
      
      return matchSearch && matchCategory && matchSize;
    });

    if (sortOrder) {
      filteredPlants.sort((a, b) => {
        const priceA = getLowestPrice(a);
        const priceB = getLowestPrice(b);
        if (priceA === null && priceB === null) return 0;
        if (priceA === null) return 1;
        if (priceB === null) return -1;
        return sortOrder === "asc" ? priceA - priceB : priceB - priceA;
      });
    }

    displayPlants(filteredPlants);
  }

  function getLowestPrice(plant) {
    if (!plant.prices) return null;
    let lowestPrice = null;
    for (const size in plant.prices) {
      if (plant.prices[size].available && plant.prices[size].price) {
        const price = parseFloat(plant.prices[size].price);
        if (!isNaN(price) && (lowestPrice === null || price < lowestPrice)) {
          lowestPrice = price;
        }
      }
    }
    return lowestPrice;
  }

  function getAvailableSizes(plant) {
    if (!plant.prices) return [];
    const sizes = [];
    for (const size in plant.prices) {
      if (plant.prices[size].available) sizes.push(size);
    }
    return sizes;
  }

  function displayPlants(plants) {
    const grid = document.getElementById("plantsGrid");
    const noResults = document.getElementById("noResults");

    if (!Array.isArray(plants) || plants.length === 0) {
      grid.innerHTML = "";
      noResults.classList.remove("d-none");
      return;
    }

    noResults.classList.add("d-none");
    grid.innerHTML = plants.map((plant, plantIndex) => {
      // Use plantIndex as fallback unique identifier
      const uid = plant.id !== undefined ? plant.id : plantIndex;
      const availableSizes = getAvailableSizes(plant);
      const defaultSize = availableSizes[0] || '';
      const defaultPrice = defaultSize && plant.prices && plant.prices[defaultSize] ? plant.prices[defaultSize].price : '';
      const hasMultipleImages = Array.isArray(plant.photos) && plant.photos.length > 1;
      const duree_de_vie = plant.details?.duree_de_vie || '';
      const type_de_plant = plant.details?.type_de_plant || '';
      const hauteur_de_plant = plant.details?.hauteur_de_plant || '';
      const diametre_de_la_couronne = plant.details?.diametre_de_la_couronne || '';
      const temperature_ideale = plant.details?.temperature_ideale || '';
      const arrosage = plant.details?.arrosage || '';
      const ensoleillement = plant.details?.ensoleillement || '';

      // Build image content
      let imageContent = '';
      if (Array.isArray(plant.photos) && plant.photos.length > 0) {
        if (hasMultipleImages) {
          const slides = plant.photos.map((photo, photoIndex) => `
            <div class="carousel-item ${photoIndex === 0 ? 'active' : ''}">
              <img src="${photo}" class="plant-image" alt="${escapeHtml(plant.name || 'plant')}" loading="lazy" decoding="async" onerror="handleBrokenImage(event)">
            </div>
          `).join('');

          imageContent = `
            <div id="carousel-${uid}" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-inner">
                ${slides}
              </div>
              <button class="carousel-control-prev" type="button" data-bs-target="#carousel-${uid}" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#carousel-${uid}" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
              </button>
            </div>`;
        } else {
          imageContent = `<img src="${plant.photos[0]}" class="plant-image" alt="${escapeHtml(plant.name || 'plant')}" loading="lazy" decoding="async" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`;
        }
      }

      return `
      <div class="col-lg-4 col-md-6 mb-4">
        <div class="card plant-card h-100">
          <div class="image-container">
            ${imageContent}
            <div class="image-fallback" style="display:${Array.isArray(plant.photos) && plant.photos.length > 0 ? 'none' : 'flex'};">
              ${escapeHtml(plant.name || '')}
            </div>
          </div>
          <div class="card-body d-flex flex-column">
            ${plant.category ? `<span class="category-badge mb-2 align-self-start">${escapeHtml(plant.category)}</span>` : ''}
            <h5 class="card-title text-success">${escapeHtml(plant.name || '')}</h5>

            ${duree_de_vie ? `<div class="d-flex align-items-center mb-2"><img src="./icons/lifespan.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;"><div><div class="fw-bold">${escapeHtml(duree_de_vie)}</div><div class="text-muted small">Durée de vie</div></div></div>` : ''}

            ${type_de_plant ? `<div class="d-flex align-items-center mb-2"><img src="./icons/planttype.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;"><div><div class="fw-bold">${escapeHtml(type_de_plant)}</div><div class="text-muted small">Type</div></div></div>` : ''}

            ${hauteur_de_plant ? `<div class="d-flex align-items-center mb-2"><img src="icons/plantheight.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;"><div><div class="fw-bold">${escapeHtml(hauteur_de_plant)}</div><div class="text-muted small">Hauteur</div></div></div>` : ''}

            ${diametre_de_la_couronne ? `<div class="d-flex align-items-center mb-2"><img src="icons/diamettre.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;"><div><div class="fw-bold">${escapeHtml(diametre_de_la_couronne)}</div><div class="text-muted small">Diamètre</div></div></div>` : ''}

            ${temperature_ideale ? `<div class="d-flex align-items-center mb-2"><img src="icons/tempideal.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;"><div><div class="fw-bold">${escapeHtml(temperature_ideale)}</div><div class="text-muted small">Température</div></div></div>` : ''}

            ${arrosage ? `<div class="d-flex align-items-center mb-2"><img src="icons/arrosage.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;"><div><div class="fw-bold">${escapeHtml(arrosage)}</div><div class="text-muted small">Arrosage</div></div></div>` : ''}

            ${ensoleillement ? `<div class="d-flex align-items-center mb-3"><img src="icons/enseilloment.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;"><div><div class="fw-bold">${escapeHtml(ensoleillement)}</div><div class="text-muted small">Ensoleillement</div></div></div>` : ''}

            ${availableSizes.length > 0 ? `
  <div class="mb-2">
    <select class="form-select form-select-sm" id="size-${uid}" onchange="updatePrice(this, '${uid}')">
      ${availableSizes.map(size => {
        const labels = {
          S: "Petit pot en plastique",
          M: "Moyen pot en plastique",
          L: "Moyen pot en poterie",
          XL: "Petit pot en poterie"
        };
        return `<option value="${size}"  data-price="${plant.prices[size].price}">${labels[size] || size}</option>`;
      }).join('')}
    </select>
  </div>
` : ''}


            <div class="mt-auto">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="price-badge" id="price-${uid}">${defaultPrice ? parseFloat(defaultPrice).toFixed(2) + ' TND' : 'bientôt disponible!'}</span>
              </div>
              <button class="btn btn-add-to-cart w-100" onclick="addToCart(${plantIndex})" ${availableSizes.length === 0 ? 'disabled' : ''}>
                <i class="fas fa-cart-plus me-2"></i>Ajouter au panier
              </button>
            </div>
          </div>
        </div>
      </div>`;
    }).join('');
  }

  function showNoResults(msg) {
    const grid = document.getElementById("plantsGrid");
    const noResults = document.getElementById("noResults");
    grid.innerHTML = "";
    noResults.classList.remove("d-none");
    noResults.querySelector("p").textContent = msg;
  }

  function updatePrice(selectElement, uid) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    const priceElement = document.getElementById(`price-${uid}`);
    if (priceElement) priceElement.textContent = price ? parseFloat(price).toFixed(2) + ' TND' : 'plant non disponible';
  }

  function addToCart(plantIndex) {
    const plant= allPlants[plantIndex];
    if (!plant) return;

    const uid = plant.id !== undefined ? plant.id : plantIndex;
    const sizeSelect = document.getElementById(`size-${uid}`);
    const selectedSize = sizeSelect ? sizeSelect.value : getAvailableSizes(plant)[0];
    const price = plant.prices && plant.prices[selectedSize] ? plant.prices[selectedSize].price : null;

    if (!price) { showToast('Erreur: plant non disponible', 'error'); return; }

    const existingItemIndex = cart.findIndex(item => item.plantUid === uid && item.size === selectedSize);
    if (existingItemIndex > -1) {
      cart[existingItemIndex].quantity += 1;
    } else {
      cart.push({
        plantUid: uid,
        plantIndex: plantIndex,
        name: plant.name,
        size: selectedSize,
        price: parseFloat(price),
        quantity: 1,
        image: Array.isArray(plant.photos) && plant.photos[0] ? plant.photos[0] : null,
        category: plant.category
      });
    }

    localStorage.setItem('plantCart', JSON.stringify(cart));
    updateCartCount();
    showToast(`${plant.name} (${selectedSize}) ajouté au panier!`);
  }

  function updateCartCount() {
    const count = cart.reduce((total, item) => total + item.quantity, 0);
    const countElement = document.getElementById('cartCount');
    if (!countElement) return;
    if (count > 0) {
      countElement.textContent = count;
      countElement.style.display = 'inline-block';
    } else {
      countElement.style.display = 'none';
    }
  }

  function showToast(message, type = 'success') {
    const toast = document.getElementById('cartToast');
    const toastMessage = document.getElementById('toastMessage');
    const toastHeader = toast.querySelector('.toast-header');
    toastMessage.textContent = message;
    const icon = toastHeader.querySelector('i');
    if (type === 'error') { icon.className = 'fas fa-exclamation-circle text-danger me-2'; }
    else { icon.className = 'fas fa-check-circle text-success me-2'; }
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
  }

  // Cart modal handling
  openCartBtn.addEventListener('click', () => {
    renderCartModal();
    const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
    cartModal.show();
  });

  function renderCartModal() {
    const body = document.getElementById('cartModalBody');
    if (!cart.length) { body.innerHTML = '<p>Votre panier est vide.</p>'; return; }

    body.innerHTML = cart.map(item => `
      <div class="d-flex align-items-center mb-3">
        <img src="${item.image || 'icons/placeholder.png'}" alt="${escapeHtml(item.name)}" style="width:80px;height:80px;object-fit:cover;border-radius:6px;margin-right:12px;">
        <div class="flex-grow-1">
          <div class="fw-bold">${escapeHtml(item.name)} <small class="text-muted">(${escapeHtml(item.size)})</small></div>
          <div>${item.quantity} × ${item.price.toFixed(2)} TND</div>
        </div>
        <div><button class="btn btn-sm btn-outline-secondary" onclick="changeQuantity('${item.plantUid}', -1)">−</button>
        <button class="btn btn-sm btn-outline-secondary ms-1" onclick="changeQuantity('${item.plantUid}', 1)">+</button></div>
      </div>
    `).join('');
  }

  function changeQuantity(uid, delta) {
    const idx = cart.findIndex(i => i.plantUid == uid);
    if (idx === -1) return;
    cart[idx].quantity += delta;
    if (cart[idx].quantity <= 0) cart.splice(idx, 1);
    localStorage.setItem('plantCart', JSON.stringify(cart));
    updateCartCount();
    renderCartModal();
  }

  document.getElementById('clearCartBtn').addEventListener('click', () => {
    cart = [];
    localStorage.setItem('plantCart', JSON.stringify(cart));
    updateCartCount();
    renderCartModal();
  });

  // Image error handler: hide broken image and show fallback
  function handleBrokenImage(event) {
    const img = event.target;
    const carouselItem = img.closest('.carousel-item');
    if (carouselItem) carouselItem.style.display = 'none';
    else img.style.display = 'none';
  }

  // Simple HTML-escape for injected strings
  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"'`]/g, function (s) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;','`':'&#96;'})[s];
    });
  }

  // Event listeners
  searchInput.addEventListener("input", filterAndDisplayPlants);
  categoryFilter.addEventListener("change", filterAndDisplayPlants);
  sizeFilter.addEventListener("change", filterAndDisplayPlants);
  priceSort.addEventListener("change", filterAndDisplayPlants);
</script>

</body>
</html>
