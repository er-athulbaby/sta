<!doctype html>
<html lang="en">
<head>
    @include('invitations._head')
    <title>Account deactivated</title>
</head>
<body>
<main>
    @include('invitations._brand')
    <h1>Your account is deactivated</h1>
    <p>{{ $reason }} Please contact {{ $contact }} if you think this is a mistake.</p>
    <p><a href="{{ $login }}">Back to sign in</a></p>
</main>
</body>
</html>
