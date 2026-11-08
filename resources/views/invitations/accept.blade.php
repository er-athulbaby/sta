<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept invitation</title>
    <style>
        body{font-family:system-ui,Arial,sans-serif;background:#f1f5f9;margin:0;padding:24px}
        main{max-width:420px;margin:40px auto;background:#fff;padding:28px;border-radius:12px}
        label{display:block;margin-top:14px;font-weight:600}
        input{width:100%;padding:10px;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        button{margin-top:20px;width:100%;padding:12px;background:#1f3a5f;color:#fff;border:0;border-radius:8px;font-weight:600}
        .error{color:#b91c1c;font-size:14px}
    </style>
</head>
<body>
<main>
    <h1>Join {{ $invitation->association->name }}</h1>
    <p>You are invited as <strong>{{ $invitation->role->label() }}</strong> with <strong>{{ $invitation->email }}</strong>.</p>
    <form method="post" action="{{ route('invitations.accept', $token) }}">
        @csrf
        <label for="name">Your full name</label>
        <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
        @error('name')<p class="error">{{ $message }}</p>@enderror
        <label for="password">Password (at least 12 characters)</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        @error('password')<p class="error">{{ $message }}</p>@enderror
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        @error('token')<p class="error">{{ $message }}</p>@enderror
        <button type="submit">Create account</button>
    </form>
</main>
</body>
</html>
