@php
    $metaTitle        = $pageTitle ?? ($title ?? config('app.name', 'Digitech College'));
    $metaDescription = $pageDescription ?? 'Digitech College online portal — academic records, grades, attendance, enrollments, and announcements for students, teachers, and parents.';
    $metaImage       = $pageImage ?? asset('images/digitech-college-banner.jpg');
    $metaUrl         = url()->current();
    $metaSite        = config('app.name', 'Digitech College');
@endphp
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}" />
<meta name="keywords" content="Digitech College, portal, enrollment, grades, attendance, announcements, Philippines" />
<meta name="author" content="{{ $metaSite }}" />
<meta name="robots" content="index, follow" />
<meta name="theme-color" content="#7c3aed" />
<link rel="canonical" href="{{ $metaUrl }}" />
<meta property="og:type" content="website" />
<meta property="og:site_name" content="{{ $metaSite }}" />
<meta property="og:title" content="{{ $metaTitle }}" />
<meta property="og:description" content="{{ $metaDescription }}" />
<meta property="og:url" content="{{ $metaUrl }}" />
<meta property="og:image" content="{{ $metaImage }}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $metaTitle }}" />
<meta name="twitter:description" content="{{ $metaDescription }}" />
<meta name="twitter:image" content="{{ $metaImage }}" />
