<?php

namespace App\Providers;

use App\Console\Commands\InstallRegenbogen;
use App\Console\Commands\QualityCheckRegenbogen;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The Advanced Rich Editor's own iframe sanitizer is tailored to its
        // YouTube/Vimeo embed node. REGENBOGEN uses a separate generic iframe node
        // for deliberately embedded editorial tools such as Matomo opt-out.
        config()->set('filament-advanced-rich-editor.embed.sanitizer', false);

        $this->app->extend(
            HtmlSanitizerConfig::class,
            fn (HtmlSanitizerConfig $config): HtmlSanitizerConfig => $config
                ->allowElement('iframe', [
                    'src',
                    'title',
                    'width',
                    'height',
                    'loading',
                    'referrerpolicy',
                    'allow',
                    'allowfullscreen',
                    'sandbox',
                    'class',
                    'style',
                    'scrolling',
                    'frameborder',
                ])
                ->allowMediaSchemes(['https', 'http'])
                ->allowRelativeMedias(),
        );
    }

    public function boot(): void
    {
        // Rich editorial documents can exceed Livewire's default nested payload depth.
        // Set only this key at runtime so vendor defaults remain intact across updates.
        config()->set('livewire.payload.max_nesting_depth', 32);
        // Filament's FileUpload uses Livewire's temporary upload endpoint first.
        // Raise that global gate as well as the per-field PDF limit.
        config()->set('livewire.temporary_file_upload.rules', ['required', 'file', 'max:122880']);

        FilamentAsset::register([
            Js::make(
                'regenbogen/iframe-rich-content',
                resource_path('js/dist/filament/rich-content-plugins/iframe-rich-content.js'),
            )->loadedOnRequest(),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([InstallRegenbogen::class, QualityCheckRegenbogen::class]);
        }
    }
}
