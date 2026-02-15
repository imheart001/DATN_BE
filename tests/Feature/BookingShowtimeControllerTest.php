<?php

namespace Tests\Feature;

use App\Models\Chairs;
use App\Models\Cinemas;
use App\Models\Film;
use App\Models\MovieRoom;
use App\Models\Time;
use App\Models\TimeDetail;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingShowtimeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasColumn('time_details', 'status')) {
            Schema::table('time_details', function (Blueprint $table) {
                $table->tinyInteger('status')->default(1);
            });
        }

        Carbon::setTestNow(Carbon::create(2026, 4, 21, 10, 0, 0, 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_returns_next_five_days_of_booking_showtimes_and_filters_past_slots(): void
    {
        $cinema = Cinemas::factory()->create(['status' => 1]);
        $room = MovieRoom::factory()->create([
            'id_cinema' => $cinema->id,
            'status' => 1,
        ]);
        $film = Film::factory()->create([
            'status' => 1,
            'limit_age' => '18',
            'release_date' => '2026-04-01',
            'end_date' => '2026-05-01',
        ]);

        $pastTime = Time::factory()->create(['time' => '09:00:00']);
        $futureTime = Time::factory()->create(['time' => '10:30:00']);
        $tomorrowTime = Time::factory()->create(['time' => '12:00:00']);
        $outOfRangeTime = Time::factory()->create(['time' => '14:00:00']);

        $pastShow = TimeDetail::create([
            'date' => '2026-04-21',
            'time_id' => $pastTime->id,
            'film_id' => $film->id,
            'room_id' => $room->id,
            'status' => 1,
        ]);
        $futureShow = TimeDetail::create([
            'date' => '2026-04-21',
            'time_id' => $futureTime->id,
            'film_id' => $film->id,
            'room_id' => $room->id,
            'status' => 1,
        ]);
        $tomorrowShow = TimeDetail::create([
            'date' => '2026-04-22',
            'time_id' => $tomorrowTime->id,
            'film_id' => $film->id,
            'room_id' => $room->id,
            'status' => 1,
        ]);
        $outOfRangeShow = TimeDetail::create([
            'date' => '2026-04-27',
            'time_id' => $outOfRangeTime->id,
            'film_id' => $film->id,
            'room_id' => $room->id,
            'status' => 1,
        ]);

        Chairs::factory()->count(3)->create([
            'id_time_detail' => $futureShow->id,
        ]);

        $deletedChair = Chairs::factory()->create([
            'id_time_detail' => $futureShow->id,
        ]);
        $deletedChair->delete();

        $response = $this->getJson("/api/showtimes/booking?cinema_id={$cinema->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.show_id', $futureShow->id)
            ->assertJsonPath('data.0.available_seats', 141)
            ->assertJsonPath('data.1.show_id', $tomorrowShow->id)
            ->assertJsonPath('data.1.available_seats', 144)
            ->assertJsonMissing(['show_id' => $pastShow->id])
            ->assertJsonMissing(['show_id' => $outOfRangeShow->id]);
    }

    public function test_it_filters_by_optional_film_and_excludes_inactive_or_deleted_showtimes(): void
    {
        $cinema = Cinemas::factory()->create(['status' => 1]);
        $inactiveCinema = Cinemas::factory()->create(['status' => 0]);
        $room = MovieRoom::factory()->create([
            'id_cinema' => $cinema->id,
            'status' => 1,
        ]);
        $inactiveRoom = MovieRoom::factory()->create([
            'id_cinema' => $cinema->id,
            'status' => 0,
        ]);
        $roomInInactiveCinema = MovieRoom::factory()->create([
            'id_cinema' => $inactiveCinema->id,
            'status' => 1,
        ]);

        $filmA = Film::factory()->create([
            'status' => 1,
            'limit_age' => '16',
            'release_date' => '2026-04-01',
            'end_date' => '2026-05-01',
        ]);
        $filmB = Film::factory()->create([
            'status' => 1,
            'limit_age' => '13',
            'release_date' => '2026-04-01',
            'end_date' => '2026-05-01',
        ]);
        $inactiveFilm = Film::factory()->create([
            'status' => 0,
            'release_date' => '2026-04-01',
            'end_date' => '2026-05-01',
        ]);

        $timeA = Time::factory()->create(['time' => '11:00:00']);
        $timeB = Time::factory()->create(['time' => '13:00:00']);
        $timeInactive = Time::factory()->create(['time' => '15:00:00']);
        $timeDeleted = Time::factory()->create(['time' => '16:00:00']);

        $activeShowA = TimeDetail::create([
            'date' => '2026-04-22',
            'time_id' => $timeA->id,
            'film_id' => $filmA->id,
            'room_id' => $room->id,
            'status' => 1,
        ]);
        $activeShowB = TimeDetail::create([
            'date' => '2026-04-22',
            'time_id' => $timeB->id,
            'film_id' => $filmB->id,
            'room_id' => $room->id,
            'status' => 1,
        ]);

        TimeDetail::create([
            'date' => '2026-04-22',
            'time_id' => $timeInactive->id,
            'film_id' => $inactiveFilm->id,
            'room_id' => $room->id,
            'status' => 1,
        ]);
        TimeDetail::create([
            'date' => '2026-04-22',
            'time_id' => $timeInactive->id,
            'film_id' => $filmA->id,
            'room_id' => $inactiveRoom->id,
            'status' => 1,
        ]);
        TimeDetail::create([
            'date' => '2026-04-22',
            'time_id' => $timeInactive->id,
            'film_id' => $filmA->id,
            'room_id' => $roomInInactiveCinema->id,
            'status' => 1,
        ]);
        TimeDetail::create([
            'date' => '2026-04-22',
            'time_id' => $timeInactive->id,
            'film_id' => $filmA->id,
            'room_id' => $room->id,
            'status' => 0,
        ]);

        $deletedShow = TimeDetail::create([
            'date' => '2026-04-22',
            'time_id' => $timeDeleted->id,
            'film_id' => $filmA->id,
            'room_id' => $room->id,
            'status' => 1,
        ]);
        $deletedShow->delete();

        $allFilmsResponse = $this->getJson("/api/showtimes/booking?cinema_id={$cinema->id}");

        $allFilmsResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['show_id' => $activeShowA->id, 'film_id' => $filmA->id])
            ->assertJsonFragment(['show_id' => $activeShowB->id, 'film_id' => $filmB->id])
            ->assertJsonMissing(['show_id' => $deletedShow->id]);

        $filteredResponse = $this->getJson("/api/showtimes/booking?cinema_id={$cinema->id}&film_id={$filmA->id}");

        $filteredResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.show_id', $activeShowA->id)
            ->assertJsonPath('data.0.status', 1)
            ->assertJsonMissing(['show_id' => $activeShowB->id]);
    }
}
