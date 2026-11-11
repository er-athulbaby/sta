<!doctype html>
<html lang="en">
<head>
    @include('invitations._head')
    <title>Accept invitation</title>
</head>
<body>
<main>
    @include('invitations._brand')
    <h1>Join {{ $invitation->association->name }}</h1>
    <p>You are invited as <strong>{{ $invitation->role->label() }}</strong> with <strong>{{ $invitation->email }}</strong>.</p>
    <form method="post" action="{{ route('invitations.accept', $token) }}">
        @csrf
        @error('email')<p class="error">{{ $message }}</p>@enderror
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
