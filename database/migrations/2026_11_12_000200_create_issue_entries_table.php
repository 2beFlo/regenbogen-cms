<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('issue_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('content_type')->default('other');
            $table->unsignedSmallInteger('page_start')->nullable();
            $table->decimal('page_offset', 8, 4)->default(0);
            $table->decimal('page_span', 8, 4)->default(1);
            $table->string('placement_type')->default('normal');
            $table->boolean('is_locked')->default(false);
            $table->string('planning_status')->default('planned');
            $table->string('print_status')->default('planned');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->unique(['issue_id', 'article_id']);
            $table->index(['issue_id', 'page_start']);
            $table->index(['issue_id', 'planning_status']);
        });

        Schema::create('issue_entry_author', function (Blueprint $table): void {
            $table->foreignId('issue_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['issue_entry_id', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_entry_author');
        Schema::dropIfExists('issue_entries');
    }
};
