<?php require_once __DIR__ . '/db.php'; $navUser = currentUser(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WhenPoll — Free Group Scheduling</title>
<meta name="description" content="Find the perfect meeting time. Create a poll, share a link, let your group vote. Free, private, EU-hosted.">
<meta property="og:title" content="WhenPoll — Free Group Scheduling">
<meta property="og:description" content="Create a scheduling poll in seconds. Free, private, EU-hosted, 100% renewable energy.">
<meta property="og:url" content="https://whenpoll.com">
<meta property="og:type" content="website">
<link rel="canonical" href="https://whenpoll.com">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="stylesheet" href="css/app.css">
<link rel="stylesheet" href="css/landing.css">
</head>
<body>

<nav>
  <a class="brand" href="/">
    <svg class="brand-icon" viewBox="0 0 32 32" fill="none" aria-hidden="true">
      <rect width="32" height="32" rx="8" fill="#15803d"/>
      <rect x="3" y="3" width="26" height="5" rx="2" fill="#4ade80"/>
      <rect x="3"  y="11" width="7" height="6" rx="1.5" fill="rgba(255,255,255,.2)"/>
      <rect x="13" y="11" width="7" height="6" rx="1.5" fill="white"/>
      <rect x="23" y="11" width="6" height="6" rx="1.5" fill="rgba(255,255,255,.2)"/>
      <rect x="3"  y="20" width="7" height="6" rx="1.5" fill="white"/>
      <rect x="13" y="20" width="7" height="6" rx="1.5" fill="rgba(255,255,255,.2)"/>
      <rect x="23" y="20" width="6" height="6" rx="1.5" fill="white"/>
    </svg><span class="brand-name">When<span>Poll</span></span>
  </a>
  <span class="nav-eco" title="Hosted in EU · 100% renewable energy">🌿 EU · 100% renewable energy</span>
  <span class="nav-spacer"></span>
  <?php if ($navUser): ?>
    <span class="nav-user"><?= h($navUser['name']) ?></span>
    <a href="calendar.php" class="nav-link">Calendars</a>
    <a href="profile.php" class="nav-link">Profile</a>
    <a href="index.php?action=logout" class="nav-link">Sign out</a>
  <?php else: ?>
    <a href="auth.php" class="nav-link">Sign in</a>
  <?php endif; ?>
</nav>

<section class="hero">
  <div class="hero-inner">
    <h1><span style="white-space:nowrap">Stop asking <em>When?</em></span><br>Just <em>Poll.</em></h1>
    <p class="hero-sub">Create a scheduling poll, share one link, your group votes. No account needed to vote. Free forever.</p>
    <a href="<?= $navUser ? 'create.php' : 'auth.php' ?>" class="btn-hero">Create a poll →</a>
  </div>
  <div class="hero-visual">
    <div class="mock-poll">
      <div class="mock-title">Team sync — April</div>
      <div class="mock-row">
        <div class="mock-date"><span class="mock-day">Mon</span><span class="mock-dm">28</span></div>
        <div class="mock-times">
          <div class="mock-slot best">10:00–11:00 <span class="mock-votes best-votes">✓ 5 ★</span></div>
          <div class="mock-slot maybe">14:00–15:00 <span class="mock-votes">~ 2</span></div>
        </div>
      </div>
      <div class="mock-row">
        <div class="mock-date"><span class="mock-day">Tue</span><span class="mock-dm">29</span></div>
        <div class="mock-times">
          <div class="mock-slot yes">09:00–10:00 <span class="mock-votes">✓ 4</span></div>
          <div class="mock-slot maybe">11:00–12:00 <span class="mock-votes">~ 3</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="social-proof">
  <p>Works with calendars your team already uses</p>
  <div class="cal-logos">
    <span class="cal-badge google">Google Calendar</span>
    <span class="cal-badge ms">Outlook / Microsoft 365</span>
    <span class="cal-badge caldav">Nextcloud · Apple · Fastmail</span>
  </div>
</section>

<footer class="site-footer">
  <div class="footer-inner">
    <a class="brand" href="/"><span class="brand-name">When<span>Poll</span></span></a>
    <div class="footer-links">
      <a href="auth.php">Sign in</a>
      <a href="mailto:info@whenpoll.com">Contact</a>
      <a href="privacy.php">Privacy</a>
      <a href="terms.php">Terms</a>
      <a href="https://github.com/JohannesEmm/whenpoll">GitHub</a>
    </div>
    <p class="footer-copy">© <?= date('Y') ?> WhenPoll · EU hosted · 100% renewable energy</p>
  </div>
</footer>

</body>
</html>
