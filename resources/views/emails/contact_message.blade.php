<h2>New contact message</h2>

<p><strong>Name:</strong> {{ $name }}</p>
<p><strong>Email:</strong> {{ $email }}</p>
<p><strong>Subject:</strong> {{ $subjectLine }}</p>
<p><strong>Locale:</strong> {{ $messageLocale }}</p>
<p><strong>IP:</strong> {{ $ip }}</p>
<p><strong>User Agent:</strong> {{ $userAgent }}</p>

<hr>

<p>{!! nl2br(e($content)) !!}</p>
