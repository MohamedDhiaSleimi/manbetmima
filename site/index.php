<!DOCTYPE html>
<html lang="fr" data-theme="light">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Catalogue de Plantes ManbetMiMa</title>
    <link rel="icon" href="./icons/logo.png" type="image/png" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
      rel="stylesheet"
    />
    <link 
      href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500&display=swap" 
      rel="stylesheet"
    />
    <style>
      .logo {
        font-family: 'Baloo 2', cursive;
      }
    </style>
    <link href="style.css" rel="stylesheet" />
  </head>

  <body>
    <!-- HEADER -->
    <header class="border-bottom">
      <!-- First row -->
      <div class="d-flex justify-content-between align-items-center py-3 px-4 w-100">
        <!-- Empty div for balance -->
        <div style="width: 34px;"></div>

        <!-- Centered title -->
        <div class="text-center flex-grow-1">
          <a href="index" class="logo text-decoration-none fw-bold fs-4" style="color: black; white-space: nowrap;">
            Manbet Mima - منبت ميما
          </a>
        </div>

        <!-- Right side icons -->
        <div class="d-flex align-items-center gap-3">
          <!-- Cart icon styled like theme toggle -->
          <span class="d-flex align-items-center" id="cartButton" title="Panier" style="cursor:pointer;" onclick="window.location.href='cart'">
            <i class="fa-solid fa-shopping-cart"></i>
            <span class="cart-badge" id="cartCount" style="display: none;">0</span>
          </span>

          <!-- Theme toggle -->
          <span class="dark-toggle d-flex align-items-center" id="themeToggle" title="Mode sombre" style="cursor:pointer;">
            <i class="fa-solid fa-moon" id="themeIcon"></i>
          </span>
        </div>
      </div>

      <!-- Second row - Centered nav -->
      <div class="border-top py-2 px-4 d-flex justify-content-center w-100">
        <nav class="d-flex gap-4">
          <form method="POST" action="index" class="m-0">
            <input type="hidden" name="page" value="about" />
            <button type="submit" class="btn btn-link p-0 text-primary fw-semibold" style="text-decoration: none;color: black !important; ">
              À propos
            </button>
          </form>
          <form method="POST" action="index" class="m-0">
            <input type="hidden" name="page" value="offers" />
            <button type="submit" class="btn btn-link p-0 text-primary fw-semibold" style="text-decoration: none;color: black !important; ">
              Offres
            </button>
          </form>
          <form method="POST" action="index" class="m-0">
            <input type="hidden" name="page" value="catalogue" />
            <button type="submit" class="btn btn-link p-0 text-primary fw-semibold" style="text-decoration: none;color: black !important; ">
              Catalogue
            </button>
          </form>
        </nav>
      </div>
  </header>



    <!-- Main Content or About Page -->
    <div class="container py-4">
      <blockquote class="imgur-embed-pub" lang="en" data-id="gZB89y1"><a href="https://photos.app.goo.gl/PNi17TaX3kJQ787V8">View post on imgur.com</a></blockquote><script async src="https://photos.app.goo.gl/PNi17TaX3kJQ787V8" charset="utf-8"></script>
      <?php
        $page = $_POST['page'] ?? 'main';
        if ($page === 'about') {
          include 'about.php';
        } elseif ($page === 'offers') {
          include 'offers.php';
        } else {
          include 'catalogue.php';
        }
      ?>
    </div>

    <footer>
      <div class="container" id="footerContact"></div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Theme toggle & footer logic remain here -->
    <script>
      const themeToggle = document.getElementById("themeToggle");
      const themeIcon = document.getElementById("themeIcon");
      const html = document.documentElement;
  
      Promise.all([
      fetch("contact.json")
      .then((res) => {
        if (!res.ok) throw new Error("Contact file not found");
        return res.json();
      })
      .catch((error) => {
        console.log("Contact file not found or invalid, using defaults");
        return {};
      }),
  ])
    .then(([contactInfo]) => {
      contactData = contactInfo;
      if (typeof updateFooterContact === "function") {
        updateFooterContact(contactData);
      }
    })
    .catch((error) => {
      console.error("Erreur lors du chargement :", error);
      showNoResults("Erreur de chargement. Réessayez plus tard.");
    });

      function updateIcon(theme) {
        themeIcon.classList.remove("rotate-sun", "rotate-moon");
        if (theme === "dark") {
          themeIcon.classList.remove("fa-moon");
          themeIcon.classList.add("fa-sun");
          void themeIcon.offsetWidth;
          themeIcon.classList.add("rotate-sun");
        } else {
          themeIcon.classList.remove("fa-sun");
          themeIcon.classList.add("fa-moon");
          void themeIcon.offsetWidth;
          themeIcon.classList.add("rotate-moon");
        }
      }

      themeToggle.addEventListener("click", () => {
        const currentTheme = html.getAttribute("data-theme");
        const newTheme = currentTheme === "dark" ? "light" : "dark";
        html.setAttribute("data-theme", newTheme);
        localStorage.setItem("theme", newTheme);
        updateIcon(newTheme);
      });
      themeToggleMobile.addEventListener("click", () => {
        const currentTheme = html.getAttribute("data-theme");
        const newTheme = currentTheme === "dark" ? "light" : "dark";
        html.setAttribute("data-theme", newTheme);
        localStorage.setItem("theme", newTheme);
        updateIcon(newTheme);
      });

      const savedTheme = localStorage.getItem("theme") || "light";
      html.setAttribute("data-theme", savedTheme);
      updateIcon(savedTheme);

      function updateFooterContact(contact) {
        const footerContact = document.getElementById("footerContact");
        let contactHtml = "";

        if (contact && contact.email && contact.email.trim() !== "") {
          contactHtml += `<p class="mb-1"> Contact : <a href="mailto:${contact.email}" style="color: black">${contact.email}</a></p>`;
        }

        let secondLine = "";
        if (contact && contact.phone && contact.phone.trim() !== "") {
          secondLine += ` ${contact.phone}`;
        }

        secondLine = ` Tunis, Tunisie | ${secondLine}`;
        contactHtml += `<p class="mb-0">${secondLine}</p>`;

        let socialLinks = [];
        if (contact && contact.facebook)
          socialLinks.push(`<a href="${contact.facebook}" target="_blank" title="Facebook"><i class="fab fa-facebook" style="color: black"></i></a>`);
        if (contact && contact.instagram)
          socialLinks.push(`<a href="${contact.instagram}" target="_blank" title="Instagram"><i class="fab fa-instagram" style="color: black"></i></a>`);
        if (contact && contact.whatsapp)
          socialLinks.push(`<a href="${contact.whatsapp}" target="_blank" title="WhatsApp"><i class="fab fa-whatsapp"style="color: black"></i></a>`);
        if (contact && contact.tiktok)
          socialLinks.push(`<a href="${contact.tiktok}" target="_blank" title="TikTok"><i class="fab fa-tiktok"style="color: black"></i></a>`);
        if (contact && contact.twitter)
          socialLinks.push(`<a href="${contact.twitter}" target="_blank" title="Twitter"><i class="fab fa-twitter"style="color: black"></i></a>`);
        if (contact && contact.bluesky)
          socialLinks.push(`<a href="${contact.bluesky}" target="_blank" title="Bluesky"><i class="fas fa-cloud"style="color: black"></i></a>`);

        if (socialLinks.length > 0) {
          contactHtml += `<p class="mb-0 mt-2" style="font-size: 1.2rem;">${socialLinks.join(" ")}</p>`;
        }

        footerContact.innerHTML = contactHtml;
      }

      
     

    </script>
  </body>
</html>
