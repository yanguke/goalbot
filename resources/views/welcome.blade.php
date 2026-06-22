<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a1628">

    {{-- Primary SEO --}}
    <title>GoalBot — Live World Cup 2026 Alerts on WhatsApp ⚽</title>
    <meta name="description" content="Never miss a goal. Get instant World Cup 2026 alerts on WhatsApp — live scores, goals, red cards, AI commentary and match predictions. Subscribe from KES 49.">
    <meta name="keywords" content="World Cup 2026, WhatsApp alerts, live scores, football notifications, GoalBot, Kenya, FIFA, soccer alerts, AI football">
    <meta name="author" content="GoalBot">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph (Facebook, WhatsApp, LinkedIn) --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="GoalBot">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="Never miss a World Cup 2026 goal — live on your WhatsApp ⚽">
    <meta property="og:description" content="AI-powered live alerts for every goal, red card & full-time score. Match predictions, instant updates, all on WhatsApp. From KES 49/day.">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:image:secure_url" content="{{ asset('og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="GoalBot — Live World Cup 2026 alerts on WhatsApp">
    <meta property="og:locale" content="en_US">

    {{-- Twitter / X --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Never miss a World Cup 2026 goal ⚽">
    <meta name="twitter:description" content="Live goals, red cards & AI predictions delivered to your WhatsApp. From KES 49/day.">
    <meta name="twitter:image" content="{{ asset('og-image.png') }}">
    <meta name="twitter:image:alt" content="GoalBot — Live World Cup 2026 alerts on WhatsApp">

    {{-- WhatsApp-specific (uses OG, but these help link previews load fast) --}}
    <meta property="og:image:type" content="image/png">

    {{-- Favicons --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- Structured data for Google --}}
    @php($siteUrl = url('/'))
    <script type="application/ld+json">
    @verbatim
    {
      "@context": "https://schema.org",
      "@type": "Service",
      "name": "GoalBot",
      "description": "Live World Cup 2026 alerts delivered on WhatsApp — goals, red cards, AI commentary and match predictions.",
      "areaServed": ["KE", "Worldwide"],
      "offers": {
        "@type": "Offer",
        "price": "49",
        "priceCurrency": "KES",
        "description": "24-hour access to live World Cup 2026 alerts on WhatsApp"
      },
      "provider": { "@type": "Organization", "name": "GoalBot", "url": "__SITE_URL__" }
    }
    @endverbatim
    </script>
    <script>
      // Inject site URL into JSON-LD after Blade rendering
      document.currentScript.previousElementSibling.textContent =
        document.currentScript.previousElementSibling.textContent.replace('__SITE_URL__', '{{ $siteUrl }}');
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #1a3a5c 50%, #0d2137 100%);
            color: #fff;
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6rem;
        }
        
        .brand {
            font-weight: 600;
            font-size: 1.125rem;
            letter-spacing: -0.02em;
        }
        
        .brand::before {
            content: "⚽ ";
        }
        
        .nav {
            display: flex;
            gap: 2rem;
        }
        
        .nav a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .nav a:hover { color: #00d2ff; }
        
        .hero {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 6rem;
            align-items: start;
        }
        
        h1 {
            font-size: 3.5rem;
            font-weight: 600;
            line-height: 1.1;
            letter-spacing: -0.03em;
            margin-bottom: 1.5rem;
        }
        
        .subtitle {
            font-size: 1.25rem;
            color: rgba(255,255,255,0.7);
            margin-bottom: 2.5rem;
            max-width: 400px;
        }
        
        .pricing {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .price {
            font-size: 2rem;
            font-weight: 600;
        }
        
        .price span {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.6);
            font-weight: 400;
        }
        
        .cta {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #25d366;
            color: #000;
            padding: 1rem 1.75rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .cta:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(37, 211, 102, 0.3);
        }
        
        .cta svg {
            width: 18px;
            height: 18px;
        }
        
        .features-list {
            margin-top: 3rem;
            padding-top: 3rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .features-list h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.5);
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.7);
        }
        
        .feature-item::before {
            content: "✓";
            color: #00d2ff;
            font-weight: 600;
        }
        
        .phone-wrapper {
            position: sticky;
            top: 2rem;
        }
        
        .phone {
            width: 270px;
            height: 520px;
            margin: 0 auto;
            background: #111;
            border-radius: 32px;
            padding: 10px;
            box-shadow: 
                0 0 0 1px #222,
                0 20px 40px -10px rgba(0,0,0,0.5);
        }
        
        .phone-inner {
            background: #0a0a0a;
            border-radius: 24px;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .phone-header {
            background: #111;
            padding: 1rem;
            border-bottom: 1px solid #222;
        }
        
        .phone-header-top {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .phone-title h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #fff;
        }
        
        .phone-title p {
            font-size: 0.75rem;
            color: #00d2ff;
        }
        
        .chat {
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            flex: 1;
            overflow-y: auto;
        }
        
        .chat-bubble {
            background: #1a1a1a;
            color: #fff;
            padding: 0.875rem 1rem;
            border-radius: 12px;
            border-bottom-left-radius: 4px;
            border-left: 3px solid #00d2ff;
            font-size: 0.875rem;
            max-width: 90%;
            animation: fadeIn 0.4s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .chat-time {
            font-size: 0.7rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .meta {
            margin-top: 6rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .meta-left {
            color: rgba(255,255,255,0.6);
            font-size: 0.875rem;
        }
        
        .meta-right {
            display: flex;
            gap: 2rem;
        }
        
        .meta-item {
            text-align: right;
        }
        
        .meta-item strong {
            display: block;
            font-size: 1.5rem;
            font-weight: 600;
            color: #00d2ff;
        }
        
        .meta-item span {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        @media (max-width: 900px) {
            .hero { grid-template-columns: 1fr; }
            .phone-wrapper { position: static; margin-top: 3rem; }
            h1 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="brand">GoalBot</div>
            <nav class="nav">
                <a href="https://wa.me/254715333355?text=goal">Start</a>
                <a href="/api/health">Status</a>
            </nav>
        </header>
        
        <section class="hero">
            <div class="hero-content">
                <h1>Never miss a World Cup moment.</h1>
                <p class="subtitle">Live goals, red cards & AI commentary — delivered straight to your <strong>WhatsApp</strong>. Tournament is live now. ⚽</p>
                
                {{-- Pricing temporarily commented out for testing --}}
                {{-- 
                <div class="pricing">
                    <div class="price">{{ $pricing['per_match_label'] ?? '$0.99' }} <span>/ day</span></div>
                    <div style="color: rgba(255,255,255,0.6); font-size: 0.875rem;">or {{ $pricing['full_label'] ?? '$9.99' }} — full tournament 🏆</div>
                    @if(!empty($pricing['is_kenya']))
                        <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #4ade80;">🇰🇪 Pay via M-Pesa</div>
                    @endif
                </div>
                --}}
                
                <a href="/go?{{ http_build_query(array_filter([
                    'utm_source' => request('utm_source'),
                    'utm_medium' => request('utm_medium'),
                    'utm_campaign' => request('utm_campaign'),
                    'utm_term' => request('utm_term'),
                    'utm_content' => request('utm_content'),
                ])) }}" class="cta">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Get Alerts Now
                </a>
                
                <p style="margin-top: 1rem; font-size: 0.875rem; color: rgba(255,255,255,0.6);">
                    Or send <strong>GOAL</strong> on WhatsApp to <strong>+254 715 333 355</strong>
                </p>
                <p style="margin-top: 0.5rem; font-size: 0.8rem; color: #4ade80; font-weight: 500;">
                    ✓ No app download &nbsp;✓ Cancel anytime &nbsp;✓ Works on any phone
                </p>
                
                <div class="features-list">
                    <h3>What's included</h3>
                    <div class="features-grid">
                        <div class="feature-item">Instant goal alerts</div>
                        <div class="feature-item">AI match commentary</div>
                        <div class="feature-item">Red cards & penalties</div>
                        <div class="feature-item">Half & full-time scores</div>
                        <div class="feature-item">Pre-match predictions</div>
                        <div class="feature-item">Morning digest 8 AM</div>
                    </div>
                </div>
            </div>
            
            <div class="phone-wrapper">
                <div class="phone">
                    <div class="phone-inner">
                        <div class="phone-header">
                            <div class="phone-header-top">
                                <div class="avatar">⚽</div>
                                <div class="phone-title">
                                    <h4>GoalBot</h4>
                                    <p>online</p>
                                </div>
                            </div>
                        </div>
                        <div class="chat">
                            <div class="chat-bubble" style="animation-delay: 0.1s">
                                🔴 Live: Mexico vs South Africa has kicked off! The greatest show on earth begins! 🏆
                                <div class="chat-time">20:00</div>
                            </div>
                            <div class="chat-bubble" style="animation-delay: 0.3s">
                                ⚽ Goal: Lozano scores for Mexico! Magical free-kick at 23' 🇲🇽 1-0
                                <div class="chat-time">20:23</div>
                            </div>
                            <div class="chat-bubble" style="animation-delay: 0.5s">
                                🟥 Red card: Mokoena sent off! South Africa down to 10 men.
                                <div class="chat-time">20:47</div>
                            </div>
                            <div class="chat-bubble" style="animation-delay: 0.7s">
                                🏁 Full time ET: Mexico win 3-3 (4-3 pens)! What an opening match! 🏆
                                <div class="chat-time">21:45</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <div class="meta">
            <div class="meta-left">World Cup 2026 is LIVE ⚽ &nbsp;Send GOAL to +254 715 333 355</div>
            <div class="meta-right">
                <div class="meta-item">
                    <strong>104</strong>
                    <span>Matches</span>
                </div>
                <div class="meta-item">
                    <strong>&lt;5s</strong>
                    <span>Alert speed</span>
                </div>
                <div class="meta-item">
                    <strong>KES 49</strong>
                    <span>Per day</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
