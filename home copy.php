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

<script>
  let allPlants = [];
  let filteredPlants = [];
  let contactData = {};

  const searchInput = document.getElementById("searchInput");
  const categoryFilter = document.getElementById("categoryFilter");
  const sizeFilter = document.getElementById("sizeFilter");
  const priceSort = document.getElementById("priceSort");
  const advancedToggle = document.getElementById("advancedToggle");
  const advancedFilters = document.getElementById("advancedFilters");

  advancedToggle.addEventListener("click", () => {
    advancedFilters.style.display =
      advancedFilters.style.display === "none" ||
      advancedFilters.style.display === ""
        ? "block"
        : "none";
  });

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

  function getPriceRange(plant) {
    if (!plant.prices) return "Prix non disponible";
    
    const prices = [];
    for (const size in plant.prices) {
      if (plant.prices[size].available && plant.prices[size].price) {
        const price = parseFloat(plant.prices[size].price);
        if (!isNaN(price)) {
          prices.push(price);
        }
      }
    }
    
    if (prices.length === 0) return "Prix non disponible";
    
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    
    return min === max ? `${min.toFixed(2)} TND` : `${min.toFixed(2)} - ${max.toFixed(2)} TND`;
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
              ${duree_de_vie ? `<p class="card-text">Durée de vie: ${duree_de_vie}</p>` : ''}
              ${type_de_plante ? `<p class="card-text">Type de plante: ${type_de_plante}</p>` : ''}
              ${hauteur_de_plante ? `<p class="card-text">Hauteur de plante: ${hauteur_de_plante}</p>` : ''}
              ${diametre_de_la_couronne ? `<p class="card-text">Diamètre de la couronne: ${diametre_de_la_couronne}</p>` : ''}
              ${temperature_ideale ? `<p class="card-text">Température idéale: ${temperature_ideale}</p>` : ''}
              ${arrosage ? `<p class="card-text">Arrosage: ${arrosage}</p>` : ''}
              ${ensoleillement ? `<p class="card-text">Ensoleillement: ${ensoleillement}</p>` : ''}
              ${availableSizes.length > 0 ? `
                <div class="mb-2">
                  <select class="form-select form-select-sm" onchange="updatePrice(this, ${plantIndex})">
                    ${availableSizes.map(size => `
                      <option value="${size}" data-price="${plant.prices[size].price}">${size}</option>
                    `).join('')}
                  </select>
                </div>
              ` : ''}
              <span class="price-badge align-self-start" id="price${plantIndex}">${defaultPrice ? parseFloat(defaultPrice).toFixed(2) + ' TND' : 'Prix non disponible'}</span>
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

  searchInput.addEventListener("input", filterAndDisplayPlants);
  categoryFilter.addEventListener("change", filterAndDisplayPlants);
  sizeFilter.addEventListener("change", filterAndDisplayPlants);
  priceSort.addEventListener("change", filterAndDisplayPlants);

  function updatePrice(selectElement, plantIndex) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    const priceElement = document.getElementById(`price${plantIndex}`);
    priceElement.textContent = price ? parseFloat(price).toFixed(2) + ' TND' : 'Prix non disponible';
  }
</script>