<?php

return [
    'profiles' => [
        'regenbogen' => [
            'plugins' => 'autoresize advlist autolink link lists charmap preview searchreplace wordcount visualblocks code fullscreen table',
            'toolbar' => 'undo redo | styles | bold italic underline | bullist numlist blockquote | link table | searchreplace visualblocks | code fullscreen',
            'upload_directory' => null,
            'custom_configs' => [
                'menubar' => false,
                'branding' => false,
                'promotion' => false,
                'statusbar' => true,
                'resize' => true,
                'min_height' => 620,
                'content_style' => 'body { font-family: Georgia, "Times New Roman", serif; font-size: 18px; line-height: 1.7; max-width: none; padding: 24px 28px; } p { margin: 0 0 1em; }',
            ],
        ],
    ],
];
