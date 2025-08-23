<!DOCTYPE html>
<html lang="fr" data-theme="light">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Catalogue de Plantes ManbetMiMa</title>
    <link rel="icon" href="./icons/logo2.png" type="image/png" />
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
      <?php
        $page = $_POST['page'] ?? 'main';
        if ($page === 'catalogue') {
          include 'catalogue.php'; 
        } elseif ($page === 'offers') {
          include 'offers.php';
        } else {
          include 'about.php';
        }
      ?>
    </div>

    <footer>
      <div class="container" id="footerContact"></div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Theme toggle & footer logic remain here -->
  <script>
 const html = document.documentElement;

  <?php
    $binFile = "./storage/binary/contact.bin";
    if (!file_exists($binFile)) {
        // Create default contact.bin if missing
        $default = [
            "email" => "info@example.com",
            "phone" => "+21612345678"
        ];
        file_put_contents($binFile, serialize($default));
    }
    $contactData = @unserialize(file_get_contents($binFile));
    if ($contactData === false) $contactData = [];
    echo "const contactData = ".json_encode($contactData).";";
  ?>

  if (typeof updateFooterContact === "function") {
      updateFooterContact(contactData);
  }

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

    secondLine = ` Ben Arous, Tunisie ${secondLine}`;
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
