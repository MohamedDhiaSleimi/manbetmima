<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catalogue de Plantes</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  
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
  <div class="advanced-filters" id="advancedFilters">
    <div class="row justify-content-center g-2">
      <div class="col-md-4">
        <select class="form-select" id="categoryFilter">
          <option value="">Toutes les catégories</option>
        </select>
      </div>
      <div class="col-md-3">
        <select class="form-select" id="sizeFilter">
          <option value="">Toutes les tailles</option>
          <option value="XXS">XXS</option>
          <option value="XS">XS</option>
          <option value="S">S</option>
          <option value="M">M</option>
          <option value="L">L</option>
          <option value="XL">XL</option>
          <option value="XXL">XXL</option>
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
      <h4 class="card-text">Aucune plante trouvée</h4>
      <p class="card-text">Essayez de modifier vos termes de recherche</p>
    </div>
  </div>
</div>

<!-- Floating Cart Button -->
<div class="cart-icon">

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
      Plante ajoutée au panier!
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
  let allPlants = [];
  let filteredPlants = [];
  let cart = JSON.parse(localStorage.getItem('plantCart') || '[]');

  const searchInput = document.getElementById("searchInput");
  const categoryFilter = document.getElementById("categoryFilter");
  const sizeFilter = document.getElementById("sizeFilter");
  const priceSort = document.getElementById("priceSort");
  const advancedToggle = document.getElementById("advancedToggle");
  const advancedFilters = document.getElementById("advancedFilters");

  // Update cart count on page load
  updateCartCount();

  advancedToggle.addEventListener("click", () => {
    advancedFilters.style.display =
      advancedFilters.style.display === "none" ||
      advancedFilters.style.display === ""
        ? "block"
        : "none";
  });

  //data loading
  Promise.all([
    fetch("catalogue.json").then((res) => res.json()),
    fetch("categories.json").then((res) => res.json()),
  ]).then(([plantsData, categoriesData]) => {
      allPlants = plantsData;

      categoriesData.sort().forEach((cat) => {
        const option = document.createElement("option");
        option.value = cat;
        option.textContent = cat;
        categoryFilter.appendChild(option);
      });

      filterAndDisplayPlants();
  }).catch((error) => {
      console.error("Erreur lors du chargement :", error);
      showNoResults("Erreur de chargement. Réessayez plus tard.");
  });

  function filterAndDisplayPlants() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedCategory = categoryFilter.value;
    const selectedSize = sizeFilter.value;
    const sortOrder = priceSort.value;

    filteredPlants = allPlants.filter((plant) => {
      const matchSearch =
        plant.name.toLowerCase().includes(searchTerm) ||
        (plant.category &&
          plant.category.toLowerCase().includes(searchTerm)) ||
        (plant.details.description && plant.details.description.toLowerCase().includes(searchTerm));
      
      const matchCategory =
        !selectedCategory || plant.category === selectedCategory;
      
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
      if (plant.prices[size].available) {
        sizes.push(size);
      }
    }
    return sizes;
  }

  function displayPlants(plants) {
    const grid = document.getElementById("plantsGrid");
    const noResults = document.getElementById("noResults");

    if (plants.length === 0) {
      grid.innerHTML = "";
      noResults.classList.remove("d-none");
      return;
    }

    noResults.classList.add("d-none");
    grid.innerHTML = plants
      .map((plant, plantIndex) => {
        const availableSizes = getAvailableSizes(plant);
        const defaultSize = availableSizes[0] || '';
        const defaultPrice = defaultSize && plant.prices[defaultSize] ? plant.prices[defaultSize].price : '';
        const hasMultipleImages = plant.photos && plant.photos.length > 1;
        
        const duree_de_vie = plant.details?.duree_de_vie|| '';
        const type_de_plante = plant.details?.type_de_plante|| '';
        const hauteur_de_plante = plant.details?.hauteur_de_plante|| '';
        const diametre_de_la_couronne = plant.details?.diametre_de_la_couronne|| '';
        const temperature_ideale = plant.details?.temperature_ideale|| '';
        const arrosage = plant.details?.arrosage|| '';
        const ensoleillement = plant.details?.ensoleillement|| '';
        
        // Create image carousel or single image
        let imageContent = '';
        if (plant.photos && plant.photos.length > 0) {
          if (hasMultipleImages) {
            imageContent = `
              <div id="carousel${plantIndex}" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                  ${plant.photos.map((photo, photoIndex) => `
                    <div class="carousel-item ${photoIndex === 0 ? 'active' : ''}">
                      <img src="${photo}" class="plant-image" alt="${plant.name}" 
                           onerror="this.style.display='none'; this.closest('.carousel-item').style.display='none';">
                    </div>
                  `).join('')}
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#carousel${plantIndex}" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carousel${plantIndex}" data-bs-slide="next">
                  <span class="carousel-control-next-icon"></span>
                </button>
              </div>`;
          } else {
            imageContent = `
              <img src="${plant.photos[0]}" class="plant-image" alt="${plant.name}"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`;
          }
        }
        
        return `
        <div class="col-lg-4 col-md-6 mb-4">
          <div class="card plant-card h-100">
            <div class="image-container">
              ${imageContent}
              <div class="image-fallback" style="display:${plant.photos && plant.photos.length > 0 ? 'none' : 'flex'};">
                ${plant.name}
              </div>
            </div>
            <div class="card-body d-flex flex-column">
              ${
                plant.category
                  ? `<span class="category-badge mb-2 align-self-start">${plant.category}</span>`
                  : ""
              }
              <h5 class="card-title text-success">${plant.name}</h5>
              ${duree_de_vie ? `
                <div class="d-flex align-items-center mb-2">
                  <img src="./icons/lifespan.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;">
                  <div>
                    <div class="fw-bold">${duree_de_vie}</div>
                    <div class="text-muted small">Durée de vie</div>
                  </div>
                </div>` : ''}

              ${type_de_plante ? `
                <div class="d-flex align-items-center mb-2">
                  <img src="./icons/planttype.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;">
                  <div>
                    <div class="fw-bold">${type_de_plante}</div>
                    <div class="text-muted small">Type</div>
                  </div>
                </div>` : ''}

              ${hauteur_de_plante ? `
                <div class="d-flex align-items-center mb-2">
                  <img src="icons/plantheight.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;">
                  <div>
                    <div class="fw-bold">${hauteur_de_plante}</div>
                    <div class="text-muted small">Hauteur</div>
                  </div>
                </div>` : ''}

              ${diametre_de_la_couronne ? `
                <div class="d-flex align-items-center mb-2">
                  <img src="icons/diamettre.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;">
                  <div>
                    <div class="fw-bold">${diametre_de_la_couronne}</div>
                    <div class="text-muted small">Diamètre</div>
                  </div>
                </div>` : ''}

              ${temperature_ideale ? `
                <div class="d-flex align-items-center mb-2">
                  <img src="icons/tempideal.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;">
                  <div>
                    <div class="fw-bold">${temperature_ideale}</div>
                    <div class="text-muted small">Température</div>
                  </div>
                </div>` : ''}

              ${arrosage ? `
                <div class="d-flex align-items-center mb-2">
                  <img src="icons/arrosage.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;">
                  <div>
                    <div class="fw-bold">${arrosage}</div>
                    <div class="text-muted small">Arrosage</div>
                  </div>
                </div>` : ''}

              ${ensoleillement ? `
                <div class="d-flex align-items-center mb-3">
                  <img src="icons/enseilloment.png" alt="" class="me-2" style="width:1.5em;height:1.5em;vertical-align:middle;">
                  <div>
                    <div class="fw-bold">${ensoleillement}</div>
                    <div class="text-muted small">ensoleillement</div>
                  </div>
                </div>` : ''}

              ${availableSizes.length > 0 ? `
                <div class="mb-2">
                  <select class="form-select form-select-sm" id="size${plant.id}" onchange="updatePrice(this, ${plant.id})">
                    ${availableSizes.map(size => `
                      <option value="${size}" data-price="${plant.prices[size].price}">${size}</option>
                    `).join('')}
                  </select>
                </div>
              ` : ''}
              
              <div class="mt-auto">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="price-badge" id="price${plant.id}">${defaultPrice ? parseFloat(defaultPrice).toFixed(2) + ' TND' : 'Prix non disponible'}</span>
                </div>
                <button class="btn btn-add-to-cart w-100" 
                        onclick="addToCart(${plant.id})" 
                        ${availableSizes.length === 0 ? 'disabled' : ''}>
                  <i class="fas fa-cart-plus me-2"></i>Ajouter au panier
                </button>
              </div>
            </div>
          </div>
        </div>`;
      })
      .join("");
  }

  function showNoResults(msg) {
    const grid = document.getElementById("plantsGrid");
    const noResults = document.getElementById("noResults");
    grid.innerHTML = "";
    noResults.classList.remove("d-none");
    noResults.querySelector("p").textContent = msg;
  }

  function updatePrice(selectElement, plantId) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    const priceElement = document.getElementById(`price${plantId}`);
    priceElement.textContent = price ? parseFloat(price).toFixed(2) + ' TND' : 'Prix non disponible';
  }

  function addToCart(plantId) {
    const plant = allPlants.find(p => p.id === plantId);
    if (!plant) return;

    const sizeSelect = document.getElementById(`size${plantId}`);
    const selectedSize = sizeSelect ? sizeSelect.value : getAvailableSizes(plant)[0];
    const price = plant.prices[selectedSize]?.price;

    if (!price) {
      showToast('Erreur: Prix non disponible', 'error');
      return;
    }

    // Check if item already exists in cart
    const existingItemIndex = cart.findIndex(item => 
      item.plantId === plantId && item.size === selectedSize
    );

    if (existingItemIndex > -1) {
      cart[existingItemIndex].quantity += 1;
    } else {
      cart.push({
        plantId: plantId,
        name: plant.name,
        size: selectedSize,
        price: parseFloat(price),
        quantity: 1,
        image: plant.photos && plant.photos[0] ? plant.photos[0] : null,
        category: plant.category
      });
    }

    // Save to localStorage
    localStorage.setItem('plantCart', JSON.stringify(cart));
    
    updateCartCount();
    showToast(`${plant.name} (${selectedSize}) ajouté au panier!`);
  }

  function updateCartCount() {
    const count = cart.reduce((total, item) => total + item.quantity, 0);
    const countElement = document.getElementById('cartCount');
    
    if (count > 0) {
      countElement.textContent = count;
      countElement.style.display = 'block';
    } else {
      countElement.style.display = 'none';
    }
  }

  function showToast(message, type = 'success') {
    const toast = document.getElementById('cartToast');
    const toastMessage = document.getElementById('toastMessage');
    const toastHeader = toast.querySelector('.toast-header');
    
    toastMessage.textContent = message;
    
    // Change icon and color based on type
    const icon = toastHeader.querySelector('i');
    if (type === 'error') {
      icon.className = 'fas fa-exclamation-circle text-danger me-2';
    } else {
      icon.className = 'fas fa-check-circle text-success me-2';
    }
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
  }

  // Event listeners
  searchInput.addEventListener("input", filterAndDisplayPlants);
  categoryFilter.addEventListener("change", filterAndDisplayPlants);
  sizeFilter.addEventListener("change", filterAndDisplayPlants);
  priceSort.addEventListener("change", filterAndDisplayPlants);
</script>

</body>
</html>