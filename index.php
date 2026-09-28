<?php
declare(strict_types=1);
// If someone is already logged in and lands here, send them straight to
// their command center instead of showing the marketing page again.
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
<title>AquaEvac &mdash; AI-Based Disaster Evacuation &amp; Safe Route Planner</title>
<link rel="icon" type="image/png" href="assets/aquaevac-favicon.png">

<!-- PWA -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#F97316">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="AquaEvac">
<link rel="apple-touch-icon" href="assets/aquaevac-icon.png">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          paper:   { DEFAULT: '#FFFFFF', soft: '#FAF9F6' },
          ink:     { 950: '#0A0A0A', 700: '#3F3F46', 400: '#78716C' },
          ember:   { glow: '#FB923C', DEFAULT: '#F97316' },
          crimson: { DEFAULT: '#DC2626' },
          amber:   { DEFAULT: '#D97706' },
          emerald: { DEFAULT: '#16A34A' },
        },
        fontFamily: {
          display: ['"Space Grotesk"', 'sans-serif'],
          sans: ['Inter', 'sans-serif'],
          mono: ['"JetBrains Mono"', 'monospace'],
        },
        boxShadow: {
          'glow-ember': '0 0 18px 2px rgba(249,115,22,0.30)',
          'glow-crimson': '0 0 18px 2px rgba(220,38,38,0.30)',
        },
      },
    },
  };
</script>

<style>
  html, body { margin: 0; background: transparent; color: #f8fafc; overflow-x: hidden; }
  body { font-family: 'Inter', sans-serif; }

  @keyframes float-slow {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
  }
  .float-slow { animation: float-slow 5s ease-in-out infinite; }

  @keyframes sos-pulse {
    0%   { box-shadow: 0 0 0 0 rgba(220,38,38,0.55); }
    70%  { box-shadow: 0 0 0 14px rgba(220,38,38,0); }
    100% { box-shadow: 0 0 0 0 rgba(220,38,38,0); }
  }
  .sos-dot { animation: sos-pulse 2s cubic-bezier(0.4,0,0.6,1) infinite; }

  :focus-visible { outline: 2px solid #F97316; outline-offset: 2px; }
</style>
</head>
<body class="font-sans text-slate-100 antialiased relative min-h-screen">

<!-- ============================= NAVBAR ============================= -->
<header class="sticky top-0 z-50 border-b border-white/10 bg-slate-950/75 backdrop-blur-md">
  <div class="max-w-6xl mx-auto px-5 sm:px-8 h-16 flex items-center justify-between">
    <a href="index.php" class="flex items-center gap-2.5">
      <img src="assets/aquaevac-icon.png" alt="AquaEvac" class="w-9 h-9 object-contain">
      <span class="font-display font-extrabold text-lg tracking-tight">
        <span class="text-white">Aqua</span><span class="text-ember">Evac</span>
      </span>
    </a>
    <nav class="hidden sm:flex items-center gap-7 text-sm text-slate-300">
      <a href="#features" class="hover:text-white transition-colors">Features</a>
      <a href="#how-it-works" class="hover:text-white transition-colors">How it works</a>
      <a href="#stack" class="hover:text-white transition-colors">Tech</a>
    </nav>
    <a href="select-panel.php"
       class="rounded-lg bg-ember text-white text-sm font-semibold px-4 py-2 hover:bg-ember-glow transition-all shadow-glow-ember">
      Get Started
    </a>
  </div>
</header>

<!-- ============================= HERO ============================= -->
<section class="relative overflow-hidden pt-10 pb-14 sm:pt-14 sm:pb-18 flex items-center justify-center">
  <div class="max-w-5xl mx-auto px-5 sm:px-8 text-center relative z-10 w-full">
    <img src="assets/aquaevac-logo-full.png" alt="AquaEvac" class="mx-auto w-auto h-20 sm:h-28 object-contain mb-4 drop-shadow-2xl filter brightness-110">
    <div class="inline-flex items-center gap-2 rounded-full border border-orange-500/40 bg-slate-900/85 backdrop-blur-md px-4 py-1.5 text-xs text-orange-200 mb-4 shadow-xl">
      <span class="w-2 h-2 rounded-full bg-crimson sos-dot"></span>
      Live AI-Assisted Disaster Evacuation &amp; Emergency Storm Rescue
    </div>
    <h1 class="font-display font-bold text-3xl sm:text-5xl leading-tight max-w-4xl mx-auto text-white drop-shadow-2xl">
      Get people to safety <span class="text-ember">before</span> the water does.
    </h1>
    <p class="mt-4 text-slate-200 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed drop-shadow-md">
      AquaEvac predicts flood risk, plans the safest evacuation route around blocked
      and hazardous roads, matches citizens to the nearest shelter with room,
      and coordinates emergency rescue boats and dispatchers &mdash; live, on one map.
    </p>
    <div class="mt-7 flex flex-col sm:flex-row items-center justify-center gap-4">
      <a href="select-panel.php"
         class="w-full sm:w-auto rounded-xl bg-ember text-white font-semibold text-base px-8 py-3.5 hover:bg-ember-glow transition-all shadow-glow-ember hover:scale-105">
        Get Started &rarr;
      </a>
      <a href="#features"
         class="w-full sm:w-auto rounded-xl border border-white/20 bg-slate-900/70 backdrop-blur-md text-white text-base font-medium px-7 py-3.5 hover:bg-white/15 transition-all">
        See what it does
      </a>
    </div>

    <!-- Mini stat strip with glassmorphism -->
    <div class="mt-8 float-slow inline-grid grid-cols-3 gap-px rounded-2xl overflow-hidden border border-white/15 bg-white/10 backdrop-blur-md max-w-xl mx-auto shadow-2xl">
      <div class="bg-slate-950/85 px-6 py-3.5">
        <p class="font-mono text-2xl font-bold text-ember">A*</p>
        <p class="text-[11px] text-slate-300 mt-1">hazard-aware routing</p>
      </div>
      <div class="bg-slate-950/85 px-6 py-3.5">
        <p class="font-mono text-2xl font-bold text-amber">CSP</p>
        <p class="text-[11px] text-slate-300 mt-1">shelter matching</p>
      </div>
      <div class="bg-slate-950/85 px-6 py-3.5">
        <p class="font-mono text-2xl font-bold text-crimson">SOS</p>
        <p class="text-[11px] text-slate-300 mt-1">live rescue dispatch</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================= FEATURES ============================= -->
<section id="features" class="border-t border-white/10 bg-slate-950/75 backdrop-blur-md">
  <div class="max-w-6xl mx-auto px-5 sm:px-8 py-20">
    <div class="max-w-xl mb-12">
      <p class="text-ember text-xs font-semibold tracking-widest uppercase mb-2">What AquaEvac does</p>
      <h2 class="font-display font-semibold text-2xl sm:text-3xl text-white">Four systems, one command center</h2>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
      <!-- Feature 1 -->
      <div class="rounded-2xl bg-slate-900/80 shadow-lg border border-white/10 p-6 hover:border-ember/50 transition-all">
        <div class="w-10 h-10 rounded-xl bg-amber/10 border border-amber/30 flex items-center justify-center mb-4">
          <svg class="w-5 h-5 text-amber" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2.7s6.5 7 6.5 11.3a6.5 6.5 0 1 1-13 0C5.5 9.7 12 2.7 12 2.7Z"/></svg>
        </div>
        <h3 class="font-display font-semibold text-lg text-white mb-1.5">Flood risk prediction</h3>
        <p class="text-sm text-slate-300 leading-relaxed">
          A logistic regression model trained on rainfall, river level, and
          elevation data scores any location as Low, Medium, or High risk,
          with full confidence breakdown.
        </p>
      </div>
      <!-- Feature 2 -->
      <div class="rounded-2xl bg-slate-900/80 shadow-lg border border-white/10 p-6 hover:border-ember/50 transition-all">
        <div class="w-10 h-10 rounded-xl bg-ember/10 border border-ember/30 flex items-center justify-center mb-4">
          <svg class="w-5 h-5 text-ember" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.5-2V4L9 6m0 14l6-2m-6 2V6m6 12l5.5 2V6L15 4m0 14V4m0 0L9 6"/></svg>
        </div>
        <h3 class="font-display font-semibold text-lg text-white mb-1.5">Safe route planner</h3>
        <p class="text-sm text-slate-300 leading-relaxed">
          A* search over the live road graph, weighted by distance, flood
          hazard, and traffic &mdash; and it can never route through a road an
          admin has marked blocked.
        </p>
      </div>
      <!-- Feature 3 -->
      <div class="rounded-2xl bg-slate-900/80 shadow-lg border border-white/10 p-6 hover:border-ember/50 transition-all">
        <div class="w-10 h-10 rounded-xl bg-ember/10 border border-ember/30 flex items-center justify-center mb-4">
          <svg class="w-5 h-5 text-ember" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg>
        </div>
        <h3 class="font-display font-semibold text-lg text-white mb-1.5">Smart shelter finder</h3>
        <p class="text-sm text-slate-300 leading-relaxed">
          A constraint-satisfaction solver ranks shelters by capacity, distance,
          and route safety &mdash; and tells you exactly why the rest were ruled
          out.
        </p>
      </div>
      <!-- Feature 4 -->
      <div class="rounded-2xl bg-slate-900/80 shadow-lg border border-white/10 p-6 hover:border-crimson/50 transition-all">
        <div class="w-10 h-10 rounded-xl bg-crimson/10 border border-crimson/30 flex items-center justify-center mb-4">
          <svg class="w-5 h-5 text-crimson" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2 19h20L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        </div>
        <h3 class="font-display font-semibold text-lg text-white mb-1.5">Emergency SOS &amp; dispatch</h3>
        <p class="text-sm text-slate-300 leading-relaxed">
          One tap sends a citizen's live location to responders. Admins see
          every alert on a live feed and move it from pending &rarr;
          acknowledged &rarr; resolved.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- ============================= HOW IT WORKS ============================= -->
<section id="how-it-works" class="border-t border-white/10 bg-slate-950/70 backdrop-blur-md">
  <div class="max-w-6xl mx-auto px-5 sm:px-8 py-20">
    <div class="max-w-xl mb-12">
      <p class="text-ember text-xs font-semibold tracking-widest uppercase mb-2">How it works</p>
      <h2 class="font-display font-semibold text-2xl sm:text-3xl text-white">Two panels, one shared map</h2>
      <p class="text-sm text-slate-300 mt-3 leading-relaxed">
        Everyone signs in through the same door, then sees exactly what their
        role needs &mdash; nothing more.
      </p>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
      <div class="rounded-2xl border border-ember/30 bg-slate-900/80 backdrop-blur-md p-6">
        <span class="inline-flex items-center gap-1.5 text-[10px] font-medium tracking-wide uppercase text-ember bg-ember/10 border border-ember/30 rounded-full px-2.5 py-1 mb-4">User panel</span>
        <ul class="space-y-3 text-sm text-slate-300">
          <li class="flex gap-2.5"><span class="text-ember mt-0.5">&#10003;</span>Check flood risk for any reading</li>
          <li class="flex gap-2.5"><span class="text-ember mt-0.5">&#10003;</span>Plan the safest route to anywhere on the network</li>
          <li class="flex gap-2.5"><span class="text-ember mt-0.5">&#10003;</span>Get matched to the nearest open shelter with room</li>
          <li class="flex gap-2.5"><span class="text-ember mt-0.5">&#10003;</span>Send an Emergency SOS with one tap</li>
        </ul>
      </div>
      <div class="rounded-2xl border border-amber/30 bg-slate-900/80 backdrop-blur-md p-6">
        <span class="inline-flex items-center gap-1.5 text-[10px] font-medium tracking-wide uppercase text-amber bg-amber/10 border border-amber/30 rounded-full px-2.5 py-1 mb-4">Admin panel</span>
        <ul class="space-y-3 text-sm text-slate-300">
          <li class="flex gap-2.5"><span class="text-amber mt-0.5">&#10003;</span>Everything in the user panel, plus:</li>
          <li class="flex gap-2.5"><span class="text-amber mt-0.5">&#10003;</span>Click any road on the map to block/unblock it live</li>
          <li class="flex gap-2.5"><span class="text-amber mt-0.5">&#10003;</span>Update shelter capacity, occupancy, and status</li>
          <li class="flex gap-2.5"><span class="text-amber mt-0.5">&#10003;</span>Watch the live SOS feed and dispatch responders</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ============================= STACK STRIP ============================= -->
<section id="stack" class="border-t border-white/10 bg-slate-950/80 backdrop-blur-md py-10">
  <div class="max-w-6xl mx-auto px-5 sm:px-8 flex flex-wrap items-center justify-center gap-8 sm:gap-12 opacity-80">
    <span class="font-mono text-xs text-slate-400">PHP 8.1+ / PDO</span>
    <span class="font-mono text-xs text-slate-400">MySQL 8.0</span>
    <span class="font-mono text-xs text-slate-400">Flask / Python 3.10+</span>
    <span class="font-mono text-xs text-slate-400">scikit-learn</span>
    <span class="font-mono text-xs text-slate-400">NetworkX (A*)</span>
    <span class="font-mono text-xs text-slate-400">python-constraint (CSP)</span>
    <span class="font-mono text-xs text-slate-400">Leaflet</span>
  </div>
</section>

<!-- ============================= FINAL CTA ============================= -->
<section class="border-t border-white/10 bg-slate-950/70 backdrop-blur-md">
  <div class="max-w-4xl mx-auto px-5 sm:px-8 py-20 text-center">
    <h2 class="font-display font-semibold text-2xl sm:text-3xl text-white mb-3">Ready to see it live?</h2>
    <p class="text-sm text-slate-300 mb-7">Choose your panel and sign in &mdash; the map is waiting.</p>
    <a href="select-panel.php"
       class="inline-block rounded-xl bg-ember text-white font-semibold text-sm px-8 py-3.5 hover:bg-ember-glow transition-all shadow-glow-ember hover:scale-105">
      Get Started &rarr;
    </a>
  </div>
</section>

<!-- ============================= FOOTER ============================= -->
<footer class="border-t border-white/10 bg-slate-950/90 backdrop-blur-md">
  <div class="max-w-6xl mx-auto px-5 sm:px-8 py-10 flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-2.5">
      <img src="assets/aquaevac-icon.png" alt="AquaEvac" class="w-7 h-7 object-contain">
      <span class="font-display font-extrabold text-sm tracking-tight">
        <span class="text-white">Aqua</span><span class="text-ember">Evac</span>
      </span>
      <span class="text-slate-400 text-xs">&middot; Disaster Evacuation &amp; Safe Route Planner</span>
    </div>
    <p class="text-[11px] text-slate-400">&copy; <?php echo date('Y'); ?> AquaEvac. Built for rapid, AI-assisted disaster response.</p>
  </div>
</footer>

<script src="assets/i18n.js"></script>
<script src="assets/flood-rescue-animation.js?v=<?php echo filemtime(__DIR__ . '/assets/flood-rescue-animation.js'); ?>"></script>
</body>
</html>
