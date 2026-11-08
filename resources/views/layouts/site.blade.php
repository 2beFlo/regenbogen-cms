<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#005436">
    @php
        $canonicalPath = '/'.trim(request()->path(), '/');
        $canonicalUrl = 'https://regenbogen-duderstadt.de'.($canonicalPath === '/' ? '/' : $canonicalPath);
        $siteName = filled($settings->site_title) ? $settings->site_title : 'REGENBOGEN';
        $seoTitle = trim($__env->yieldContent('title', $siteName));
        $seoDescription = trim($__env->yieldContent('description', 'Das Magazin der Grünen Untereichsfeld: aktuelle Artikel und Ausgaben aus Duderstadt und dem Untereichsfeld.'));
        if ($seoDescription === '') $seoDescription = 'Das Magazin der Grünen Untereichsfeld: aktuelle Artikel und Ausgaben aus Duderstadt und dem Untereichsfeld.';
        $robotsContent = trim($__env->yieldContent('robots', 'index,follow'));
        $organizationId = 'https://regenbogen-duderstadt.de/#organization';
        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => 'https://regenbogen-duderstadt.de/#website',
            'url' => 'https://regenbogen-duderstadt.de/',
            'name' => $siteName,
            'inLanguage' => 'de-DE',
            'publisher' => ['@id' => $organizationId],
        ];
        $publisherSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsMediaOrganization',
            '@id' => $organizationId,
            'name' => $siteName.' – Das Magazin der Grünen Untereichsfeld',
            'url' => 'https://regenbogen-duderstadt.de/',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => 'https://regenbogen-duderstadt.de/assets/regenbogen-mark.png',
            ],
        ];
        $socialImage = trim($__env->yieldContent('image')) ?: 'https://regenbogen-duderstadt.de/assets/regenbogen-mark.png';
    @endphp
    <title>{{ $seoTitle }} – {{ $siteName }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $robotsContent }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="de_DE">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $seoTitle }} – {{ $siteName }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }} – {{ $siteName }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <script type="application/ld+json">{!! json_encode($publisherSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script type="application/ld+json">{!! json_encode($websiteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <link rel="icon" href="{{ asset('assets/regenbogen-mark.svg') }}?v={{ @filemtime(public_path('assets/regenbogen-mark.svg')) ?: '1.1.6' }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('assets/regenbogen-mark.png') }}?v={{ @filemtime(public_path('assets/regenbogen-mark.png')) ?: '1.1.6' }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('assets/regenbogen-mark.png') }}?v={{ @filemtime(public_path('assets/regenbogen-mark.png')) ?: '1.1.6' }}">
    <link rel="stylesheet" href="{{ asset('css/regenbogen.css') }}?v={{ @filemtime(public_path('css/regenbogen.css')) ?: '1.5.5' }}">
    @yield('head')
</head>
<body>
<a class="skip-link" href="#inhalt">Direkt zum Inhalt</a>

<header class="site-header">
    <div class="shell">
        <p class="eyebrow">{{ $settings->masthead_kicker }}</p>

        <a class="masthead" href="{{ route('home') }}" aria-label="REGENBOGEN – Startseite">
            <span class="masthead-lockup">
                <span class="masthead-wordmark">REGENBOGEN</span>
                <img class="brand-mark" src="{{ asset('assets/regenbogen-mark.svg') }}" alt="" aria-hidden="true">
            </span>
        </a>

        <div class="strapline">
            <strong>{{ $settings->strapline }}</strong>
            <span>{{ optional(\App\Models\Issue::query()->where('is_current', true)->first())->title ?? 'DUDERSTADT & UNTEREICHSFELD' }}</span>
        </div>

        <nav class="primary-nav" aria-label="Hauptnavigation">
            <ul class="primary-nav-list">
                <li><a href="{{ route('home') }}">Start</a></li>
                <li><a href="{{ route('home') }}#beitraege">Beiträge</a></li>
                <li><a href="{{ route('issues.index') }}">Ausgaben</a></li>
                @foreach($navigation as $navPage)
                    <li><a href="{{ route('pages.show', $navPage->slug) }}">{{ $navPage->title }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>
</header>

<main id="inhalt" class="site-main">
    @yield('content')
</main>

<footer class="site-footer" id="impressum">
    <div class="shell footer-brand">
        <strong>{{ $settings->footer_claim }}</strong>
        <img class="brand-mark" src="{{ asset('assets/regenbogen-mark.svg') }}" alt="" aria-hidden="true">
        <strong><a href="{{ route('home') }}">REGENBOGEN-DUDERSTADT.DE</a></strong>
    </div>

    <div class="shell footer-grid">
        <div>
            <h2>Herausgeberin</h2>
            <p>{!! \App\Support\EmailProtector::protectPlainText($settings->publisher) !!}</p>
        </div>
        <div>
            <h2>Redaktion</h2>
            <p>{!! \App\Support\EmailProtector::protectPlainText($settings->editorial) !!}</p>
        </div>
        <div>
            <h2>Kontakt</h2>
            <p><a href="{{ route('pages.show', 'kontakt') }}">Kontakt aufnehmen</a></p>
            @if($settings->contact_label)
                <p class="footer-contact-secondary">
                    @if($settings->contact_url)
                        {!! \App\Support\EmailProtector::protectLink($settings->contact_url, $settings->contact_label) !!}
                    @else
                        {!! \App\Support\EmailProtector::protectPlainText($settings->contact_label) !!}
                    @endif
                </p>
            @endif
        </div>
        <div>
            <h2>Erscheinungsweise</h2>
            <p>{{ $settings->frequency }}</p>
        </div>
    </div>

    <nav class="shell footer-nav" aria-label="Weitere Seiten">
        <ul>
            @foreach($footerPages as $footerPage)
                <li><a href="{{ route('pages.show', $footerPage->slug) }}">{{ $footerPage->title }}</a></li>
            @endforeach
        </ul>
    </nav>
</footer>
    <script src="{{ asset('js/email-protection.js') }}" defer></script>
</body>
</html>
