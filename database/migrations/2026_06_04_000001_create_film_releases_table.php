<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('film_releases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('film_id');
            $table->date('release_date');
            $table->date('end_date');
            $table->string('label', 100)->nullable(); // "Khởi chiếu lần 1", "Re-release", etc.
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('film_id')
                  ->references('id')
                  ->on('films')
                  ->onDelete('cascade');

            $table->index(['film_id', 'release_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('film_releases');
    }
};
