<?php
/**
 * includes/head.php
 * Shared <head> asset links for all SEMS pages (dashboard, sales,
 * expenses, reports, users, products).
 *
 * Each calling page keeps its own <title> and page-specific <style>
 * block; this include handles the common external dependencies so
 * they only need to be updated in one place.
 */
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<link rel="icon" href="favicon.svg" type="image/svg+xml">

<link rel="stylesheet" href="style.css">