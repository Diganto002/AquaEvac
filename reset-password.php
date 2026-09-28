<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$token = (string)($_GET['token'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
<title>Reset password &mdash; AquaEvac</title>
<link rel="icon" type="image/png" href="assets/aquaevac-favicon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  :root {
    --ink:         #F8FAFC;
    --muted:       #94A3B8;
    --accent:      #FB923C;
    --accent-dark: #FDBA74;
    --accent-tint: rgba(251,146,60,0.2);
    --danger:      #EF4444;
  }
  * { box-sizing: border-box; }
  html, body { height: 100%; margin: 0; }
  body {
    font-family: 'Inter', sans-serif; background: transparent; color: var(--ink);
    min-height: 100vh; display: flex; flex-direction: column; position: relative; overflow-x: hidden;
  }
  a { color: inherit; }
  header.top {
    display: flex; align-items: center; justify-content: space-between;
    max-width: 1100px; width: 100%; margin: 0 auto; padding: 20px 24px; flex-shrink: 0;
    position: relative; z-index: 10;
  }
  .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
  .brand-mark { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; }
  .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
  .brand-name { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 17px; }
  .back-link { font-size: 12px; color: var(--muted); text-decoration: none; }
  .back-link:hover { color: var(--ink); }

  main.stage { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px 16px 48px; position: relative; z-index: 10; }

  .neu-card-wrap { position: relative; width: 100%; max-width: 400px; margin-top: 46px; }
  .neu-icon-circle {
    position: absolute; top: -46px; left: 50%; transform: translateX(-50%);
    width: 92px; height: 92px; border-radius: 50%;
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    display: flex; align-items: center; justify-content: center; z-index: 2; color: var(--accent);
  }
  .neu-card {
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 32px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
    padding: 66px 32px 32px;
  }
  h1.neu-title { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 24px; text-align: center; margin: 0; color: #f8fafc; }
  p.neu-subtitle { text-align: center; color: var(--muted); font-size: 13px; margin: 6px 0 26px; line-height: 1.6; }

  .neu-field { margin-bottom: 16px; }
  label.neu-label { font-size: 12px; font-weight: 700; color: var(--accent-dark); display: block; margin-bottom: 7px; }
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
  .neu-eye-toggle { background: none; border: none; cursor: pointer; color: var(--muted); display: flex; padding: 0; flex-shrink: 0; }
  .neu-eye-toggle:hover { color: var(--ink); }

  .neu-error {
    font-size: 12px; color: var(--danger); background: rgba(239,68,68,0.12);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 14px; padding: 10px 14px; margin-bottom: 16px; display: none;
  }
  .neu-error.show { display: block; }

  button.neu-submit, a.neu-submit {
    width: 100%; border: none; border-radius: 999px; padding: 15px; display: block; text-align: center;
    font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 14px; letter-spacing: 0.03em;
    color: #ffffff; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    cursor: pointer; text-decoration: none;
    box-shadow: 0 10px 25px -5px rgba(249, 115, 22, 0.4);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  button.neu-submit:hover { transform: translateY(-1px); box-shadow: 0 14px 28px -5px rgba(249, 115, 22, 0.5); }
  button.neu-submit:active { transform: translateY(1px); }
  button.neu-submit:disabled { opacity: 0.6; cursor: default; }

  .neu-success-icon {
    width: 44px; height: 44px; border-radius: 50%; background: rgba(22,163,74,0.15); color: #22c55e;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;
  }
  .neu-back { display: block; text-align: center; font-size: 11px; color: var(--muted); text-decoration: none; margin-top: 22px; }
  .neu-back:hover { color: var(--ink); }
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
  <a href="select-panel.php" class="back-link">&larr; Back to login</a>
</header>

<main class="stage">
  <div class="neu-card-wrap">
    <div class="neu-icon-circle">
      <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>
      </svg>
    </div>

    <div class="neu-card">
      <?php if ($token === ''): ?>
        <h1 class="neu-title">Invalid link</h1>
        <p class="neu-subtitle">This reset link is missing its token. Please request a new one from the login page.</p>
        <a href="select-panel.php" class="neu-submit">BACK TO LOGIN</a>
      <?php else: ?>

      <div id="reset-step-form">
        <h1 class="neu-title">Set a new password</h1>
        <p class="neu-subtitle">Choose a new password for your account. It must be at least 8 characters.</p>

        <form id="reset-form" autocomplete="off">
          <div class="neu-field">
            <label class="neu-label">New Password</label>
            <div class="neu-input-wrap">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
              <input id="reset-password" type="password" required minlength="8" autocomplete="new-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
              <button type="button" class="neu-eye-toggle" data-eye-for="reset-password" aria-label="Show password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <div class="neu-field">
            <label class="neu-label">Confirm New Password</label>
            <div class="neu-input-wrap">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
              <input id="reset-password-confirm" type="password" required minlength="8" autocomplete="new-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
              <button type="button" class="neu-eye-toggle" data-eye-for="reset-password-confirm" aria-label="Show password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <div id="reset-error" class="neu-error"></div>
          <button type="submit" id="reset-submit" class="neu-submit">RESET PASSWORD</button>
        </form>
      </div>

      <div id="reset-step-done" class="hidden" style="display:none;">
        <div class="neu-success-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="neu-title">Password updated</h1>
        <p class="neu-subtitle">You can now log in with your new password.</p>
        <a href="select-panel.php" class="neu-submit">GO TO LOGIN</a>
      </div>

      <?php endif; ?>
    </div>
  </div>
</main>

<?php if ($token !== ''): ?>
<script>
(() => {
  "use strict";
  const TOKEN = <?php echo json_encode($token); ?>;

  document.querySelectorAll(".neu-eye-toggle").forEach((btn) => {
    btn.addEventListener("click", () => {
      const input = document.getElementById(btn.dataset.eyeFor);
      if (!input) return;
      input.type = input.type === "password" ? "text" : "password";
    });
  });

  const form = document.getElementById("reset-form");
  const passwordInput = document.getElementById("reset-password");
  const confirmInput = document.getElementById("reset-password-confirm");
  const errorBox = document.getElementById("reset-error");
  const submitBtn = document.getElementById("reset-submit");
  const stepForm = document.getElementById("reset-step-form");
  const stepDone = document.getElementById("reset-step-done");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    errorBox.classList.remove("show");

    if (passwordInput.value !== confirmInput.value) {
      errorBox.textContent = "Passwords do not match.";
      errorBox.classList.add("show");
      return;
    }

    submitBtn.disabled = true;
    try {
      const response = await fetch("auth.php?action=reset_password", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ token: TOKEN, password: passwordInput.value }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error((data && data.error) || `Request failed (${response.status}).`);
      stepForm.style.display = "none";
      stepDone.style.display = "block";
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.classList.add("show");
      submitBtn.disabled = false;
    }
  });
})();
</script>
<?php endif; ?>

<script src="assets/i18n.js"></script>
<script src="assets/flood-rescue-animation.js?v=<?php echo filemtime(__DIR__ . '/assets/flood-rescue-animation.js'); ?>"></script>
</body>
</html>
