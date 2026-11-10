<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#005436">
    @php
        $seoMetadata = $seoMetadata ?? \App\Support\SeoMetadata::resolve(
            $settings,
            '/'.trim(request()->path(), '/'),
            defaultTitle: $settings->site_title,
        );
        $siteName = filled($settings->site_title) ? $settings->site_title : 'REGENBOGEN';
        $siteUrl = \App\Support\SeoMetadata::siteUrl($settings);
        $organizationId = $siteUrl.'/#organization';
        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $siteUrl.'/#website',
            'url' => $siteUrl.'/',
            'name' => $siteName,
            'inLanguage' => 'de-DE',
            'publisher' => ['@id' => $organizationId],
        ];
        $publisherSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsMediaOrganization',
            '@id' => $organizationId,
            'name' => $settings->seo_publisher_name ?: $siteName,
            'url' => $settings->seo_publisher_url ?: $siteUrl,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => \App\Support\SeoMetadata::imageUrl($settings, null, publisher: true),
            ],
        ];
    @endphp
    <title>{{ $seoMetadata['title'] }}</title>
    <meta name="description" content="{{ $seoMetadata['description'] }}">
    <meta name="robots" content="{{ $seoMetadata['robots'] }}">
    <link rel="canonical" href="{{ $seoMetadata['canonical'] }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="de_DE">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $seoMetadata['og_title'] }}">
    <meta property="og:description" content="{{ $seoMetadata['og_description'] }}">
    <meta property="og:url" content="{{ $seoMetadata['canonical'] }}">
    <meta property="og:image" content="{{ $seoMetadata['og_image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoMetadata['og_title'] }}">
    <meta name="twitter:description" content="{{ $seoMetadata['og_description'] }}">
    <meta name="twitter:image" content="{{ $seoMetadata['og_image'] }}">
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
