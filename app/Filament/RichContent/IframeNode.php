<?php

namespace App\Filament\RichContent;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

class IframeNode extends Node
{
    public static $name = 'regenbogenIframe';

    public function addOptions(): array
    {
        return [
            'HTMLAttributes' => [],
        ];
    }

    public function parseHTML(): array
    {
        return [
            // Do not require src in the selector. The editor must first keep
            // the node so all attributes can be round-tripped through source
            // mode; URL validation happens in the HTML sanitizer.
            ['tag' => 'iframe'],
        ];
    }

    public function addAttributes(): array
    {
        return [
            'src' => ['default' => null],
            'title' => ['default' => null],
            'width' => ['default' => null],
            'height' => ['default' => null],
            'loading' => ['default' => null],
            'referrerpolicy' => ['default' => null],
            'allow' => ['default' => null],
            'allowfullscreen' => ['default' => null],
            'sandbox' => ['default' => null],
            'class' => ['default' => null],
            'style' => ['default' => null],
            'scrolling' => ['default' => null],
            'frameborder' => ['default' => null],
        ];
    }

    public function renderHTML($node, $HTMLAttributes = []): array
    {
        return ['iframe', HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes)];
    }
}
