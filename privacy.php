<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Privacy Policy · WhenPoll</title>
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="stylesheet" href="css/landing.css">
<style>
  .prose { max-width: 720px; margin: 0 auto; padding: 3rem 2rem 5rem; }
  .prose h1 { font-size: 2rem; font-weight: 800; margin-bottom: .4rem; letter-spacing: -.5px; }
  .prose .updated { color: var(--ink2); font-size: .85rem; margin-bottom: 2.5rem; }
  .prose h2 { font-size: 1.1rem; font-weight: 700; margin: 2rem 0 .5rem; color: var(--ink); }
  .prose p, .prose li { font-size: .95rem; color: var(--ink2); line-height: 1.75; margin-bottom: .75rem; }
  .prose ul { padding-left: 1.25rem; }
  .prose a { color: var(--accent); }
</style>
</head>
<body>

<div class="eco-strip">
  <span>Hosted in the EU</span>
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
    </svg>
    <span class="brand-name">When<span>Poll</span></span>
  </a>
</header>

<div class="prose">
  <h1>Privacy Policy</h1>
  <p class="updated">Last updated: <?= date('d F Y') ?></p>

  <p>WhenPoll ("we", "us") operates <strong>whenpoll.com</strong>. This policy explains what data we collect, why, and how we protect it. We keep it short and plain.</p>

  <h2>Who we are</h2>
  <p>WhenPoll is a free group scheduling tool. Our server is hosted in the <strong>European Union</strong> (Germany, all-inkl.com) and runs on <strong>100% renewable energy</strong>. We are subject to EU/GDPR data protection law.</p>

  <h2>What data we collect</h2>
  <ul>
    <li><strong>Account data</strong> — your email address and display name when you register. A password hash if you choose password sign-in.</li>
    <li><strong>Poll data</strong> — poll titles, descriptions, time slots, and votes (including participant names entered by voters). Voter names are entered voluntarily and are not linked to accounts.</li>
    <li><strong>Calendar tokens</strong> — if you connect Google Calendar or Microsoft Outlook, we store OAuth access and refresh tokens to read your free/busy times. We never read event content beyond start/end times and titles for the busy-slot overlay feature.</li>
    <li><strong>Session data</strong> — a session cookie to keep you logged in. No tracking or advertising cookies.</li>
    <li><strong>Server logs</strong> — standard web server access logs (IP address, timestamp, page visited). Retained for up to 14 days for security purposes.</li>
  </ul>

  <h2>What we do NOT collect</h2>
  <ul>
    <li>We do not use analytics, advertising, or third-party tracking scripts.</li>
    <li>We do not sell or share your data with any third party.</li>
    <li>We do not read, store, or process the content of your calendar events — only start/end times and event titles for the in-app overlay.</li>
  </ul>

  <h2>Google &amp; Microsoft calendar access</h2>
  <p>When you connect a calendar, WhenPoll requests <strong>read-only</strong> access (<code>calendar.readonly</code> for Google, <code>Calendars.Read</code> for Microsoft). We use this solely to display your busy slots while creating or viewing a poll. Tokens are stored securely in our database and used only on your request. You can disconnect your calendar at any time from the Calendars page.</p>
  <p>WhenPoll's use of Google user data complies with the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>

  <h2>Data retention</h2>
  <ul>
    <li>Polls and votes are kept until you delete them (feature coming) or request deletion.</li>
    <li>Account data is kept until you request account deletion.</li>
    <li>Disconnecting a calendar immediately deletes the stored tokens.</li>
  </ul>

  <h2>Your rights (GDPR)</h2>
  <p>You have the right to access, correct, or delete your personal data at any time. To exercise these rights, email us at <a href="mailto:info@whenpoll.com">info@whenpoll.com</a> and we will respond within 30 days.</p>

  <h2>Security</h2>
  <p>All data is transmitted over HTTPS. Passwords are hashed using bcrypt. OAuth tokens are stored in our database on an EU server with restricted access. We do not log or retain magic sign-in links after use.</p>

  <h2>Contact</h2>
  <p>Questions about this policy? Email <a href="mailto:info@whenpoll.com">info@whenpoll.com</a>.</p>
</div>

<footer class="site-footer">
  <div class="footer-inner">
    <a class="brand" href="/"><span class="brand-name">When<span>Poll</span></span></a>
    <div class="footer-links">
      <a href="auth.php">Sign in</a>
      <a href="mailto:info@whenpoll.com">Contact</a>
      <a href="https://github.com/JohannesEmm/whenpoll">GitHub</a>
    </div>
    <p class="footer-copy">© <?= date('Y') ?> WhenPoll · EU hosted · 100% renewable energy</p>
  </div>
</footer>

</body>
</html>
