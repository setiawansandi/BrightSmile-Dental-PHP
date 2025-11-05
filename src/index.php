<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Home</title>
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="css/root.css">
  <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
  <?php require_once __DIR__ . '/components/navbar.php'; ?>
  <!-- Hero Section -->
  <section class="hero">
    <div class="general hero-page">
      <div class="hero-text">
        <h1>
          Where bright smiles meets bright <span class="highlight">Dentist</span>
        </h1>
        <p class="hero-subtitle">Teeth cleaning and whitening for a confident smile</p>

        <div class="actions">
          <a href="appointment.php" class="btn-base btn-primary">Book Now</a>
          <div class="dentists">
            <img src="assets/images/doc1.png">
            <img src="assets/images/doc2.png">
            <img src="assets/images/doc3.png">
            <span class="text-secondary"><span class="text-primary">10+</span> Pro Dentists</span>
          </div>
        </div>

        <div class="features">
          <span><img src="assets/icons/boost.svg" alt=""> Free Appointment</span>
          <span><img src="assets/icons/boost.svg" alt=""> Student & Senior Discount</span>
        </div>
      </div>

      <div class="hero-image">
        <img src="assets/images/hero-img.png" alt="Hero Image">
      </div>
    </div>
  </section>

  <!-- Benefits Section -->
  <section class="benefits">
    <div class="general">
      <h2>Why choose <span class="highlight">BrightSmile?</span></h2>
      <p class="subtitle">
        We provide comprehensive dental services with the latest technology and a patient-centred approach
      </p>

      <div class="features-grid">
        <div class="feature-card">
          <p>Schedule your appointments online 24/7 with our convenient booking system</p>
          <h3><img src="assets/icons/benefit 1.svg" alt=""> Easy Booking</h3>
        </div>
        <div class="feature-card">
          <p>Extended hours including evenings and weekends to fit your schedule</p>
          <h3><img src="assets/icons/benefit 2.svg" alt=""> Flexible Hour</h3>
        </div>
        <div class="feature-card">
          <p>Experienced dentists committed to your oral health</p>
          <h3><img src="assets/icons/benefit 3.svg" alt=""> Expert Team</h3>
        </div>
        <div class="feature-card">
          <p>State-of-art equipment and peak dental care</p>
          <h3><img src="assets/icons/benefit 4.svg" alt=""> Quality Care</h3>
        </div>
      </div>

      <p class="stats-title">Trusted by Thousands for Healthier Smile!</p>

      <div class="stats">
        <div>
          <span class="number">98<span class="symbol">%</span></span>
          <p>Satisfaction</p>
        </div>
        <div>
          <span class="number">5000<span class="symbol">+</span></span>
          <p>Smiles Transformed</p>
        </div>
        <div>
          <span class="number">4.9<span class="symbol">☆</span></span>
          <p>Star Rating</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA Section -->
  <section class="cta">
    <div class="cta-content">
      <img src="assets/icons/logo.svg" alt="Tooth Icon" class="tooth-icon left">

      <div class="cta-text">
        <h2>
          Ready to <span class="highlight">Schedule</span> Your Visit?
        </h2>
        <p>Take the first step towards a healthier, brighter, smile today</p>
        <a href="appointment.php" class="btn-base btn-primary2">Book Now</a>
      </div>

      <img src="assets/icons/logo.svg" alt="Tooth Icon" class="tooth-icon right">
    </div>
  </section>

  <?php require __DIR__ . '/components/footer.php'; ?>

</body>

</html>