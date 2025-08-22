<?php
$formulas = [
    [
        "title" => "Formule 1 – Pack petits pots en plastique",
        "plants" => "Minimum : 6 plantes",
        "price" => "",
        "details" => "Petits contenants plastiques<br>Convient parfaitement aux petits espaces ou aux débutants."
    ],
    [
        "title" => "Formule 2 – Pack moyens pots en plastique",
        "plants" => "Minimum : 3 plantes",
        "price" => "",
        "details" => "Contenants plastiques de taille moyenne<br>Un bon compromis entre encombrement et esthétique."
    ],
    [
        "title" => "Formule 3 – Grand pot en poterie",
        "plants" => "1 grand pot",
        "price" => "",
        "details" => "Un seul grand pot en terre cuite ou poterie<br>Une pièce décorative idéale pour l’intérieur ou le jardin."
    ],
    [
        "title" => "Formule 4 – Duo en poterie",
        "plants" => "Minimum : 2 plantes",
        "price" => "",
        "details" => "Pots moyens en poterie<br>Alliance de naturel et d’élégance pour un effet chaleureux."
    ],
    [
        "title" => "Formule 5 – Pack pré-sélectionné pour les indécis",
        "plants" => "Assortiment choisi",
        "price" => "",
        "details" => "Un assortiment choisi par nos soins<br>Pour celles et ceux qui hésitent ou souhaitent une découverte guidée."
    ]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Nos Formules</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
      body {
          background: #f8f9fa;
          font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      }
      .container {
          max-width: 800px;
      }
      .accordion-button {
          font-weight: 600;
          font-size: 1.1rem;
      }
      .price-tag {
          color: #198754;
          font-weight: bold;
          margin-left: auto;
      }
      .accordion-item {
          border-radius: 12px;
          overflow: hidden;
          margin-bottom: 10px;
          box-shadow: 0 3px 8px rgba(0,0,0,0.05);
      }
  </style>
</head>
<body>
<div class="container py-5">
    <h1 class="text-center mb-4">Nos Offres</h1>
    <div class="accordion" id="formulesAccordion">
        <?php foreach($formulas as $index => $formula): ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?= $index ?>">
                <button class="accordion-button collapsed d-flex align-items-center" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>"
                        aria-expanded="false" aria-controls="collapse<?= $index ?>">
                    <?= $formula["title"] ?> 
                    <span class="ms-3 text-muted small">(<?= $formula["plants"] ?>)</span>
                    <?php if($formula["price"]): ?>
                        <span class="price-tag"><?= $formula["price"] ?></span>
                    <?php endif; ?>
                </button>
            </h2>
            <div id="collapse<?= $index ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $index ?>" data-bs-parent="#formulesAccordion">
                <div class="accordion-body">
                    <?= $formula["details"] ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
