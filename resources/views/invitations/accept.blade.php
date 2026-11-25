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
        <input id="name" name="name" value="{{ old('name', $invitation->name) }}" required autocomplete="name">
        @error('name')<p class="error">{{ $message }}</p>@enderror
        <label for="phone">Mobile (optional)</label>
        <div class="phone">
            <select name="phone_cc" aria-label="Mobile country code">
                @foreach (array_keys(config('phone_codes')) as $cc)
                    <option value="{{ $cc }}" @selected(old('phone_cc', '+973') === $cc)>{{ $cc }}</option>
                @endforeach
            </select>
            <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="20" pattern="[0-9 ]{4,20}" autocomplete="tel-national">
        </div>
        @error('phone')<p class="error">{{ $message }}</p>@enderror
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
