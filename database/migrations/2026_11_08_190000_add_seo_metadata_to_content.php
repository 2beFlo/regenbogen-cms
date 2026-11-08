<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('image_alt')->nullable();
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['seo_title', 'seo_description', 'image_alt']);
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn(['seo_title', 'seo_description']);
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn(['seo_title', 'seo_description']);
        });
    }
};
