<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $subject }}</title></head>
<body style="font-family:Arial,sans-serif;color:#17202a;line-height:1.6;max-width:600px;margin:32px auto">
<h1>NABAKU Fundraiser</h1><h2>{{ $subject }}</h2><p>{{ $messageText }}</p>
@if($url)<p><a href="{{ $url }}">Continue</a></p><p>{{ $url }}</p>@endif
<p>If you did not request this, contact your organization administrator.</p>
</body></html>
