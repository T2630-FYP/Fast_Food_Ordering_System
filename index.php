<?php
require_once(__DIR__."/dataconnection.php");
require_once(__DIR__."/customer_navigation.php");
require_once(__DIR__."/product_catalog_helpers.php");
$eo_categories = catalog_public_categories($connect);
$eo_logged_in = isset($_SESSION["customer_id"]);
$eo_menu_url = $eo_logged_in ? "category.php" : "guest_menu.php";
$eo_order_url = $eo_logged_in ? "category.php" : "login.php";
header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">

<head><!--Home page of EasyOrder website-->
<link rel="icon" type="image/png" href="image/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EasyOrder - Fast Food Ordering</title>
<link rel="stylesheet" href="style.css?v=20261024-shell1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<link rel="stylesheet" href="home_section.css?v=20261024-shell1">
<script src="customer_navigation.js?v=20261024-shell1" defer></script>
<script src="home_section.js?v=20261023-1" defer></script>
</head>

<body class="eo-home" data-session-aware>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!--Customer navigation bar-->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main class="eo-block">

    <!-- HERO SECTION -->
    <section class="eo-hero">

        <div class="eo-hero-copy">

            <p class="eo-eyebrow">
                <span class="eo-eyebrow-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M4 3v5a3 3 0 0 0 6 0V3M7 3v18" />
                        <path d="M20 3c-4 0-6 3-6 8v3h6M20 3v18" />
                    </svg>
                </span>
                YOUR FAVOURITES, MADE SIMPLE
            </p>

            <h1>
                Big cravings.<br>
                <span>Better bites.</span>
            </h1>

            <p class="eo-lead">
                Juicy burgers, golden crispy chicken
                and sweet little treats. All your
                favourites, just a few clicks away.
            </p>

            <div class="eo-actions">

                <a href="<?php echo htmlspecialchars($eo_order_url,ENT_QUOTES,'UTF-8'); ?>"
                   class="eo-order-btn home-cta">
                    Order Now
                    <svg class="eo-action-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M5 12h14m-6-6 6 6-6 6" />
                    </svg>
                </a>

                <a href="<?php echo htmlspecialchars($eo_menu_url); ?>"
                   class="eo-explore-btn">
                    Explore Menu
                    <svg class="eo-action-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M5 12h14m-6-6 6 6-6 6" />
                    </svg>
                </a>

            </div>

            <p class="eo-note">
                <span class="eo-note-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M10 4.5c.7 5 2.3 6.6 7.3 7.3-5 .7-6.6 2.3-7.3 7.3-.7-5-2.3-6.6-7.3-7.3 5-.7 6.6-2.3 7.3-7.3Z" />
                        <circle cx="19" cy="4" r="1.5" />
                    </svg>
                </span>
                Your next favourite meal starts here.
            </p>

        </div>

        <div class="eo-hero-picture">
            <img
                src="image/banner.jpg?v=<?php echo filemtime(__DIR__.'/image/banner.jpg'); ?>"
                alt="Burger, fried chicken, fries and cola">
        </div>

    </section>


    <!-- THREE BENEFITS -->
    <section class="eo-benefits">

        <div class="eo-benefit">
            <div class="eo-benefit-icon">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 7h12v11H3z"/>
                    <path d="M15 10h3.2l3 3.5V18H15"/>
                    <circle cx="7.2" cy="18" r="2"/>
                    <circle cx="18.2" cy="18" r="2"/>
                </svg>
            </div>

            <div>
                <h3>Fast Delivery</h3>
                <p>Your favourites, delivered fast</p>
            </div>
        </div>

        <div class="eo-benefit">
            <div class="eo-benefit-icon">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2 20 5v6c0 5-3.1 8.7-8 11-4.9-2.3-8-6-8-11V5l8-3Z"/>
                    <path d="m8.5 12 2.2 2.2 4.8-4.8"/>
                </svg>
            </div>

            <div>
                <h3>Safe &amp; Secure</h3>
                <p>Order with confidence</p>
            </div>
        </div>

        <div class="eo-benefit">
            <div class="eo-benefit-icon">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 2.5 3 6.2 6.8 1-4.9 4.8 1.2 6.8-6.1-3.2-6.1 3.2 1.2-6.8-4.9-4.8 6.8-1L12 2.5Z"/>
                </svg>
            </div>

            <div>
                <h3>Best Quality</h3>
                <p>Made fresh, served with care</p>
            </div>
        </div>

    </section>


    <!-- FOOD CATEGORY SLIDER -->
    <section class="eo-categories">

        <div class="eo-categories-heading">

            <div>
                <p class="eo-categories-label">
                    A LITTLE SOMETHING FOR EVERY CRAVING
                </p>

                <h2>
                    What sounds <span>good?</span>
                </h2>
            </div>

            <div class="eo-slider-controls">
                <span class="eo-counter" id="eo-counter" aria-live="polite" aria-atomic="true">
                    01 / 03
                </span>

                <button class="eo-slide-prev"
                        type="button"
                        aria-label="Previous category"
                        disabled><svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M12.5 5L7.5 10L12.5 15"></path></svg></button>

                <button class="eo-slide-next"
                        type="button"
                        aria-label="Next category"><svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M7.5 5L12.5 10L7.5 15"></path></svg></button>
            </div>

        </div>

        <div class="eo-slider" id="eo-slider" tabindex="0" role="region" aria-roledescription="carousel" aria-label="Food categories">
            <div class="eo-card-track">

                <?php foreach ($eo_categories as $category):

                    $name = $category['category_name'];

                    $category_url = $eo_logged_in
                        ? 'category.php?category=' .
                          rawurlencode($category['category_id'])
                        : 'guest_menu.php?category=' . rawurlencode($category['category_id']) . '#guest-menu-products';
                ?>

                <a class="eo-food-card"
                   href="<?php echo htmlspecialchars($category_url, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="eo-food-photo">
                        <img
                            src="<?php echo htmlspecialchars($category['image'], ENT_QUOTES, 'UTF-8'); ?>"
                            alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                            draggable="false">
                    </div>

                    <div class="eo-food-details">
                        <div>
                            <h3>
                                <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                            </h3>

                            <p>
                                <?php echo htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        </div>

                        <span class="eo-food-arrow" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M9 6l6 6-6 6" />
                            </svg>
                        </span>
                    </div>

                </a>

                <?php endforeach; ?>

            </div>
        </div>

        <p class="eo-slider-hint">
            <svg class="eo-slider-hint-icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <path d="M3 10h14M6 7l-3 3 3 3m8-6 3 3-3 3" />
            </svg>
            <span>Scroll sideways to explore all <?php echo count($eo_categories); ?> categories</span>
        </p>

    </section>

</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

</body>

</html>
