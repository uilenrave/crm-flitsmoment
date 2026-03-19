<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen — Flitsmoment CRM</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #fff; border-radius: .75rem; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem; width: 100%; max-width: 380px; }
        .login-logo { text-align: center; margin-bottom: 2rem; }
        .login-logo h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
        .login-logo p { font-size: .875rem; color: #64748b; margin-top: .25rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: .8rem; font-weight: 500; color: #475569; margin-bottom: .35rem; }
        input { width: 100%; padding: .625rem .75rem; border: 1px solid #e2e8f0; border-radius: .375rem; font-size: .875rem; outline: none; }
        input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .error-text { font-size: .75rem; color: #dc2626; margin-top: .25rem; }
        .btn { display: block; width: 100%; padding: .7rem; background: #2563eb; color: #fff; border: none; border-radius: .375rem; font-size: .875rem; font-weight: 600; cursor: pointer; margin-top: 1.25rem; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: .75rem; background: #fee2e2; color: #991b1b; border-radius: .375rem; font-size: .8rem; margin-bottom: 1rem; border: 1px solid #fecaca; }
        .remember { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: #475569; margin-top: .5rem; }
        .remember input[type=checkbox] { width: auto; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <h1>Flitsmoment</h1>
            <p>CRM Systeem</p>
        </div>

        @if($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        @if(session('success'))
            <div class="alert" style="background:#dcfce7;color:#166534;border-color:#bbf7d0;">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="email">E-mailadres</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Wachtwoord</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="remember">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Ingelogd blijven</label>
            </div>
            <button type="submit" class="btn">Inloggen</button>
        </form>
    </div>
</body>
</html>
