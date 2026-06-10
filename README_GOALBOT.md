# GoalBot - World Cup 2026 WhatsApp Bot

**GoalBot** delivers real-time World Cup match notifications via WhatsApp, powered by AI-generated messages.

## Features

### MVP (Live Now)
- **Live Match Events**: Goals, kickoff, halftime, fulltime, red cards, penalties
- **AI-Generated Messages**: Personalized, emoji-enhanced notifications
- **Match Reminders**: 2-hour advance notice for subscribed teams
- **Zero Interactivity**: Pure push notifications (interactive features coming in Phase 2)

### How It Works

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  API-Football   │────▶│   Match Event   │────▶│  AI Message     │
│  (Live Data)    │     │   Detector      │     │  Generator      │
└─────────────────┘     └─────────────────┘     └─────────────────┘
                                                        │
                                                        ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  Subscriber     │◀────│  WhatsApp API   │◀────│  Notification   │
│  (Phone)        │     │  (Message Send) │     │  Queue          │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

## Setup

### 1. API Keys Required

| Service | Purpose | Get From |
|---------|---------|----------|
| API-Football | Live match data | https://www.api-football.com/ |
| WhatsApp Business API | Send messages | https://developers.facebook.com/ |
| OpenAI | AI message generation | https://platform.openai.com/ |

### 2. Configure Environment

```bash
cp .env .env.local
# Edit .env.local with your API keys
```

Required variables:
```env
FOOTBALL_API_KEY=your-key
WHATSAPP_ACCESS_TOKEN=your-token
WHATSAPP_PHONE_NUMBER_ID=your-id
WHATSAPP_VERIFY_TOKEN=your-verify-token
OPENAI_API_KEY=your-openai-key
```

### 3. Setup WhatsApp Webhook

1. In Meta Developer Console, set webhook URL to:
   ```
   https://your-domain.com/api/webhook/whatsapp
   ```

2. Verify token must match `WHATSAPP_VERIFY_TOKEN`

3. Subscribe to messages webhook

### 4. Run the Bot

```bash
# Start the scheduler (polls every minute)
php artisan schedule:work

# Or run manually
php artisan matches:poll      # Poll live matches
php artisan reminders:send    # Send upcoming match reminders
```

### 5. Production Deployment

```bash
# Add to crontab for production
* * * * * cd /var/www/goalbot && php artisan schedule:run >> /dev/null 2>&1

# Or use systemd/Supervisor for queue workers
php artisan queue:work --queue=default
```

## Architecture

### Core Services

| Service | File | Purpose |
|---------|------|---------|
| FootballDataService | `app/Services/Football/FootballDataService.php` | Fetches live match data |
| MatchEventDetector | `app/Services/MatchEventDetector.php` | Detects score changes, cards, etc |
| AIMessageGenerator | `app/Services/AIMessageGenerator.php` | Generates human-like notifications |
| MessageSender | `app/Services/WhatsApp/MessageSender.php` | Sends WhatsApp messages |

### Commands

| Command | Schedule | Purpose |
|---------|----------|---------|
| `matches:poll` | Every minute | Detect events and notify subscribers |
| `reminders:send` | Every 5 minutes | Send 2-hour match reminders |

### Database Tables

- `subscribers` - WhatsApp subscribers and their preferences
- `match_states` - Last known state of each match (for change detection)
- `notifications` - History of sent notifications

## Notification Types

| Event | Example Message |
|-------|-----------------|
| Kickoff | "🔴 LIVE! Argentina vs France has kicked off at Estadio Azteca!" |
| Goal | "⚽ GOAL! Argentina 1-0! Messi scores at 23' 🐐" |
| Half-time | "⏸️ HALF-TIME: Argentina lead 1-0. Possession: 55-45" |
| Full-time | "🏁 FULL TIME! Argentina win 2-1 and top Group C!" |
| Red Card | "🟥 RED CARD! France down to 10 men. Game changer!" |
| Penalty | "🎯 PENALTY! VAR confirms handball. About to take it..." |

## Testing

```bash
# Test match polling (dry run)
php artisan matches:poll --dry-run

# Test reminder sending
php artisan reminders:send

# View recent notifications
php artisan tinker
>>> Notification::latest()->take(10)->get();
```

## Roadmap

### Phase 2 (Week 1)
- [ ] Interactive commands (/favorite, /live, /schedule)
- [ ] Natural language queries
- [ ] Match predictions game

### Phase 3 (Week 2)
- [ ] Shareable match graphics
- [ ] Voice notes summary
- [ ] Multi-channel (Instagram DM, Telegram)

### Phase 4 (Post-Tournament)
- [ ] General sports bot expansion
- [ ] Betting integration (affiliate)
- [ ] Merchandise recommendations

## License

MIT - Built for the love of football ⚽
