<h2>New contact message</h2>

<p><strong>Name:</strong> {{ $name }}</p>
<p><strong>Email:</strong> {{ $email }}</p>
@if($phone)
<p><strong>Phone:</strong> {{ $phone }}</p>
@endif
@if($company)
<p><strong>Company:</strong> {{ $company }}</p>
@endif
<p><strong>Subject:</strong> {{ $subjectLine }}</p>
<p><strong>Locale:</strong> {{ $messageLocale }}</p>
<p><strong>IP:</strong> {{ $ip }}</p>
<p><strong>User Agent:</strong> {{ $userAgent }}</p>

<hr>

<p>{!! nl2br(e($content)) !!}</p>
