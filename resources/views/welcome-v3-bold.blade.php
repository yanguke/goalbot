<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoalBot - Get Every World Cup Moment</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #000;
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .noise {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            opacity: 0.03;
            z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 2;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            margin-bottom: 4rem;
        }
        
        .logo {
            font-weight: 900;
            font-size: 1.75rem;
            letter-spacing: -0.02em;
        }
        
        .logo span { color: #25d366; }
        
        .nav-links a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-weight: 600;
            margin-left: 2rem;
            transition: color 0.3s;
        }
        
        .nav-links a:hover { color: #25d366; }
        
        .hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            min-height: 75vh;
        }
        
        .hero-content h1 {
            font-size: 5rem;
            font-weight: 900;
            line-height: 0.95;
            margin-bottom: 2rem;
            letter-spacing: -0.03em;
            text-transform: uppercase;
        }
        
        .hero-content h1 .highlight {
            color: #25d366;
            display: block;
        }
        
        .hero-content p {
            font-size: 1.35rem;
            color: rgba(255,255,255,0.6);
            margin-bottom: 2.5rem;
            line-height: 1.5;
            max-width: 450px;
        }
        
        .cta-group {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .cta-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #25d366;
            color: #000;
            padding: 1.25rem 2.5rem;
            border-radius: 0;
            font-weight: 800;
            font-size: 1.1rem;
            text-decoration: none;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        
        .cta-button:hover {
            background: #fff;
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(37, 211, 102, 0.3);
        }
        
        .price-pill {
            background: rgba(255,255,255,0.1);
            padding: 0.75rem 1.5rem;
            border: 1px solid rgba(255,255,255,0.2);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .marquee {
            background: #25d366;
            color: #000;
            padding: 0.75rem 0;
            overflow: hidden;
            white-space: nowrap;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 4rem;
        }
        
        .marquee-content {
            display: inline-block;
            animation: marquee 20s linear infinite;
        }
        
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        
        .phone-container {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        
        .phone-frame {
            position: absolute;
            width: 280px;
            height: 540px;
            border: 2px solid #25d366;
            border-radius: 42px;
            transform: rotate(-3deg);
            opacity: 0.3;
        }
        
        .phone-container:hover .phone-frame {
            transform: rotate(-3deg) scale(1.02);
        }
        
        .phone-container:hover .phone {
            transform: rotate(-3deg) scale(1.01);
            transition: transform 0.3s ease;
        }
        
        .phone {
            width: 280px;
            height: 540px;
            background: #111;
            border-radius: 38px;
            padding: 12px;
            position: relative;
            z-index: 2;
            transform: rotate(-3deg);
            transition: transform 0.3s ease;
        }
        
        .phone-screen {
            width: 100%;
            height: 100%;
            background: #0a0a0a;
            border-radius: 30px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .phone-header {
            background: #111;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid #222;
        }
        
        .phone-avatar {
            width: 40px;
            height: 40px;
            background: #25d366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .phone-title h3 { font-size: 1rem; font-weight: 700; }
        .phone-title p { font-size: 0.75rem; color: #25d366; }
        
        .chat-area {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .message {
            max-width: 90%;
            padding: 1rem;
            background: #1a1a1a;
            border-left: 3px solid #25d366;
            font-size: 0.9rem;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-time {
            font-size: 0.7rem;
            color: #666;
            margin-top: 0.5rem;
            text-transform: uppercase;
        }
        
        .stats-row {
            display: flex;
            gap: 3rem;
            margin-top: 3rem;
            padding-top: 3rem;
            border-top: 1px solid #222;
        }
        
        .stat-item h3 {
            font-size: 3rem;
            font-weight: 900;
            color: #25d366;
        }
        
        .stat-item p {
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .footer {
            margin-top: 6rem;
            padding: 2rem 0;
            text-align: center;
            color: rgba(255,255,255,0.4);
            font-size: 0.85rem;
            border-top: 1px solid #222;
        }
        
        @media (max-width: 968px) {
            .hero { grid-template-columns: 1fr; }
            .hero-content h1 { font-size: 3rem; }
            .cta-group { flex-direction: column; align-items: flex-start; }
            .stats-row { flex-direction: column; gap: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="noise"></div>
    
    <div class="container">
        <header class="header">
            <div class="logo">GOAL<span>BOT</span></div>
            <nav class="nav-links">
                <a href="#">Pricing</a>
                <a href="/api/health">Status</a>
            </nav>
        </header>
        
        <section class="hero">
            <div class="hero-content">
                <h1>
                    GET EVERY
                    <span class="highlight">MOMENT</span>
                </h1>
                <p><strong>AI-POWERED MATCH INTELLIGENCE.</strong> Context, stats, personality—not just scores. Delivered instantly to your WhatsApp.</p>
                
                <div class="cta-group">
                    <a href="https://wa.me/YOUR_PHONE?text=START" class="cta-button">
                        START NOW →
                    </a>
                    <div class="price-pill">From $2.99/match</div>
                </div>
                
                <div class="stats-row">
                    <div class="stat-item">
                        <h3>104</h3>
                        <p>Matches</p>
                    </div>
                    <div class="stat-item">
                        <h3>AI</h3>
                        <p>Powered</p>
                    </div>
                    <div class="stat-item">
                        <h3>48</h3>
                        <p>Teams</p>
                    </div>
                </div>
            </div>
            
            <div class="phone-container">
                <div class="phone-frame"></div>
                <div class="phone">
                    <div class="phone-screen">
                        <div class="phone-header">
                            <div class="phone-avatar">⚽</div>
                            <div class="phone-title">
                                <h3>GoalBot</h3>
                                <p>● LIVE</p>
                            </div>
                        </div>
                        <div class="chat-area">
                            <div class="message" style="animation-delay: 0.2s">
                                🔴 KICKOFF<br>Argentina vs France LIVE! Messi returns after his hat-trick vs Croatia.
                                <div class="message-time">20:00 UTC</div>
                            </div>
                            <div class="message" style="animation-delay: 0.5s">
                                ⚽ GOAL<br>Messi. 23'. His 8th World Cup goal—now tied with Ronaldo all-time! 1-0.
                                <div class="message-time">20:23 UTC</div>
                            </div>
                            <div class="message" style="animation-delay: 0.8s">
                                🟥 RED CARD<br>France. 67'. Down to 10.
                                <div class="message-time">20:47 UTC</div>
                            </div>
                            <div class="message" style="animation-delay: 1.1s">
                                🏁 FULL TIME<br>Argentina 2-1. Group C winners.
                                <div class="message-time">21:45 UTC</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <div class="marquee">
            <div class="marquee-content">
                ⚽ WORLD CUP 2026 ⚽ USA CANADA MEXICO ⚽ 104 MATCHES ⚽ 48 TEAMS ⚽ LIVE ALERTS ⚽ AI POWERED ⚽ WORLD CUP 2026 ⚽ USA CANADA MEXICO ⚽ 104 MATCHES ⚽ 48 TEAMS ⚽ LIVE ALERTS ⚽ AI POWERED ⚽
            </div>
        </div>
        
        <footer class="footer">
            <p>World Cup 2026 ⚽ Premium Alert Service</p>
        </footer>
    </div>
</body>
</html>
