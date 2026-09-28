<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$bootstrap_user = current_user();
if ($bootstrap_user !== null) {
    header('Location: dashboard.php');
    exit;
}

$rawPanel = $_GET['panel'] ?? 'user';
$panel = in_array($rawPanel, ['admin', 'responder'], true) ? $rawPanel : 'user';
$isAdminPanel = $panel === 'admin';
$isResponderPanel = $panel === 'responder';
$accent = $isAdminPanel ? 'amber' : ($isResponderPanel ? 'sky' : 'ember');
$panelLabel = $isAdminPanel ? 'Admin panel' : ($isResponderPanel ? 'Responder panel' : 'User panel');
// Admin accounts are never self-registerable (see auth.php); a responder
// CAN self-register, but doing so through this panel should always create
// a 'responder' account, not the default 'citizen'.
$canSelfRegister = !$isAdminPanel;
$fixedRegisterRole = $isResponderPanel ? 'responder' : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
<title><?php echo $isAdminPanel ? 'Admin login' : 'Log in'; ?> &mdash; AquaEvac</title>
<link rel="icon" type="image/png" href="assets/aquaevac-favicon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  :root {
    --neu-bg:      rgba(15, 23, 42, 0.85);
    --neu-dark:    rgba(0, 0, 0, 0.5);
    --neu-light:   rgba(255, 255, 255, 0.08);
    --ink:         #F8FAFC;
    --muted:       #94A3B8;
    --accent:      <?php echo $isAdminPanel ? '#F59E0B' : ($isResponderPanel ? '#38BDF8' : '#FB923C'); ?>;
    --accent-dark: <?php echo $isAdminPanel ? '#FBBF24' : ($isResponderPanel ? '#7DD3FC' : '#FDBA74'); ?>;
    --accent-tint: <?php echo $isAdminPanel ? 'rgba(245,158,11,0.2)' : ($isResponderPanel ? 'rgba(56,189,248,0.2)' : 'rgba(251,146,60,0.2)'); ?>;
    --danger:      #EF4444;
  }
  * { box-sizing: border-box; }
  html, body { height: 100%; margin: 0; }
  body {
    font-family: 'Inter', sans-serif;
    background: #07111e;
    color: var(--ink);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow-x: hidden;
  }
  a { color: inherit; }

  header.top {
    display: flex; align-items: center; justify-content: space-between;
    max-width: 1100px; width: 100%; margin: 0 auto;
    padding: 20px 24px; flex-shrink: 0;
  }
  .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
  .brand-mark { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; }
  .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
  .brand-name { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 17px; }
  .back-link { font-size: 12px; color: var(--muted); text-decoration: none; }
  .back-link:hover { color: var(--ink); }

  main.stage {
    flex: 1; display: flex; align-items: center; justify-content: center;
    padding: 20px 16px 48px;
  }

  .neu-card-wrap { position: relative; width: 100%; max-width: 400px; margin-top: 46px; }
  .neu-icon-circle {
    position: absolute; top: -46px; left: 50%; transform: translateX(-50%);
    width: 92px; height: 92px; border-radius: 50%;
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    display: flex; align-items: center; justify-content: center; z-index: 2;
  }
  .neu-icon-circle svg { color: var(--accent); }

  .neu-card {
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 32px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
    padding: 66px 32px 32px;
  }

  .neu-panel-badge {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--accent-dark); background: var(--accent-tint);
    border-radius: 999px; padding: 5px 12px; margin: 0 auto 14px; 
  }
  .neu-badge-row { text-align: center; }

  h1.neu-title { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 26px; text-align: center; margin: 0; color: #f8fafc; }
  p.neu-subtitle { text-align: center; color: var(--muted); font-size: 13px; margin: 6px 0 26px; }

  .neu-field { margin-bottom: 16px; }
  .neu-field.hidden { display: none; }
  .neu-label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 7px; }
  label.neu-label { font-size: 12px; font-weight: 700; color: var(--accent-dark); }
  .neu-forgot-link {
    font-size: 11px; font-weight: 600; color: var(--accent-dark); background: none; border: none;
    cursor: pointer; text-decoration: none; padding: 0;
  }
  .neu-forgot-link:hover { text-decoration: underline; }

  .neu-input-wrap {
    display: flex; align-items: center; gap: 10px;
    background: rgba(2, 6, 23, 0.65);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 999px; padding: 13px 18px;
    box-shadow: inset 2px 2px 6px rgba(0, 0, 0, 0.5);
  }
  .neu-input-wrap svg { color: var(--muted); flex-shrink: 0; }
  .neu-input-wrap input {
    background: transparent; border: none; outline: none; flex: 1;
    font-size: 14px; color: var(--ink); font-family: 'Inter', sans-serif; min-width: 0;
  }
  .neu-input-wrap input::placeholder { color: #64748b; }
  .neu-eye-toggle { background: none; border: none; cursor: pointer; color: var(--muted); display: flex; padding: 0; flex-shrink: 0; }
  .neu-eye-toggle:hover { color: var(--ink); }

  .neu-error {
    font-size: 12px; color: var(--danger); background: rgba(239,68,68,0.12);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 14px; padding: 10px 14px; margin-bottom: 16px; display: none;
  }
  .neu-error.show { display: block; }

  .neu-check-row { display: flex; align-items: center; justify-content: space-between; margin: 4px 0 22px; }
  .neu-check-row.single { justify-content: flex-start; }
  .neu-checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--muted); cursor: pointer; user-select: none; }
  .neu-checkbox-label input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }
  .neu-checkbox-label .tc-link { color: var(--accent-dark); font-weight: 600; }

  button.neu-submit {
    width: 100%; border: none; border-radius: 999px; padding: 15px;
    font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 14px; letter-spacing: 0.03em;
    color: #ffffff; background: linear-gradient(135deg, #f97316, #ea580c); cursor: pointer;
    box-shadow: 0 8px 20px rgba(234, 88, 12, 0.45);
    transition: transform 0.15s ease, filter 0.15s ease;
  }
  button.neu-submit:hover { filter: brightness(1.1); transform: translateY(-1px); }
  button.neu-submit:active { transform: translateY(1px); }
  button.neu-submit:disabled { opacity: 0.6; cursor: default; }

  .neu-switch-row {
    display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 24px;
  }
  .neu-switch-row span { font-size: 12px; color: var(--muted); }
  .neu-mini-btn {
    width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer;
    background: var(--neu-bg); color: var(--accent-dark);
    box-shadow: 6px 6px 12px var(--neu-dark), -6px -6px 12px var(--neu-light);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    transition: box-shadow 0.15s ease, transform 0.15s ease;
  }
  .neu-mini-btn:active { box-shadow: inset 4px 4px 8px var(--neu-dark), inset -4px -4px 8px var(--neu-light); transform: translateY(1px); }

  .neu-admin-note { text-align: center; font-size: 11px; color: var(--muted); line-height: 1.6; margin-top: 22px; }

  .neu-back { display: block; text-align: center; font-size: 11px; color: var(--muted); text-decoration: none; margin-top: 22px; }
  .neu-back:hover { color: var(--ink); }
  .neu-back b { color: var(--ink); }

  /* ---------------- Forgot-password modal ---------------- */
  #forgot-overlay {
    display: none; position: fixed; inset: 0; z-index: 50;
    background: rgba(22,35,63,0.35); backdrop-filter: blur(3px);
    align-items: center; justify-content: center; padding: 20px;
  }
  #forgot-overlay.show { display: flex; }
  .neu-modal-card {
    width: 100%; max-width: 380px; position: relative;
    background: var(--neu-bg); border-radius: 28px;
    box-shadow: 10px 10px 24px var(--neu-dark), -10px -10px 24px var(--neu-light);
    padding: 30px;
  }
  .neu-modal-close {
    position: absolute; top: 16px; right: 16px; width: 30px; height: 30px; border-radius: 50%;
    border: none; background: var(--neu-bg); color: var(--muted); cursor: pointer;
    box-shadow: 4px 4px 8px var(--neu-dark), -4px -4px 8px var(--neu-light);
    display: flex; align-items: center; justify-content: center; font-size: 15px; line-height: 1;
  }
  .neu-demo-box {
    display: none; border-radius: 16px; background: rgba(217,138,61,0.10);
    padding: 14px; font-size: 12px; line-height: 1.6; margin-bottom: 6px;
  }
  .neu-demo-box.show { display: block; }
  .neu-demo-box p.demo-title { color: var(--accent-dark); font-weight: 700; margin: 0 0 6px; }
  .neu-demo-box a { display: block; word-break: break-all; color: var(--accent-dark); font-family: monospace; font-size: 11px; text-decoration: underline; }
  .neu-success-icon {
    width: 44px; height: 44px; border-radius: 50%; background: rgba(22,163,74,0.12); color: #16A34A;
    display: flex; align-items: center; justify-content: center; margin-bottom: 14px;
  }
</style>
</head>
<body>

<header class="top">
  <a href="index.php" class="brand">
    <span class="brand-mark">
      <img src="assets/aquaevac-icon.png" alt="AquaEvac">
    </span>
    <span class="brand-name">AquaEvac</span>
  </a>
  <a href="select-panel.php" class="back-link">&larr; Change panel</a>
</header>

<main class="stage">
  <div class="neu-card-wrap">
    <div class="neu-icon-circle">
      <svg id="auth-icon" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>
      </svg>
    </div>

    <div class="neu-card">
      <div class="neu-badge-row">
        <span class="neu-panel-badge"><?php echo $panelLabel; ?></span>
      </div>
      <h1 id="auth-title" class="neu-title">Welcome</h1>
      <p id="auth-subtitle" class="neu-subtitle">
        <?php
          if ($isAdminPanel) {
              echo 'Sign in with an administrator account.';
          } elseif ($isResponderPanel) {
              echo 'Sign in to continue your journey.';
          } else {
              echo 'Login to continue your journey.';
          }
        ?>
      </p>

      <form id="auth-form" autocomplete="off">
        <div id="auth-name-field" class="neu-field hidden">
          <div class="neu-label-row"><label class="neu-label">Full Name</label></div>
          <div class="neu-input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-3.9 3.6-7 8-7s8 3.1 8 7"/></svg>
            <input id="auth-name" type="text" placeholder="Enter your name">
          </div>
        </div>

        <div class="neu-field">
          <div class="neu-label-row"><label class="neu-label">Email Address</label></div>
          <div class="neu-input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3.5 6 8.5 7 8.5-7"/></svg>
            <input id="auth-email" type="email" required autocomplete="email" placeholder="you@example.com">
          </div>
        </div>

        <div class="neu-field">
          <div class="neu-label-row">
            <label class="neu-label">Password</label>
            <button type="button" id="auth-forgot-link" class="neu-forgot-link">Forgot Password?</button>
          </div>
          <div class="neu-input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            <input id="auth-password" type="password" required minlength="8" autocomplete="current-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
            <button type="button" class="neu-eye-toggle" data-eye-for="auth-password" aria-label="Show password">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div id="auth-confirm-field" class="neu-field hidden">
          <div class="neu-label-row"><label class="neu-label">Confirm Password</label></div>
          <div class="neu-input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            <input id="auth-password-confirm" type="password" minlength="8" autocomplete="new-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
            <button type="button" class="neu-eye-toggle" data-eye-for="auth-password-confirm" aria-label="Show password">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div id="auth-error" class="neu-error"></div>

        <div id="auth-remember-row" class="neu-check-row single">
          <label class="neu-checkbox-label">
            <input id="auth-remember" type="checkbox" checked>
            Remember me
          </label>
        </div>

        <div id="auth-terms-row" class="neu-check-row single hidden">
          <label class="neu-checkbox-label">
            <input id="auth-terms" type="checkbox">
            I agree to the <span class="tc-link">Terms &amp; Conditions</span>
          </label>
        </div>

        <button type="submit" id="auth-submit" class="neu-submit">LOGIN</button>
      </form>

      <?php if ($canSelfRegister): ?>
      <div class="neu-switch-row">
        <span id="auth-toggle-prompt">Don't have an account?</span>
        <button type="button" id="auth-toggle-mode" class="neu-mini-btn" aria-label="Switch to create account">
          <svg id="auth-toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
        </button>
      </div>
      <?php else: ?>
      <p class="neu-admin-note">
        Admin accounts are provisioned by an existing administrator, not
        through self-registration.
      </p>
      <?php endif; ?>
    </div>

    <a href="select-panel.php" class="neu-back">Wrong panel? <b>Go back</b></a>
  </div>
</main>

<!-- ============================= FORGOT PASSWORD MODAL ============================= -->
<div id="forgot-overlay">
  <div class="neu-modal-card">
    <button type="button" id="forgot-close" class="neu-modal-close" aria-label="Close">&times;</button>

    <div id="forgot-step-request">
      <h2 class="neu-title" style="font-size:20px; text-align:left;">Reset your password</h2>
      <p class="neu-subtitle" style="text-align:left; margin-bottom:20px;">Enter your account email and we'll generate a reset link.</p>
      <form id="forgot-form" autocomplete="off">
        <div class="neu-field">
          <div class="neu-label-row"><label class="neu-label">Email Address</label></div>
          <div class="neu-input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3.5 6 8.5 7 8.5-7"/></svg>
            <input id="forgot-email" type="email" required autocomplete="email" placeholder="you@example.com">
          </div>
        </div>
        <div id="forgot-error" class="neu-error"></div>
        <button type="submit" id="forgot-submit" class="neu-submit">SEND RESET LINK</button>
      </form>
    </div>

    <div id="forgot-step-done" class="hidden">
      <div class="neu-success-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
      </div>
      <h2 class="neu-title" style="font-size:20px; text-align:left;">Check your link</h2>
      <p id="forgot-done-message" class="neu-subtitle" style="text-align:left; margin-bottom:16px;">If an account exists for that email, a reset link has been generated.</p>
      <div id="forgot-demo-box" class="neu-demo-box">
        <p class="demo-title">Demo mode &mdash; no email server configured</p>
        <p style="margin:0 0 8px; color:var(--muted);">This link normally goes out by email. For this demo build it's shown here instead:</p>
        <a id="forgot-demo-link" href="#"></a>
      </div>
      <button type="button" id="forgot-done-close" class="neu-submit" style="margin-top:14px;">CLOSE</button>
    </div>
  </div>
</div>

<script>
(() => {
  "use strict";
  const PANEL = <?php echo json_encode($panel); ?>; // "user" | "admin" | "responder"
  const FIXED_REGISTER_ROLE = <?php echo json_encode($fixedRegisterRole); ?>; // null | "responder"
  let mode = "login"; // 'login' | 'register'

  const ICON_LOCK = '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>';
  const ICON_PERSON = '<circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-3.9 3.6-7 8-7s8 3.1 8 7"/>';
  const ICON_PLUS = '<path stroke-linecap="round" d="M12 5v14M5 12h14"/>';
  const ICON_ARROW = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>';

  const authIcon = document.getElementById("auth-icon");
  const nameField = document.getElementById("auth-name-field");
  const nameInput = document.getElementById("auth-name");
  const confirmField = document.getElementById("auth-confirm-field");
  const confirmInput = document.getElementById("auth-password-confirm");
  const rememberRow = document.getElementById("auth-remember-row");
  const termsRow = document.getElementById("auth-terms-row");
  const termsCheckbox = document.getElementById("auth-terms");
  const errorBox = document.getElementById("auth-error");
  const title = document.getElementById("auth-title");
  const subtitle = document.getElementById("auth-subtitle");
  const submitBtn = document.getElementById("auth-submit");
  const toggleBtn = document.getElementById("auth-toggle-mode");
  const toggleIcon = document.getElementById("auth-toggle-icon");
  const togglePrompt = document.getElementById("auth-toggle-prompt");
  const forgotLink = document.getElementById("auth-forgot-link");

  async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", ...(options.headers || {}) },
      ...options,
    });
    let data = null;
    try { data = await response.json(); } catch (_) { data = null; }
    if (!response.ok) throw new Error((data && data.error) || `Request failed (${response.status}).`);
    return data;
  }

  function setMode(next) {
    mode = next;
    const isRegister = mode === "register";
    authIcon.innerHTML = isRegister ? ICON_PERSON : ICON_LOCK;
    title.textContent = isRegister ? "Create Account" : "Welcome";
    subtitle.textContent = isRegister ? "Sign up to get started" : "Login to continue your journey.";
    submitBtn.textContent = isRegister ? "SIGN UP" : "LOGIN";
    nameField.classList.toggle("hidden", !isRegister);
    nameInput.required = isRegister;
    confirmField.classList.toggle("hidden", !isRegister);
    confirmInput.required = isRegister;
    rememberRow.classList.toggle("hidden", isRegister);
    termsRow.classList.toggle("hidden", !isRegister);
    termsCheckbox.required = isRegister;
    if (togglePrompt) togglePrompt.textContent = isRegister ? "Already have an account?" : "Don't have an account?";
    if (toggleIcon) toggleIcon.innerHTML = isRegister ? ICON_ARROW : ICON_PLUS;
    if (forgotLink) forgotLink.classList.toggle("hidden", isRegister);
    errorBox.classList.remove("show");
  }

  if (toggleBtn) {
    toggleBtn.addEventListener("click", () => setMode(mode === "login" ? "register" : "login"));
  }

  // Show/hide password toggles
  document.querySelectorAll(".neu-eye-toggle").forEach((btn) => {
    btn.addEventListener("click", () => {
      const input = document.getElementById(btn.dataset.eyeFor);
      if (!input) return;
      input.type = input.type === "password" ? "text" : "password";
    });
  });

  // ---------------------------------------------------------------------
  // FORGOT PASSWORD MODAL
  // ---------------------------------------------------------------------
  const forgotOverlay = document.getElementById("forgot-overlay");
  const forgotClose = document.getElementById("forgot-close");
  const forgotDoneClose = document.getElementById("forgot-done-close");
  const forgotForm = document.getElementById("forgot-form");
  const forgotEmail = document.getElementById("forgot-email");
  const forgotError = document.getElementById("forgot-error");
  const forgotSubmit = document.getElementById("forgot-submit");
  const forgotStepRequest = document.getElementById("forgot-step-request");
  const forgotStepDone = document.getElementById("forgot-step-done");
  const forgotDoneMessage = document.getElementById("forgot-done-message");
  const forgotDemoBox = document.getElementById("forgot-demo-box");
  const forgotDemoLink = document.getElementById("forgot-demo-link");

  function openForgotModal() {
    forgotStepRequest.classList.remove("hidden");
    forgotStepDone.classList.add("hidden");
    forgotError.classList.remove("show");
    forgotDemoBox.classList.remove("show");
    forgotEmail.value = document.getElementById("auth-email").value.trim();
    forgotOverlay.classList.add("show");
    forgotEmail.focus();
  }
  function closeForgotModal() { forgotOverlay.classList.remove("show"); }

  if (forgotLink) forgotLink.addEventListener("click", openForgotModal);
  if (forgotClose) forgotClose.addEventListener("click", closeForgotModal);
  if (forgotDoneClose) forgotDoneClose.addEventListener("click", closeForgotModal);
  forgotOverlay.addEventListener("click", (e) => { if (e.target === forgotOverlay) closeForgotModal(); });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && forgotOverlay.classList.contains("show")) closeForgotModal();
  });

  forgotForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    forgotError.classList.remove("show");
    forgotSubmit.disabled = true;
    try {
      const result = await apiFetch("auth.php?action=forgot_password", {
        method: "POST",
        body: JSON.stringify({ email: forgotEmail.value.trim() }),
      });
      forgotDoneMessage.textContent = result.message || "If an account exists for that email, a reset link has been generated.";
      if (result.demo_reset_url) {
        forgotDemoBox.classList.add("show");
        forgotDemoLink.href = result.demo_reset_url;
        forgotDemoLink.textContent = result.demo_reset_url;
      } else {
        forgotDemoBox.classList.remove("show");
      }
      forgotStepRequest.classList.add("hidden");
      forgotStepDone.classList.remove("hidden");
    } catch (err) {
      forgotError.textContent = err.message;
      forgotError.classList.add("show");
    } finally {
      forgotSubmit.disabled = false;
    }
  });

  // ---------------------------------------------------------------------
  // LOGIN / REGISTER SUBMIT
  // ---------------------------------------------------------------------
  document.getElementById("auth-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    errorBox.classList.remove("show");

    const email = document.getElementById("auth-email").value.trim();
    const password = document.getElementById("auth-password").value;
    const name = nameInput.value.trim();

    if (mode === "register" && password !== confirmInput.value) {
      errorBox.textContent = "Passwords do not match.";
      errorBox.classList.add("show");
      return;
    }
    if (mode === "register" && !termsCheckbox.checked) {
      errorBox.textContent = "Please agree to the Terms & Conditions to continue.";
      errorBox.classList.add("show");
      return;
    }

    submitBtn.disabled = true;
    try {
      if (mode === "register") {
        const registerBody = { name, email, password };
        if (FIXED_REGISTER_ROLE) registerBody.role = FIXED_REGISTER_ROLE;
        await apiFetch("auth.php?action=register", {
          method: "POST",
          body: JSON.stringify(registerBody),
        });
      }

      const loginResult = await apiFetch("auth.php?action=login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });

      const role = loginResult.user.role;

      // Enforce that the panel picked on the previous screen actually
      // matches the account's role, so nobody wanders into a dashboard
      // meant for a different role.
      const PANEL_LABELS = { user: "User panel", admin: "Admin panel", responder: "Responder panel" };
      const roleMatchesPanel =
        (PANEL === "admin" && role === "admin") ||
        (PANEL === "responder" && role === "responder") ||
        (PANEL === "user" && role !== "admin" && role !== "responder");

      if (!roleMatchesPanel) {
        await apiFetch("auth.php?action=logout", { method: "POST" });
        const correctPanel = role === "admin" ? "admin" : role === "responder" ? "responder" : "user";
        errorBox.textContent = `This account is a '${role}' account. Please continue through the ${PANEL_LABELS[correctPanel]} instead.`;
        errorBox.classList.add("show");
        submitBtn.disabled = false;
        return;
      }

      window.location.href = "dashboard.php";
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.classList.add("show");
      submitBtn.disabled = false;
    }
  });
})();
</script>

<script src="assets/i18n.js"></script>
<script src="assets/flood-rescue-animation.js?v=<?php echo filemtime(__DIR__ . '/assets/flood-rescue-animation.js'); ?>"></script>
</body>
</html>
