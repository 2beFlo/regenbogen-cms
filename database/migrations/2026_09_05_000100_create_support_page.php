<?php

use App\Models\CmsPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        CmsPage::query()->firstOrCreate(
            ['slug' => 'unterstuetze-uns'],
            [
                'title' => 'Unterstütze uns',
                'content' => '<p>Der REGENBOGEN lebt von Menschen, die hinschauen, einordnen und sich einmischen. Wenn du unsere Arbeit unterstützen möchtest, kannst du hier direkt spenden.</p>',
                'status' => 'published',
                'show_in_navigation' => true,
                'show_in_footer' => true,
                'sort_order' => 25,
            ],
        );
    }

    public function down(): void
    {
        CmsPage::query()
            ->where('slug', 'unterstuetze-uns')
            ->where('content', '<p>Der REGENBOGEN lebt von Menschen, die hinschauen, einordnen und sich einmischen. Wenn du unsere Arbeit unterstützen möchtest, kannst du hier direkt spenden.</p>')
            ->delete();
    }
};
