<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>RequestRelay</title><link rel="stylesheet" href="/assets/style.css">
<script src="/assets/app.js" defer></script>
</head>
<body>
<header><a class="brand" href="/">RequestRelay</a><nav aria-label="Main"><a href="/">Submit request</a><a href="/dashboard">Internal dashboard</a></nav></header>
<main>

<p class="eyebrow">CUSTOMER REQUESTS</p>
<h1>Tell us what you need.</h1>
<p>Send a print or service request for our team to review. This independent demo uses fictional information only.</p>
<form id="request-form">
<div class="grid">
<label>Customer name <input name="customer_name" required maxlength="100" autocomplete="name"></label>
<label>Email <input name="customer_email" type="email" required maxlength="255" autocomplete="email"></label>
<label>Service type <select name="service_type" required><option value="">Choose a service</option><option>Business cards</option><option>Flyers</option><option>Booklets</option><option>Signs</option><option>Other</option></select></label>
<label>Quantity <input name="quantity" type="number" min="1" max="4294967295" step="1" required></label>
<label>Due date (optional) <input name="due_date" type="date" min="1000-01-01" max="9999-12-31"></label>
</div>
<label>Description (optional) <textarea name="request_description" rows="5" placeholder="Describe the service you need."></textarea></label>
<button type="submit">Submit request</button>
<p id="feedback" role="status" aria-live="polite"></p>
</form>
</main><footer>RequestRelay · Independent technical demonstration · No real customer data</footer>
</body></html>
