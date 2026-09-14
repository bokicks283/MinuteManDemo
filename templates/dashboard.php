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

<p class="eyebrow">INTERNAL WORKSPACE</p>
<h1>Service requests</h1>
<p>Review requests and move work through its next step. Demo access is unauthenticated.</p>
<button id="refresh" type="button">Refresh requests</button>
<p id="feedback" role="status" aria-live="polite"></p>
<div class="workspace">
<section aria-label="Request list"><div class="table-wrap"><table>
<thead><tr><th>Request / customer</th><th>Service</th><th>Status</th><th>Details</th></tr></thead>
<tbody id="requests"></tbody></table></div><p id="empty" hidden>No requests yet. Submit one to get started.</p></section>
<section id="detail" aria-label="Request details" tabindex="-1"><h2>Request details</h2><p>Select a request to see details and status history.</p></section>
</div>
</main><footer>RequestRelay · Independent technical demonstration · No real customer data</footer>
</body></html>
