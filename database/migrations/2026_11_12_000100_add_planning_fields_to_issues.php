<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->string('issue_code')->nullable()->unique();
            $table->string('work_title')->nullable();
            $table->date('planned_publication_date')->nullable();
            $table->date('editorial_deadline')->nullable();
            $table->date('print_date')->nullable();
            $table->string('production_status')->default('planning')->index();
            $table->text('planning_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropIndex(['production_status']);
            $table->dropUnique(['issue_code']);
            $table->dropColumn([
                'issue_code', 'work_title', 'planned_publication_date', 'editorial_deadline',
                'print_date', 'production_status', 'planning_notes',
            ]);
        });
    }
};
