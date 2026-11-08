<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('rubric_id')->nullable()->after('issue_id')->constrained('rubrics')->nullOnDelete();
        });

        $defaults = [
            'Reportage / Politik',
            'Glosse',
            'Kommentar',
            'In eigener Sache',
            'Interview',
        ];

        $existing = DB::table('articles')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->pluck('category')
            ->all();

        $names = array_values(array_unique(array_merge($defaults, $existing)));

        foreach ($names as $index => $name) {
            $id = DB::table('rubrics')->insertGetId([
                'name' => $name,
                'slug' => Str::slug($name),
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('articles')
                ->where('category', $name)
                ->update(['rubric_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rubric_id');
        });

        Schema::dropIfExists('rubrics');
    }
};
