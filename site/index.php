<!DOCTYPE html>
<html lang="fr" data-theme="light">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Catalogue de Plantes ManbetMiMa</title>
    <link rel="icon" href="emoji.png" type="image/png" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
      rel="stylesheet"
    />
  
    <link href="style.css" rel="stylesheet" />
  </head>

  <body>
    <!-- HEADER -->
    <header class="d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
      <!-- Left: Hamburger + Logo -->
      <div class="d-flex align-items-center gap-3">
        <!-- Hamburger toggle (only on mobile) -->
        <button
          class="btn d-md-none p-0"
          type="button"
          data-bs-toggle="offcanvas"
          data-bs-target="#mobileMenu"
          aria-controls="mobileMenu"
        >
          <i class="fa-solid fa-bars fs-4"></i>
        </button>

        <!-- Logo (always visible) -->
        <a href="index" class="logo text-decoration-none card-text fw-bold fs-4">
          Manbet MiMa - منبت ميما
        </a>
      </div>

      <!-- Controls: Search bar always visible, others only desktop -->
      <div class="d-flex align-items-center gap-3">
       
        <!-- À propos - desktop only -->
        <form method="POST" action="index" class="m-0 d-none d-md-inline">
          <input type="hidden" name="page" value="about" />
          <button type="submit" class="btn btn-link p-0 text-primary fw-semibold" style="text-decoration: none;">
            À propos
          </button>
        </form>

        <!-- Dark mode toggle - desktop only -->
        <span class="dark-toggle d-none d-md-flex align-items-center" id="themeToggle" title="Mode sombre" style="cursor:pointer;">
          <i class="fa-solid fa-moon" id="themeIcon"></i>
        </span>
      </div>
    </header>

    <!-- Offcanvas for mobile menu -->
    <div
      class="offcanvas offcanvas-start"
      tabindex="-1"
      id="mobileMenu"
      aria-labelledby="mobileMenuLabel"
    >
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="mobileMenuLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body d-flex flex-column gap-3">
        <!-- À propos -->
        <form method="POST" action="index" class="m-0 d-md-none">
          <input type="hidden" name="page" value="about" />
          <button type="submit" class="btn btn-link p-0 text-primary fw-semibold">
            À propos
          </button>
        </form>

        <!-- Dark mode toggle -->
        <span class="dark-toggle d-md-none d-flex align-items-center" id="themeToggleMobile" title="Mode sombre" style="cursor:pointer;">
          <i class="fa-solid fa-moon" id="themeIconMobile"></i>
        </span>
      </div>
    </div>

    <!-- Main Content or About Page -->
    <div class="container py-4">
      <?php
        $page = $_POST['page'] ?? 'main';
        if ($page === 'about') {
          include 'about.php';
        } else {
          include 'home.php';
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
          contactHtml += `<p class="mb-1"> Contact : <a href="mailto:${contact.email}">${contact.email}</a></p>`;
        }

        let secondLine = "";
        if (contact && contact.phone && contact.phone.trim() !== "") {
          secondLine += ` ${contact.phone}`;
        }

        secondLine = ` Tunis, Tunisie | ${secondLine}`;
        contactHtml += `<p class="mb-0">${secondLine}</p>`;

        let socialLinks = [];
        if (contact && contact.facebook)
          socialLinks.push(`<a href="${contact.facebook}" target="_blank" title="Facebook"><i class="fab fa-facebook"></i></a>`);
        if (contact && contact.instagram)
          socialLinks.push(`<a href="${contact.instagram}" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>`);
        if (contact && contact.whatsapp)
          socialLinks.push(`<a href="${contact.whatsapp}" target="_blank" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>`);
        if (contact && contact.tiktok)
          socialLinks.push(`<a href="${contact.tiktok}" target="_blank" title="TikTok"><i class="fab fa-tiktok"></i></a>`);
        if (contact && contact.twitter)
          socialLinks.push(`<a href="${contact.twitter}" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>`);
        if (contact && contact.bluesky)
          socialLinks.push(`<a href="${contact.bluesky}" target="_blank" title="Bluesky"><i class="fas fa-cloud"></i></a>`);

        if (socialLinks.length > 0) {
          contactHtml += `<p class="mb-0 mt-2" style="font-size: 1.2rem;">${socialLinks.join(" ")}</p>`;
        }

        footerContact.innerHTML = contactHtml;
      }

      
     

    </script>
  </body>
</html>
