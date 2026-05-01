<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add the column (nullable for backward compatibility)
        Schema::table('time_details', function (Blueprint $table) {
            $table->unsignedBigInteger('film_release_id')->nullable()->after('film_id');

            $table->foreign('film_release_id')
                  ->references('id')
                  ->on('film_releases')
                  ->onDelete('set null');

            $table->index('film_release_id');
        });

        // Step 2: Backfill — Create a film_release for every existing film
        $now = now();
        $films = DB::table('films')->whereNull('deleted_at')->get();

        foreach ($films as $film) {
            $releaseId = DB::table('film_releases')->insertGetId([
                'film_id' => $film->id,
                'release_date' => $film->release_date,
                'end_date' => $film->end_date,
                'label' => 'Khởi chiếu lần 1',
                'note' => 'Tự động tạo từ dữ liệu phim gốc',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Assign film_release_id to all existing time_details for this film
            DB::table('time_details')
                ->where('film_id', $film->id)
                ->whereNull('film_release_id')
                ->update(['film_release_id' => $releaseId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_details', function (Blueprint $table) {
            $table->dropForeign(['film_release_id']);
            $table->dropIndex(['film_release_id']);
            $table->dropColumn('film_release_id');
        });
    }
};
