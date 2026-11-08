<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('issues', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('pdf_file')->nullable();
            $table->date('published_at')->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->string('format')->default('A5');
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('card_title')->nullable();
            $table->string('slug')->unique();
            $table->string('category')->default('Reportage / Politik');
            $table->text('teaser')->nullable();
            $table->longText('content')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('image_notice')->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('featured')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('article_author', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['article_id', 'author_id']);
        });

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->string('status')->default('published')->index();
            $table->boolean('show_in_navigation')->default(false);
            $table->boolean('show_in_footer')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('site_title')->default('REGENBOGEN');
            $table->string('masthead_kicker')->default('DAS GRÜNE BLATT FÜR DUDERSTADT UND DAS UNTEREICHSFELD');
            $table->string('strapline')->default('Das Magazin der Grünen Untereichsfeld');
            $table->string('footer_claim')->default('HINSCHAUEN. EINORDNEN. EINMISCHEN.');
            $table->string('about_title')->default('HINSCHAUEN. EINORDNEN. EINMISCHEN.');
            $table->text('about_text')->nullable();
            $table->string('about_link_label')->default('Mehr über den REGENBOGEN');
            $table->string('about_link_url')->default('/ueber-uns');
            $table->text('publisher')->nullable();
            $table->text('editorial')->nullable();
            $table->string('contact_label')->nullable();
            $table->string('contact_url')->nullable();
            $table->string('frequency')->default('Unregelmäßig – gedruckt und online.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('article_author');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('authors');
    }
};
