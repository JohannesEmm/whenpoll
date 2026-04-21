<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WhenPoll — Free Group Scheduling</title>
<meta name="description" content="Find the perfect meeting time. Create a poll, share a link, let your group vote. Free, private, EU-hosted.">
<meta property="og:title" content="WhenPoll — Free Group Scheduling">
<meta property="og:description" content="Create a scheduling poll in seconds. Free, private, EU-hosted, 100% renewable hosting.">
<meta property="og:url" content="https://whenpoll.com">
<meta property="og:type" content="website">
<link rel="canonical" href="https://whenpoll.com">
<link rel="stylesheet" href="css/landing.css">
</head>
<body>

<div class="eco-strip">
  <span>Hosted in EU</span>
  <span>100% renewable energy</span>
  <span>No tracking</span>
  <span>Open source</span>
</div>

<header class="site-nav">
  <a class="brand" href="/">
    <svg viewBox="0 0 32 32" fill="none" style="width:26px;height:26px;flex-shrink:0">
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
  <a href="auth.php" class="nav-cta">Sign in</a>
</header>

<section class="hero">
  <div class="hero-inner">
    <h1>Find the perfect time<br><em>together.</em></h1>
    <p class="hero-sub">Create a scheduling poll, share one link, your group votes. No account needed to vote. Free forever.</p>
    <a href="auth.php" class="btn-hero">Create a poll →</a>
    <p class="hero-note">No tracking · Works with Google, Outlook &amp; CalDAV</p>
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
      <a href="https://github.com/johannesEmm/whenpoll">GitHub</a>
    </div>
    <p class="footer-copy">© <?= date('Y') ?> WhenPoll · EU hosted · 100% renewable</p>
  </div>
</footer>

</body>
</html>
