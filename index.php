<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/public/global.css">
    
    <link rel="stylesheet" href="assets/css/public/index.css">
</head>
<body>
    <div class="grid-bg"></div>
    <div id="mouse-light"></div>
    
    <nav>
        <div id="logo-text">Agath<span>io</span></div>
        <div class="menu-capsule">
            <a href="index.php" class="active">Home</a>
            <a href="portfolio.php">Portfolio</a>
            <a href="expertise.php">Expertise</a>
        </div>
    </nav>

<?php 
// Menentukan judul halaman secara dinamis
$pageTitle = "Agathio | Creative Portfolio";
include 'includes/header.php'; 
?>

    <header>
        <div id="hero-content">
            <div id="hero-inner">
                <h1 id="hero-title">
                    <span id="hello-typing"></span> 👋<br>
                    I'm Fidelio Bagas
                </h1>
                <p id="hero-desc">
                    Information Systems student passionate about UI/UX design, marketing, and live broadcasting. Let's build something extraordinary.
                </p>
                
                <p class="stack-label">DESIGN & DEVELOPMENT STACK</p>
                <div id="tools-marquee">
                    <div class="tools-track">
                        <img src="assets/img/logo-aplikasi/figma.png" alt="Figma">
                        <img src="assets/img/logo-aplikasi/canva.png" alt="Canva">
                        <img src="assets/img/logo-aplikasi/vscode.png" alt="VS Code">
                        <img src="assets/img/logo-aplikasi/vMix-Logo-Black.jpg" alt="vMix">
                        <img src="assets/img/logo-aplikasi/figma.png" alt="Figma">
                        <img src="assets/img/logo-aplikasi/canva.png" alt="Canva">
                        <img src="assets/img/logo-aplikasi/vscode.png" alt="VS Code">
                        <img src="assets/img/logo-aplikasi/vMix-Logo-Black.jpg" alt="vMix">
                    </div>
                </div>
            </div>

            <div id="hero-photo-container">
                <img id="hero-photo" src="assets/img/bagas-hero-photo.png" alt="Bagas">
            </div>
        </div>
    </header>

    <section id="what-i-offer">
        <div class="offer-container">
            <div class="offer-header">
                <span class="tag">WHAT I OFFER</span>
                <h2>Explore the core services I provide to help your brands stand out and grow</h2>
            </div>

            <div id="about-cards-section">
                <div class="info-card">
                    <div>
                        <div class="card-header"><span class="dot"></span> ABOUT ME</div>
                        <p class="card-desc">Curious about my journey, mindset, and what I bring to the table? Let's take a deeper look.</p>
                        <div class="about-card-img">
                            <img src="assets/img/aboutme.jpg" alt="About Me">
                        </div>
                    </div>
                    <a href="about.php" class="card-link">Learn More ↗</a>
                </div>

                <div class="info-card">
                    <div>
                        <div class="card-header"><span class="dot"></span> CERTIFICATE</div>
                        <p class="card-desc">Professional achievements and validated skills in technology and design.</p>
                        <div class="cert-marquee-container">
                            <div class="cert-row">
                                <img src="assets/img/certificates/indeep.png" alt="Cert">
                                <img src="assets/img/certificates/smi.png" alt="Cert">
                                <img src="assets/img/certificates/thebestuxieee25.jpg" alt="Cert">
                                <img src="assets/img/certificates/indeep.png" alt="Cert">
                            </div>
                        </div>
                    </div>
                    <a href="certificate.php" class="card-link">Learn More ↗</a>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>