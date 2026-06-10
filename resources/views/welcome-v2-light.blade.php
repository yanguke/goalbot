<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoalBot - World Cup 2026 WhatsApp Notifications</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            color: #1e293b;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            margin-bottom: 3rem;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 800;
            font-size: 1.5rem;
            color: #1e293b;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .nav-links a {
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            margin-left: 2rem;
            transition: color 0.3s;
        }
        
        .nav-links a:hover { color: #1e293b; }
        
        .hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            min-height: 70vh;
        }
        
        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            color: #0f172a;
        }
        
        .hero-content h1 span {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .hero-content p {
            font-size: 1.25rem;
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .pricing-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #fef3c7;
            color: #92400e;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .cta-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: #fff;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 14px rgba(37, 211, 102, 0.3);
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
        }
        
        .features {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .phone-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .phone {
            width: 260px;
            height: 520px;
            background: #fff;
            border-radius: 36px;
            padding: 10px;
            box-shadow: 
                0 0 0 1px #e2e8f0,
                0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        
        .phone-screen {
            width: 100%;
            height: 100%;
            background: #f1f5f9;
            border-radius: 30px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .phone-header {
            background: #fff;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .phone-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .phone-title { flex: 1; }
        .phone-title h3 { font-size: 1rem; font-weight: 600; color: #0f172a; }
        .phone-title p { font-size: 0.75rem; color: #22c55e; }
        
        .chat-area {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            background: #e5e7eb url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect fill="%23e5e7eb" width="100" height="100"/><circle fill="%23d1d5db" cx="50" cy="50" r="1"/></svg>');
        }
        
        .chat-date {
            text-align: center;
            color: #6b7280;
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .message {
            max-width: 85%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            line-height: 1.4;
            position: relative;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-bot {
            background: #fff;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            color: #1f2937;
        }
        
        .message-time {
            font-size: 0.65rem;
            color: #9ca3af;
            text-align: right;
            margin-top: 0.25rem;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 4rem;
            padding: 2rem;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        
        .stat { text-align: center; }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-label { color: #64748b; margin-top: 0.5rem; }
        
        .how-it-works {
            margin-top: 6rem;
            text-align: center;
        }
        
        .how-it-works h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3rem;
            color: #0f172a;
        }
        
        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }
        
        .step {
            background: #fff;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            margin: 0 auto 1rem;
        }
        
        .step h3 { font-size: 1.25rem; margin-bottom: 0.5rem; color: #0f172a; }
        .step p { color: #64748b; font-size: 0.95rem; line-height: 1.5; }
        
        .footer {
            margin-top: 6rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        @media (max-width: 968px) {
            .hero { grid-template-columns: 1fr; text-align: center; }
            .hero-content h1 { font-size: 2.5rem; }
            .features { justify-content: center; flex-wrap: wrap; }
            .steps { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <div class="logo-icon">⚽</div>
                <span>GoalBot</span>
            </div>
            <nav class="nav-links">
                <a href="#how-it-works">How It Works</a>
                <a href="/api/health">API Status</a>
            </nav>
        </header>
        
        <section class="hero">
            <div class="hero-content">
                <div class="pricing-tag">💎 Premium Service</div>
                <h1>Never Miss a <span>World Cup</span> Moment</h1>
                <p><strong>AI-crafted commentary</strong> with context, stats, and personality. Not robotic scores—real storytelling delivered to your WhatsApp.</p>
                
                <a href="https://wa.me/YOUR_PHONE?text=SUBSCRIBE" class="cta-button">
                    ⚡ Start Your Alerts
                </a>
                
                <div class="features">
                    <div class="feature"><span>⚡</span> Instant notifications</div>
                    <div class="feature"><span>🧠</span> AI-powered</div>
                    <div class="feature"><span>📊</span> Smart context</div>
                </div>
            </div>
            
            <div class="phone-container">
                <div class="phone">
                    <div class="phone-screen">
                        <div class="phone-header">
                            <div class="phone-avatar">⚽</div>
                            <div class="phone-title">
                                <h3>GoalBot</h3>
                                <p>● online</p>
                            </div>
                        </div>
                        <div class="chat-area">
                            <div class="chat-date">Today</div>
                            
                            <div class="message message-bot">
                                🔴 LIVE! Argentina vs France kicks off at Estadio Azteca! Messi starts after his 3-game rest.
                                <div class="message-time">8:00 PM ✓✓</div>
                            </div>
                            
                            <div class="message message-bot">
                                ⚽ GOAL! Argentina 1-0! Messi scores at 23'—his 8th World Cup goal, now tied with Ronaldo on the all-time list! 🐐
                                <div class="message-time">8:23 PM ✓✓</div>
                            </div>
                            
                            <div class="message message-bot">
                                🟥 RED CARD! France down to 10 men at 67'
                                <div class="message-time">8:47 PM ✓✓</div>
                            </div>
                            
                            <div class="message message-bot">
                                🏁 FULL TIME! Argentina win 2-1! 🏆
                                <div class="message-time">9:45 PM ✓✓</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <section class="stats">
            <div class="stat">
                <div class="stat-number">104</div>
                <div class="stat-label">Matches Covered</div>
            </div>
            <div class="stat">
                <div class="stat-number">&lt;1s</div>
                <div class="stat-label">Alert Speed</div>
            </div>
            <div class="stat">
                <div class="stat-number">48</div>
                <div class="stat-label">Teams</div>
            </div>
        </section>
        
        <section class="how-it-works" id="how-it-works">
            <h2>Get Started in Seconds</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Subscribe</h3>
                    <p>Message GoalBot on WhatsApp. Choose your favorite team for personalized alerts.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Select Plan</h3>
                    <p>Pick your notification package. Pay only for the matches you want to follow.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Enjoy</h3>
                    <p>Receive instant AI-powered updates. Never miss a goal or big moment again.</p>
                </div>
            </div>
        </section>
        
        <footer class="footer">
            <p>Built for World Cup 2026 ⚽</p>
        </footer>
    </div>
</body>
</html>
