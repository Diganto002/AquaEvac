<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$bootstrap_user = current_user();
if ($bootstrap_user !== null) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
<title>Choose a panel &mdash; AquaEvac</title>
<link rel="icon" type="image/png" href="assets/aquaevac-favicon.png">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          paper:   { DEFAULT: '#FFFFFF', soft: '#FAF9F6' },
          ink:     { 950: '#0A0A0A', 700: '#3F3F46', 400: '#78716C' },
          ember:   { glow: '#FB923C', DEFAULT: '#F97316' },
          amber:   { DEFAULT: '#D97706' },
          sky:     { DEFAULT: '#0284C7' },
        },
        fontFamily: {
          display: ['"Space Grotesk"', 'sans-serif'],
          sans: ['Inter', 'sans-serif'],
        },
        boxShadow: {
          'glow-ember': '0 0 22px 2px rgba(249,115,22,0.25)',
          'glow-amber': '0 0 22px 2px rgba(217,119,6,0.25)',
          'glow-sky': '0 0 22px 2px rgba(2,132,199,0.25)',
        },
      },
    },
  };
</script>
<style>
  html, body { height: 100%; margin: 0; background: #07111e; color: #f8fafc; }
  body { font-family: 'Inter', sans-serif; }
  .panel-card { transition: transform 220ms ease, border-color 220ms ease, box-shadow 220ms ease; }
  .panel-card:hover { transform: translateY(-4px); }
  :focus-visible { outline: 2px solid #F97316; outline-offset: 2px; }
</style>
</head>
<body class="font-sans text-slate-100 min-h-screen flex flex-col relative overflow-x-hidden">

<header class="px-5 sm:px-8 h-16 flex items-center justify-between max-w-6xl mx-auto w-full flex-shrink-0 relative z-10">
  <a href="index.php" class="flex items-center gap-2.5">
    <img src="assets/aquaevac-icon.png" alt="AquaEvac" class="w-9 h-9 object-contain">
    <span class="font-display font-semibold text-lg text-white">AquaEvac</span>
  </a>
  <a href="index.php" class="text-xs text-slate-300 hover:text-white transition-colors">&larr; Back to home</a>
</header>

<main class="flex-1 flex items-center justify-center px-5 py-14 relative z-10">
  <div class="w-full max-w-5xl">
    <div class="text-center mb-10">
      <p class="text-ember text-xs font-semibold tracking-widest uppercase mb-2">Step 1 of 2</p>
      <h1 class="font-display font-semibold text-3xl sm:text-4xl text-white">Which panel do you need?</h1>
      <p class="text-slate-300 text-sm mt-3">Choose one, then sign in. You'll only ever see the tools for your role.</p>
    </div>

    <div class="grid sm:grid-cols-3 gap-5">
      <!-- User panel -->
      <a href="login.php?panel=user" class="panel-card group block rounded-2xl bg-slate-900/85 backdrop-blur-md shadow-2xl border border-white/10 hover:border-ember/60 hover:shadow-glow-ember p-7">
        <div class="w-12 h-12 rounded-xl bg-ember/15 border border-ember/40 flex items-center justify-center mb-5">
          <svg class="w-6 h-6 text-ember" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 1 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <h2 class="font-display font-semibold text-xl text-white mb-1.5">User panel</h2>
        <p class="text-sm text-slate-300 leading-relaxed mb-5">
          For citizens and volunteers. Check flood risk, plan safe routes,
          find shelters, and send an Emergency SOS.
        </p>
        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-ember">
          Continue as User
          <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
        </span>
      </a>

      <!-- Responder panel -->
      <a href="login.php?panel=responder" class="panel-card group block rounded-2xl bg-slate-900/85 backdrop-blur-md shadow-2xl border border-white/10 hover:border-sky/60 hover:shadow-glow-sky p-7">
        <div class="w-12 h-12 rounded-xl bg-sky/15 border border-sky/40 flex items-center justify-center mb-5">
          <svg class="w-6 h-6 text-sky" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v4m0 12v4m10-10h-4M6 12H2m15.07-7.07-2.83 2.83M9.76 14.24l-2.83 2.83m0-10.14 2.83 2.83m7.31 7.31 2.83 2.83"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <h2 class="font-display font-semibold text-xl text-white mb-1.5">Responder panel</h2>
        <p class="text-sm text-slate-300 leading-relaxed mb-5">
          For field responders. See live SOS locations, accept a call,
          share your own live location, and get an ETA to reach them.
        </p>
        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-sky">
          Continue as Responder
          <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
        </span>
      </a>

      <!-- Admin panel -->
      <a href="login.php?panel=admin" class="panel-card group block rounded-2xl bg-slate-900/85 backdrop-blur-md shadow-2xl border border-white/10 hover:border-amber/60 hover:shadow-glow-amber p-7">
        <div class="w-12 h-12 rounded-xl bg-amber/15 border border-amber/40 flex items-center justify-center mb-5">
          <svg class="w-6 h-6 text-amber" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 6.5v5C4 16.3 7.4 20 12 21c4.6-1 8-4.7 8-9.5v-5L12 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 12l1.8 1.8L14.5 10"/></svg>
        </div>
        <h2 class="font-display font-semibold text-xl text-white mb-1.5">Admin panel</h2>
        <p class="text-sm text-slate-300 leading-relaxed mb-5">
          For operations staff. Everything above, plus the power to block
          roads, manage shelters, and oversee every SOS dispatch.
        </p>
        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-amber">
          Continue as Admin
          <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
        </span>
      </a>
    </div>
  </div>
</main>

<script src="assets/i18n.js"></script>
<script src="assets/flood-rescue-animation.js?v=<?php echo filemtime(__DIR__ . '/assets/flood-rescue-animation.js'); ?>"></script>
</body>
</html>
