<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>GoalBot Admin — Login</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; background: #0b0f1a; color: #e4e7ef; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
    .card { background: #131826; border: 1px solid #1f2638; border-radius: 14px; padding: 2rem; width: 100%; max-width: 360px; }
    h1 { margin: 0 0 0.25rem; font-size: 1.4rem; }
    .muted { color: #8892a8; font-size: 0.85rem; margin: 0 0 1.5rem; }
    label { display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #8892a8; margin-bottom: 0.4rem; }
    input[type=email], input[type=password] { width: 100%; padding: 0.7rem 0.8rem; background: #0b0f1a; border: 1px solid #1f2638; border-radius: 8px; color: #e4e7ef; font-size: 0.95rem; margin-bottom: 1rem; }
    input:focus { outline: none; border-color: #22d3ee; }
    .row { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.25rem; font-size: 0.85rem; color: #8892a8; }
    button { width: 100%; padding: 0.75rem; background: linear-gradient(90deg, #4ade80, #22d3ee); color: #0b0f1a; border: none; border-radius: 8px; font-weight: 700; font-size: 0.95rem; cursor: pointer; }
    button:hover { opacity: 0.92; }
    .error { background: #2a1620; border: 1px solid #5b2333; color: #fca5a5; padding: 0.6rem 0.8rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1rem; }
</style>
</head>
<body>
    <form class="card" method="POST" action="{{ route('admin.login.attempt') }}">
        @csrf
        <h1>⚽ GoalBot Admin</h1>
        <p class="muted">Sign in to access the dashboard.</p>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus />

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required />

        <div class="row">
            <input type="checkbox" id="remember" name="remember" value="1" />
            <label for="remember" style="margin:0; text-transform:none; letter-spacing:0;">Remember me</label>
        </div>

        <button type="submit">Sign In</button>
    </form>
</body>
</html>
