<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Film;
use App\Models\TimeDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class BugFixesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Category API store requires status (should return 422 instead of 500)
     */
    public function test_category_store_requires_status(): void
    {
        $response = $this->postJson('/api/Category', [
            'name' => 'Hành động',
            'slug' => 'hanh-dong',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    /**
     * Test Category API update requires status (should return 422 instead of 500)
     */
    public function test_category_update_requires_status(): void
    {
        $category = Categories::create([
            'name' => 'Kinh dị',
            'slug' => 'kinh-di',
            'status' => 1,
        ]);

        $response = $this->putJson("/api/Category/{$category->id}", [
            'name' => 'Kinh dị sửa',
            'slug' => 'kinh-di-sua',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    /**
     * Test Film API store allows nullable trailer
     */
    public function test_film_store_allows_nullable_trailer(): void
    {
        $response = $this->postJson('/api/film', [
            'name' => 'Phim Test Không Trailer',
            'slug' => 'phim-test-khong-trailer',
            'image' => 'images/test.jpg',
            'poster' => 'posters/test.jpg',
            'limit_age' => '16',
            'time' => '120',
            'release_date' => '2026-06-08',
            'end_date' => '2026-06-15',
            'description' => 'Mô tả phim test',
            'status' => 1,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('films', [
            'name' => 'Phim Test Không Trailer',
            'trailer' => null,
        ]);
    }

    /**
     * Test time_detail API returns 400 instead of 401 when showtime duplicate
     */
    public function test_time_detail_store_returns_400_on_duplicate(): void
    {
        // Tạo trước một bản ghi trong bảng times để câu lệnh join hoạt động
        DB::table('times')->insert([
            'id' => 2,
            'time' => '10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tạo trước một bản ghi time_detail trực tiếp trong database
        DB::table('time_details')->insert([
            'room_id' => 1,
            'time_id' => 2,
            'date' => '2026-06-10',
            'film_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Gửi request trùng lặp
        $response = $this->postJson('/api/time_detail', [
            'room_id' => 1,
            'time_id' => 2,
            'date' => '2026-06-10',
            'film_id' => 1,
        ]);

        // Phải trả về 400 (Bad Request) thay vì 401
        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    }
}
