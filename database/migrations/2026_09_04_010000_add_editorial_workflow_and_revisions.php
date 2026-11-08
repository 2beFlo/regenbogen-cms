<?php

use App\Models\Article;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->timestamp('review_requested_at')->nullable()->after('published_at');
            $table->foreignId('review_requested_by')->nullable()->after('review_requested_at')->constrained('users')->nullOnDelete();
            $table->text('review_message')->nullable()->after('review_requested_by');
            $table->timestamp('reviewed_at')->nullable()->after('review_message');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('review_feedback')->nullable()->after('reviewed_by');
        });

        Schema::create('article_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('reason', 80)->default('saved');
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['article_id', 'version_number']);
            $table->index(['article_id', 'created_at']);
        });

        // Bestehende Artikel bekommen einen Ausgangspunkt für den Versionsverlauf.
        DB::table('articles')->orderBy('id')->chunkById(100, function ($articles): void {
            foreach ($articles as $article) {
                $authorIds = DB::table('article_author')
                    ->where('article_id', $article->id)
                    ->orderBy('sort_order')
                    ->pluck('author_id')
                    ->values()
                    ->all();

                DB::table('article_revisions')->insert([
                    'article_id' => $article->id,
                    'user_id' => $article->created_by,
                    'version_number' => 1,
                    'reason' => 'Ausgangsstand',
                    'snapshot' => json_encode([
                        'title' => $article->title,
                        'card_title' => $article->card_title,
                        'slug' => $article->slug,
                        'teaser' => $article->teaser,
                        'content' => $article->content,
                        'hero_image' => $article->hero_image,
                        'card_image' => $article->card_image ?? null,
                        'image_notice' => $article->image_notice,
                        'status' => $article->status,
                        'featured' => (bool) $article->featured,
                        'published_at' => $article->published_at,
                        'rubric_id' => $article->rubric_id ?? null,
                        'issue_id' => $article->issue_id,
                        'author_ids' => $authorIds,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $article->updated_at ?? now(),
                    'updated_at' => $article->updated_at ?? now(),
                ]);
            }
        }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('review_requested_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'review_requested_at', 'review_message', 'reviewed_at', 'review_feedback',
            ]);
        });
    }
};
