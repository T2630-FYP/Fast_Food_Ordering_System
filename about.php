<?php
require_once(__DIR__."/dataconnection.php");
require_once(__DIR__."/customer_navigation.php");
header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">

<head><!--About EasyOrder and group member information page-->
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Us</title>
<link rel="stylesheet" href="public.css?v=20261024-shell1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Charis+SIL:wght@400;700&amp;family=PT+Serif:wght@400;700&amp;display=swap">
<link rel="stylesheet" href="customer.css?v=20261027-consolidated1">
<script src="customer.js?v=20261028-consolidated1" defer></script>

</head>

<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!--Customer navigation bar-->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="eo-about-brown">
<div class="eo-about-wrap">
<h1 class="eo-about-page-title">About Us</h1>

<section class="eo-about-hero" aria-labelledby="about-story-title">
<figure class="eo-about-photo-frame">
<div class="eo-about-photo">
<img src="image/about.jpg" width="1200" height="1000" alt="The EasyOrder kitchen team preparing freshly made burgers">
</div>
</figure>
<div class="eo-about-copy">
<h2 id="about-story-title">Good food.<br>Made easy.</h2>
<p>Founded in 2024, EasyOrder was created with a simple belief that good food should be easy to enjoy. We take pride in freshly prepared meals, friendly service, and a convenient ordering experience. From a quick lunch to dinner with family, our goal is to make every meal satisfying and memorable.</p>
</div>
</section>
</div>

<section class="eo-orbit" data-eo-orbit aria-labelledby="about-team-title" aria-roledescription="carousel">
<h2 id="about-team-title" class="eo-orbit-title">Our Teams</h2>
<div class="eo-orbit-carousel">
<div class="eo-orbit-floor" aria-hidden="true">
<svg viewBox="0 0 1200 170" preserveAspectRatio="none" focusable="false" aria-hidden="true">
<defs>
<radialGradient id="about-orbit-shadow">
<stop offset="0" stop-color="#97703E" stop-opacity=".24"/>
<stop offset=".45" stop-color="#A98555" stop-opacity=".14"/>
<stop offset="1" stop-color="#B89A73" stop-opacity="0"/>
</radialGradient>
<linearGradient id="about-orbit-back" x1="0%" y1="0%" x2="100%" y2="0%">
<stop offset="0" stop-color="#DBB786" stop-opacity="0"/>
<stop offset=".25" stop-color="#DCB782" stop-opacity=".44"/>
<stop offset=".65" stop-color="#D7AD72" stop-opacity=".5"/>
<stop offset="1" stop-color="#DBB786" stop-opacity=".04"/>
</linearGradient>
<linearGradient id="about-orbit-gold" x1="0%" y1="0%" x2="100%" y2="0%">
<stop offset="0" stop-color="#E3C297" stop-opacity=".06"/>
<stop offset=".18" stop-color="#D6A052" stop-opacity=".72"/>
<stop offset=".5" stop-color="#D9A65D" stop-opacity=".9"/>
<stop offset=".78" stop-color="#D8AA65" stop-opacity=".65"/>
<stop offset="1" stop-color="#E3C297" stop-opacity=".04"/>
</linearGradient>
</defs>
<ellipse cx="600" cy="88" rx="430" ry="54" fill="url(#about-orbit-shadow)"/>
<path d="M95 89C274 13 900 10 1110 76" fill="none" stroke="url(#about-orbit-back)" stroke-width="1.1" vector-effect="non-scaling-stroke"/>
<ellipse cx="600" cy="87" rx="484" ry="45" fill="none" stroke="url(#about-orbit-back)" stroke-width=".8" vector-effect="non-scaling-stroke"/>
<path d="M40 100C185 139 350 151 566 153M606 153C810 154 996 140 1148 116" fill="none" stroke="url(#about-orbit-gold)" stroke-width="1.3" vector-effect="non-scaling-stroke"/>
<path d="M159 111C395 145 785 147 1044 109M1132 105C1168 95 1185 86 1191 79" fill="none" stroke="url(#about-orbit-back)" stroke-width=".8" vector-effect="non-scaling-stroke"/>
</svg>
</div>
<div id="about-team-cards" class="eo-orbit-stage">

<article class="eo-orbit-card" data-orbit-card>
<div class="eo-orbit-photo">
<img src="image/member2.jpg" width="414" height="420" alt="Ho Kwun Yan" draggable="false">
</div>
<h3 class="eo-orbit-name">Ho Kwun Yan</h3>
<span class="eo-orbit-divider" aria-hidden="true"></span>
<p class="eo-orbit-role">Co-Founder</p>
</article>

<article class="eo-orbit-card" data-orbit-card data-orbit-initial>
<div class="eo-orbit-photo eo-orbit-photo--eric">
<img src="image/member1.jpg" width="1024" height="1024" alt="Eric Ong Yong Zhen" draggable="false">
</div>
<h3 class="eo-orbit-name">Eric Ong Yong Zhen</h3>
<span class="eo-orbit-divider" aria-hidden="true"></span>
<p class="eo-orbit-role">CEO &amp; Founder</p>
</article>

<article class="eo-orbit-card" data-orbit-card>
<div class="eo-orbit-photo">
<img src="image/member3.jpg" width="420" height="420" alt="Ng Kai Jie" draggable="false">
</div>
<h3 class="eo-orbit-name">Ng Kai Jie</h3>
<span class="eo-orbit-divider" aria-hidden="true"></span>
<p class="eo-orbit-role">Co-Founder</p>
</article>

</div>
<button class="eo-orbit-arrow eo-orbit-prev" type="button" data-orbit-prev aria-label="Previous member" aria-controls="about-team-cards">
<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15.5 4.5-7.5 7.5 7.5 7.5"/></svg>
</button>
<button class="eo-orbit-arrow eo-orbit-next" type="button" data-orbit-next aria-label="Next member" aria-controls="about-team-cards">
<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m8.5 4.5 7.5 7.5-7.5 7.5"/></svg>
</button>
</div>
<p class="eo-orbit-status" data-orbit-status aria-live="polite" aria-atomic="true">Eric Ong Yong Zhen, CEO &amp; Founder. Member 2 of 3.</p>
</section>
</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

</body>

</html>
