<div class="container py-4">
  <div class="d-flex align-items-center gap-3">
    <input
      type="text"
      class="form-control"
      placeholder="Rechercher..."
      id="searchInput"
      style="min-width: 200px;"
    />

    <!-- Advanced search - desktop only -->
    <button class="btn btn-outline-secondary d-none d-md-inline" id="advancedToggle">
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
  ]).then(([plantsData, categoriesData, contactInfo]) => {
      allPlants = plantsData;
      contactData = contactInfo;

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
    const sortOrder = priceSort.value;

    filteredPlants = allPlants.filter((plant) => {
      const matchSearch =
        plant.name.toLowerCase().includes(searchTerm) ||
        (plant.category &&
          plant.category.toLowerCase().includes(searchTerm)) ||
        plant.description.toLowerCase().includes(searchTerm);
      const matchCategory =
        !selectedCategory || plant.category === selectedCategory;
      return matchSearch && matchCategory;
    });

    if (sortOrder === "asc") {
      filteredPlants.sort(
        (a, b) => parseFloat(a.price) - parseFloat(b.price)
      );
    } else if (sortOrder === "desc") {
      filteredPlants.sort(
        (a, b) => parseFloat(b.price) - parseFloat(a.price)
      );
    }

    displayPlants(filteredPlants);
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
    console.log(plants);
    grid.innerHTML = plants
      .map((plant) => {
        return `
        <div class="col-lg-4 col-md-6 mb-4">
          <div class="card plant-card h-100">
            <div class="image-container">
              <img
                src="${plant.image}"
                class="plant-image"
                alt="${plant.name}"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
              />
              <div class="image-fallback" style="display:none;">
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
              <p class="card-text flex-grow-1">${plant.description}</p>
              <span class="price-badge align-self-start">${parseFloat(
                plant.price
              ).toFixed(2)} TND</span>
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
  priceSort.addEventListener("change", filterAndDisplayPlants);
</script>
