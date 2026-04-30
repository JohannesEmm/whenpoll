# WhenPoll

Free, privacy-friendly group scheduling. Create a poll, share one link, your group votes — EU-hosted on 100% renewable energy.

Live at [whenpoll.com](https://whenpoll.com)

## Features

- **No account needed to vote** — participants just click a link
- **Magic-link login** — no passwords, sign in via email
- **Calendar integration** — connect Google, Outlook/Microsoft 365, or any CalDAV calendar (Nextcloud, Apple, Fastmail) to see busy slots while scheduling
- **Time-slot & full-day modes** — pick specific hours or whole dates
- **Anonymous voting** option
- **Drag-select** on desktop and mobile
- **Timezone-aware** — times shown in each viewer's local timezone

## Self-hosting

**Requirements:** PHP 8.1+, MySQL, Apache with `mod_rewrite`

1. Upload all files via FTP
2. Edit `config.php`:
   - Set `APP_URL`, `APP_SECRET` (`openssl rand -hex 32`)
   - Add DB credentials
   - Optionally add Google / Microsoft OAuth credentials for calendar sync

## Calendar OAuth setup

**Google Calendar**
1. [console.cloud.google.com](https://console.cloud.google.com) → APIs & Services → Credentials → OAuth 2.0 Client ID
2. Redirect URI: `https://yourdomain.com/calendar.php?provider=google&action=callback`
3. Enable the Google Calendar API and add your client ID/secret to `config.php`

**Microsoft / Outlook**
1. [portal.azure.com](https://portal.azure.com) → App registrations → New registration
2. Redirect URI: `https://yourdomain.com/calendar.php?provider=microsoft&action=callback`
3. Add delegated permission `Calendars.Read`, create a client secret, copy both into `config.php`

**CalDAV** — no setup needed. Users enter their server URL and credentials directly in the UI.

## License

MIT
