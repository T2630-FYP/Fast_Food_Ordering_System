<?php
require_once 'dataconnection.php';
require_once 'customer_navigation.php';
require_once 'contact_icons.php';

function contact_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function contact_field_attributes($field)
{
    global $errors;
    return isset($errors[$field])
        ? ' aria-invalid="true" aria-describedby="contact-error-'.contact_escape($field).'"'
        : '';
}

function contact_field_error($field)
{
    global $errors;
    if (isset($errors[$field])) {
        echo '<span class="field-error" id="contact-error-'.contact_escape($field).'">'.contact_escape($errors[$field]).'</span>';
    }
}

if (empty($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}

$subjects = array('General Enquiry', 'Feedback', 'Complaint', 'Partnership', 'Others');
$form = array('cust_name'=>'', 'cust_email'=>'', 'subject'=>'', 'cust_message'=>'');
$errors = array();
$success = false;
if ($_SERVER['REQUEST_METHOD']==='GET' && !empty($_SESSION['contact_message_sent'])) {
    $success = true;
    unset($_SESSION['contact_message_sent']);
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['sendbtn'])) {
    foreach ($form as $key=>$value) {
        $form[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
    }
    $token = $_POST['contact_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['contact_csrf'], $token)) {
        $errors['general'] = 'Your session has expired. Please refresh and try again.';
    }
    if ($form['cust_name']==='' || strlen($form['cust_name'])>100) {
        $errors['cust_name'] = 'Please enter your full name using up to 100 characters.';
    }
    if (!filter_var($form['cust_email'], FILTER_VALIDATE_EMAIL) || strlen($form['cust_email'])>100) {
        $errors['cust_email'] = 'Please enter a valid email address.';
    }
    if (!in_array($form['subject'], $subjects, true)) {
        $errors['subject'] = 'Please select a subject.';
    }
    if ($form['cust_message']==='' || strlen($form['cust_message'])>5000) {
        $errors['cust_message'] = 'Please enter your message using up to 5,000 characters.';
    }
    if (count($errors)===0) {
        $stmt = null;
        try {
            $stmt = mysqli_prepare($connect, 'INSERT INTO contact_msg(msg_name,msg_email,msg_subject,msg_message,msg_date) VALUES(?,?,?,?,?)');
            if (!$stmt) throw new RuntimeException('Contact message unavailable');
            $today = date('Y-m-d');
            mysqli_stmt_bind_param($stmt, 'sssss', $form['cust_name'], $form['cust_email'], $form['subject'], $form['cust_message'], $today);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Contact message unavailable');
            $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['contact_message_sent'] = true;
        } catch (Throwable $error) {
            $errors['general'] = 'Unable to send your message right now. Please try again.';
        } finally {
            if ($stmt) mysqli_stmt_close($stmt);
        }
        if (count($errors)===0) {
            header('Location: contact.php?sent=1#contact-form-section');
            exit();
        }
    }
}

// This fictional EasyOrder project uses the verified Marrybrown storefront as its map reference.
$store_map_cid = '12743579893168012323';
// Show the same business profile and storefront photos as Google's own Open in Maps link.
$store_map_url = 'https://maps.google.com/?cid='.$store_map_cid;
$map_embed_url = 'https://maps.google.com/maps?cid='.$store_map_cid.'&z=17&output=embed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Us | EasyOrder</title>
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<link rel="stylesheet" href="style.css?v=20261024-shell1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<link rel="stylesheet" href="contact_redesign.css?v=20261026-contact8">
<script src="customer_navigation.js?v=20261025-account-check1" defer></script>
<script src="contact_redesign.js?v=20261025-contact3" defer></script>
</head>
<body class="contact-page">
<?php easyorder_render_customer_header(); ?>
<div id="navbar" class="customer-site-navbar">
<?php easyorder_render_customer_navigation_links(isset($_SESSION['customer_id'])); ?>
</div>

<main id="main" class="eo-contact-page<?php echo count($errors)>0 ? ' expanded' : ''; ?>" data-start-open="<?php echo count($errors)>0 ? '1' : '0'; ?>">
<div class="contact-container">
    <h1 class="contact-title">Contact Us</h1>

    <section class="location-card" aria-labelledby="contact-store-name">
        <div class="location-visuals">
            <div class="location-map">
                <iframe src="<?php echo contact_escape($map_embed_url); ?>" title="Marrybrown Taman Mount Austin demonstration location on Google Maps" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
            <div class="location-photo">
                <img src="image/store.jpg?v=20261025-landscape1" alt="EasyOrder restaurant exterior at dusk" width="1680" height="1020" fetchpriority="high">
            </div>
        </div>
        <div class="location-info">
            <div class="location-address">
                <span class="location-icon"><?php echo eo_contact_icon('pin'); ?></span>
                <div>
                    <h2 id="contact-store-name">EasyOrder &ndash; Mount Austin</h2>
                    <p>G-55, Jalan Mutiara Emas 10/19, Taman Mount Austin,<br>81100 Johor Bahru, Johor, Malaysia.</p>
                </div>
            </div>
            <div class="location-detail">
                <span class="location-icon"><?php echo eo_contact_icon('clock'); ?></span>
                <div><strong>Open daily</strong><p>10:00 AM &ndash; 10:00 PM</p></div>
            </div>
            <div class="location-detail">
                <span class="location-icon"><?php echo eo_contact_icon('phone'); ?></span>
                <div><strong>Call us</strong><p><a href="tel:+6076849271">+60 7-684 9271</a></p></div>
            </div>
            <a class="directions-btn" href="<?php echo contact_escape($store_map_url); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo eo_contact_icon('directions'); ?><span>Get directions</span><?php echo eo_contact_icon('arrow'); ?>
            </a>
        </div>
    </section>

    <section class="contact-section" id="contact-form-section" aria-labelledby="contact-heading">
        <h2 class="contact-heading" id="contact-heading">We're all ears.</h2>
        <?php if ($success): ?>
        <div class="contact-success" role="status">Thank you for contacting EasyOrder! Your message has been received.</div>
        <?php endif; ?>
        <div class="contact-panel">
            <aside class="contact-left" aria-labelledby="contact-team-heading">
                <span class="contact-eyebrow">REACH OUR TEAM</span>
                <h3 id="contact-team-heading">Let's stay<br>connected.</h3>
                <p class="contact-description">Whether it's an enquiry, feedback or a little help, we're just a message away.</p>
                <div class="contact-info-item">
                    <span class="contact-info-icon"><?php echo eo_contact_icon('mail'); ?></span>
                    <div><small>EMAIL US</small><strong><a href="mailto:support@easyorder.com">support@easyorder.com</a></strong></div>
                </div>
                <div class="contact-info-item">
                    <span class="contact-info-icon"><?php echo eo_contact_icon('phone'); ?></span>
                    <div><small>CALL US</small><strong><a href="tel:+6076849271">+60 7-684 9271</a></strong></div>
                </div>
                <div class="contact-info-item">
                    <span class="contact-info-icon"><?php echo eo_contact_icon('pin'); ?></span>
                    <div><small>VISIT OUR STORE</small><strong>EasyOrder &ndash; Mount Austin</strong></div>
                </div>
                <div class="contact-motto">GOOD FOOD. GOOD PEOPLE. GOOD SERVICE.</div>
            </aside>
            <div class="contact-right">
                <span class="contact-eyebrow-right">WRITE TO US</span>
                <h3 id="contact-message-heading">Send us a message</h3>
                <p class="contact-subtitle">A note from you means a lot to us.</p>
                <div class="contact-divider"></div>

                <div class="message-prompt" id="message-prompt" hidden>
                    <span class="message-icon"><?php echo eo_contact_icon('message'); ?></span>
                    <h4>We'd love to hear from you.</h4>
                    <p>Have a question or suggestion? Send a message and our team will get back to you.</p>
                    <button type="button" id="open-message-btn" class="primary-contact-btn" aria-expanded="false" aria-controls="contact-message-form">Leave us a message <span aria-hidden="true">&rarr;</span></button>
                </div>

                <form id="contact-message-form" name="contactfrm" class="contact-message-form" method="post" action="contact.php#contact-form-section" aria-labelledby="contact-message-heading" aria-hidden="false">
                    <?php if (isset($errors['general'])): ?>
                    <p class="form-error" role="alert"><?php echo contact_escape($errors['general']); ?></p>
                    <?php endif; ?>
                    <input type="hidden" name="contact_token" value="<?php echo contact_escape($_SESSION['contact_csrf']); ?>">
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="cust_name">Full name <em>*</em></label>
                            <input type="text" id="cust_name" name="cust_name" maxlength="100" autocomplete="name" placeholder="Your full name" value="<?php echo contact_escape($form['cust_name']); ?>"<?php echo contact_field_attributes('cust_name'); ?> required>
                            <?php contact_field_error('cust_name'); ?>
                        </div>
                        <div class="form-field">
                            <label for="cust_email">Email address <em>*</em></label>
                            <input type="email" id="cust_email" name="cust_email" maxlength="100" autocomplete="email" placeholder="e.g. customer@email.com" value="<?php echo contact_escape($form['cust_email']); ?>"<?php echo contact_field_attributes('cust_email'); ?> required>
                            <?php contact_field_error('cust_email'); ?>
                        </div>
                        <div class="form-field full-width">
                            <label for="subject">What is this regarding? <em>*</em></label>
                            <select id="subject" name="subject"<?php echo contact_field_attributes('subject'); ?> required>
                                <option value="">Select a subject</option>
                                <?php foreach ($subjects as $option): ?>
                                <option value="<?php echo contact_escape($option); ?>"<?php echo $form['subject']===$option ? ' selected' : ''; ?>><?php echo contact_escape($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php contact_field_error('subject'); ?>
                        </div>
                        <div class="form-field full-width">
                            <label for="cust_message">Your message <em>*</em></label>
                            <textarea id="cust_message" name="cust_message" rows="5" maxlength="5000" placeholder="Tell us a little more about how we can help..."<?php echo contact_field_attributes('cust_message'); ?> required><?php echo contact_escape($form['cust_message']); ?></textarea>
                            <?php contact_field_error('cust_message'); ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="sendbtn" value="1" class="primary-contact-btn">Send message <span aria-hidden="true">&rarr;</span></button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
</main>
<?php easyorder_render_customer_footer(isset($_SESSION['customer_id'])); ?>
</body>
</html>
