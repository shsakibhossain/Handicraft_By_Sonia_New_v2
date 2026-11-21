<?php
require_once __DIR__.'/helpers.php'; page_start('Content Management'); ?>
<div class="card"><h2>Website Content Management</h2><p class="muted">Manage About Us, Contact Information and FAQ content displayed on the customer website.</p>
<div class="grid">
<div class="card"><h2>About Us</h2><p class="muted">Hero text, Our Story, Mission, Vision and other introduction content.</p><a class="btn" href="about.php">Manage About Us</a></div>
<div class="card"><h2>Contact Information</h2><p class="muted">Email, phone, social links, address and Google Maps.</p><a class="btn" href="contact.php">Manage Contact</a></div>
<div class="card"><h2>FAQ</h2><p class="muted">Add, edit, delete, sort and activate questions and answers.</p><a class="btn" href="faq.php">Manage FAQ</a></div>
</div></div><?php page_end(); ?>