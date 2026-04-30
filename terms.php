<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Terms of Service · WhenPoll</title>
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
  <h1>Terms of Service</h1>
  <p class="updated">Last updated: <?= date('d F Y') ?></p>

  <p>By using WhenPoll ("the Service") at <strong>whenpoll.com</strong>, you agree to these terms. Please read them — they are short.</p>

  <h2>The Service</h2>
  <p>WhenPoll is a free scheduling poll tool. We provide it as-is, with no uptime guarantees. We may modify, suspend, or discontinue the Service at any time with reasonable notice.</p>

  <h2>Your account</h2>
  <ul>
    <li>You are responsible for keeping your login credentials secure.</li>
    <li>You must provide a valid email address to create an account.</li>
    <li>You may not use the Service for illegal purposes or to send spam.</li>
    <li>We reserve the right to suspend accounts that violate these terms.</li>
  </ul>

  <h2>Your content</h2>
  <p>You own the polls, votes, and data you create. By using the Service you grant us a limited licence to store and display that content in order to operate the Service. We do not sell or share your content.</p>

  <h2>Calendar access</h2>
  <p>If you connect Google Calendar or Microsoft Outlook, you grant WhenPoll read-only access to your calendar free/busy data solely to power the in-app scheduling features. You can revoke this access at any time. See our <a href="privacy.php">Privacy Policy</a> for details.</p>

  <h2>Acceptable use</h2>
  <p>You agree not to:</p>
  <ul>
    <li>Use the Service to harass, deceive, or harm others.</li>
    <li>Attempt to gain unauthorised access to other users' polls or accounts.</li>
    <li>Scrape, reverse-engineer, or overload the Service.</li>
  </ul>

  <h2>Disclaimer of warranties</h2>
  <p>The Service is provided <strong>"as is"</strong> without warranty of any kind. We do not guarantee that it will be error-free, uninterrupted, or suitable for any particular purpose.</p>

  <h2>Limitation of liability</h2>
  <p>To the maximum extent permitted by law, WhenPoll shall not be liable for any indirect, incidental, or consequential damages arising from your use of the Service.</p>

  <h2>Governing law</h2>
  <p>These terms are governed by the laws of the European Union and Italy. Any disputes shall be resolved in the courts of that jurisdiction.</p>

  <h2>Changes to these terms</h2>
  <p>We may update these terms occasionally. Continued use of the Service after changes constitutes acceptance. We will update the date at the top of this page.</p>

  <h2>Contact</h2>
  <p>Questions? Email <a href="mailto:info@whenpoll.com">info@whenpoll.com</a>.</p>
</div>

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
