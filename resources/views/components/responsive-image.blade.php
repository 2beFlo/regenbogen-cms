@props([
    'path',
    'alt' => '',
    'class' => null,
    'loading' => 'lazy',
    'sizes' => '100vw',
    'widths' => [480, 800, 1200, 1600],
    'fetchpriority' => null,
])
@php
    $imagePath = (string) $path;
    $optimizable = \App\Support\ResponsiveImages::isOptimizable($imagePath);
    $dimensions = $optimizable ? \App\Support\ResponsiveImages::dimensions($imagePath) : null;
    $source = $optimizable
        ? \App\Support\ResponsiveImages::url($imagePath, (int) max($widths))
        : \Illuminate\Support\Facades\Storage::disk('uploads')->url($imagePath);
@endphp
<img
    src="{{ $source }}"
    @if($optimizable) srcset="{{ \App\Support\ResponsiveImages::srcset($imagePath, $widths) }}" sizes="{{ $sizes }}" @endif
    @if($dimensions) width="{{ $dimensions['width'] }}" height="{{ $dimensions['height'] }}" @endif
    @if($class) class="{{ $class }}" @endif
    alt="{{ $alt }}"
    @if($loading) loading="{{ $loading }}" @endif
    decoding="async"
    @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
>
