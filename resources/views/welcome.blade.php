<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoalBot - World Cup 2026 WhatsApp Notifications</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* Header */
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
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #fff;
        }
        
        /* Hero Section */
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
            background: linear-gradient(135deg, #fff 0%, #00d2ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-content p {
            font-size: 1.25rem;
            color: rgba(255,255,255,0.7);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .cta-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: #fff;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(37, 211, 102, 0.3);
        }
        
        .cta-button svg {
            width: 24px;
            height: 24px;
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
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
        }
        
        /* Phone Mockup */
        .phone-container {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        
        .phone {
            width: 280px;
            height: 560px;
            background: #1a1a2e;
            border-radius: 36px;
            padding: 10px;
            box-shadow: 
                0 0 0 4px #2d2d44,
                0 20px 60px rgba(0,0,0,0.5),
                0 0 60px rgba(0, 210, 255, 0.1);
            position: relative;
        }
        
        .phone-screen {
            width: 100%;
            height: 100%;
            background: #0d0d1a;
            border-radius: 30px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .phone-header {
            background: #075e54;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .phone-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .phone-title {
            flex: 1;
        }
        
        .phone-title h3 {
            font-size: 1rem;
            font-weight: 600;
        }
        
        .phone-title p {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.6);
        }
        
        .chat-area {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            overflow-y: auto;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect fill="%230d0d1a" width="100" height="100"/><circle fill="%23151525" cx="50" cy="50" r="1"/></svg>');
        }
        
        .chat-date {
            text-align: center;
            color: rgba(255,255,255,0.4);
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
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .message-bot {
            background: #075e54;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        
        .message-time {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.5);
            text-align: right;
            margin-top: 0.25rem;
        }
        
        /* Sample Messages */
        .typing-indicator {
            display: flex;
            gap: 0.25rem;
            padding: 0.75rem 1rem;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            background: rgba(255,255,255,0.5);
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }
        
        /* Glow Effects */
        .glow {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(0,210,255,0.2) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
        }
        
        .glow-1 {
            top: -100px;
            right: -100px;
        }
        
        .glow-2 {
            bottom: -100px;
            left: -100px;
            background: radial-gradient(circle, rgba(37,211,102,0.15) 0%, transparent 70%);
        }
        
        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 4rem;
            padding-top: 4rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            color: rgba(255,255,255,0.6);
            margin-top: 0.5rem;
        }
        
        /* How It Works */
        .how-it-works {
            margin-top: 6rem;
            text-align: center;
        }
        
        .how-it-works h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3rem;
        }
        
        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }
        
        .step {
            background: rgba(255,255,255,0.05);
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
            margin: 0 auto 1rem;
        }
        
        .step h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        
        .step p {
            color: rgba(255,255,255,0.6);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        /* Footer */
        .footer {
            margin-top: 6rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            color: rgba(255,255,255,0.4);
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 968px) {
            .hero {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .features {
                justify-content: center;
            }
            
            .phone-container {
                order: -1;
            }
            
            .phone {
                width: 260px;
                height: 520px;
            }
            
            .steps {
                grid-template-columns: 1fr;
            }
            
            .stats {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        @media (max-height: 800px) {
            .phone {
                width: 240px;
                height: 480px;
            }
            
            .hero {
                min-height: auto;
                padding: 1rem 0;
            }
        }
    </style>
</head>
<body>
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>
    
    <div class="container">
        <header class="header">
            <div class="logo">
                <div class="logo-icon">⚽</div>
                <span>GoalBot</span>
            </div>
            <nav class="nav-links">
                <a href="#how-it-works">How It Works</a>
                <a href="https://goalbot.test/api/health">API Status</a>
            </nav>
        </header>
        
        <section class="hero">
            <div class="hero-content">
                <h1>Never Miss a World Cup Moment</h1>
                <p><strong>AI-powered match commentary</strong> delivered to your WhatsApp. Not just scores—context, stats, and personality in every alert.</p>
                
                <a href="https://wa.me/YOUR_PHONE_NUMBER?text=SUBSCRIBE" class="cta-button">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Start on WhatsApp
                </a>
                
                <div class="features">
                    <div class="feature">
                        <span>⚡</span> Instant notifications
                    </div>
                    <div class="feature">
                        <span>�</span> AI-powered
                    </div>
                    <div class="feature">
                        <span>💎</span> Premium alerts
                    </div>
                </div>
            </div>
            
            <div class="phone-container">
                <div class="phone">
                    <div class="phone-screen">
                        <div class="phone-header">
                            <div class="phone-avatar">⚽</div>
                            <div class="phone-title">
                                <h3>GoalBot</h3>
                                <p>online</p>
                            </div>
                        </div>
                        <div class="chat-area">
                            <div class="chat-date">Today</div>
                            
                            <div class="message message-bot" style="animation-delay: 0.2s">
                                🔴 LIVE! Argentina vs France has kicked off at Estadio Azteca! ⚽
                                <div class="message-time">8:00 PM</div>
                            </div>
                            
                            <div class="message message-bot" style="animation-delay: 0.6s">
                                ⚽ GOAL! Argentina 1-0! Messi scores at 23' with a brilliant free kick 🐐
                                <div class="message-time">8:23 PM</div>
                            </div>
                            
                            <div class="message message-bot" style="animation-delay: 1s">
                                🟥 RED CARD! France down to 10 men. Griezmann sent off at 67' 🚨
                                <div class="message-time">8:47 PM</div>
                            </div>
                            
                            <div class="message message-bot" style="animation-delay: 1.4s">
                                🏁 FULL TIME! Argentina win 2-1 and top Group C! 🏆
                                <div class="message-time">9:45 PM</div>
                            </div>
                            
                            <div class="typing-indicator" style="animation-delay: 1.8s">
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
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
                <div class="stat-label">Average Alert Time</div>
            </div>
            <div class="stat">
                <div class="stat-number">48</div>
                <div class="stat-label">Teams Tracking</div>
            </div>
        </section>
        
        <section class="how-it-works" id="how-it-works">
            <h2>How GoalBot Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Subscribe</h3>
                    <p>Send "SUBSCRIBE" to GoalBot on WhatsApp. Set your favorite team to get personalized alerts.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Watch</h3>
                    <p>GoalBot monitors every World Cup match in real-time using official FIFA data feeds.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Receive</h3>
                    <p>Get instant AI-generated notifications with context—player stats, historical comparisons, and dramatic flair.</p>
                </div>
            </div>
        </section>
        
        <footer class="footer">
            <p>Built for World Cup 2026 USA/Canada/Mexico ⚽</p>
                    </footer>
    </div>
</body>
</html>
