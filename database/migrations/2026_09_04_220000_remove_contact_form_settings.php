<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('site_settings', 'contact_recipient') ? 'contact_recipient' : null,
            Schema::hasColumn('site_settings', 'contact_form_intro') ? 'contact_form_intro' : null,
        ]));

        if ($columns !== []) {
            Schema::table('site_settings', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('contact_recipient')->nullable()->after('contact_url');
            $table->text('contact_form_intro')->nullable()->after('contact_recipient');
        });
    }
};
