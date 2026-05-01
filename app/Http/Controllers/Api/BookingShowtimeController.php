<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class BookingShowtimeController extends Controller
{
    private const DEFAULT_DAYS = 5;
    private const TOTAL_SEATS = 144;
    private const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'cinema_id' => ['required', 'integer'],
            'film_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'between:1,14'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu truy vấn không hợp lệ',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cinemaId = (int) $request->query('cinema_id');
        $filmId = $request->filled('film_id') ? (int) $request->query('film_id') : null;
        $days = (int) $request->query('days', self::DEFAULT_DAYS);
        $now = Carbon::now(self::TIMEZONE);
        $startDate = $request->filled('date_from')
            ? Carbon::createFromFormat('Y-m-d', $request->query('date_from'), self::TIMEZONE)->startOfDay()
            : $now->copy()->startOfDay();
        $endDate = $startDate->copy()->addDays(max($days - 1, 0))->endOfDay();
        $hasTimeDetailStatus = Schema::hasColumn('time_details', 'status');

        $showtimes = DB::table('time_details as td')
            ->join('movie_rooms as mr', 'mr.id', '=', 'td.room_id')
            ->join('cinemas as c', 'c.id', '=', 'mr.id_cinema')
            ->join('films as f', 'f.id', '=', 'td.film_id')
            ->join('times as t', 't.id', '=', 'td.time_id')
            ->leftJoin('film_releases as fr', 'fr.id', '=', 'td.film_release_id')
            ->leftJoin(
                DB::raw(
                    '(SELECT id_time_detail, COUNT(*) as booked_seats FROM movie_chairs WHERE deleted_at IS NULL GROUP BY id_time_detail) as chair_counts'
                ),
                'chair_counts.id_time_detail',
                '=',
                'td.id'
            )
            ->where('c.id', $cinemaId)
            ->whereNull('td.deleted_at')
            ->whereNull('mr.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereNull('f.deleted_at')
            ->whereNull('t.deleted_at')
            ->where('mr.status', 1)
            ->where('c.status', 1)
            ->where('f.status', 1)
            ->whereBetween('td.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where(function ($dateQuery) {
                // If time_detail has a film_release_id, check against the release dates
                $dateQuery->where(function ($sub) {
                    $sub->whereNotNull('td.film_release_id')
                        ->whereColumn('fr.release_date', '<=', 'td.date')
                        ->whereColumn('fr.end_date', '>=', 'td.date');
                })
                // Fallback: check against the film's own dates (backward compatible)
                ->orWhere(function ($sub) {
                    $sub->whereNull('td.film_release_id')
                        ->whereColumn('f.release_date', '<=', 'td.date')
                        ->whereColumn('f.end_date', '>=', 'td.date');
                });
            });

        if ($filmId !== null) {
            $showtimes->where('td.film_id', $filmId);
        }

        if ($hasTimeDetailStatus) {
            $showtimes->where('td.status', 1);
        }

        if ($startDate->lessThanOrEqualTo($now->copy()->startOfDay())) {
            $showtimes->where(function ($timeQuery) use ($now) {
                $timeQuery->whereDate('td.date', '>', $now->toDateString())
                    ->orWhere(function ($todayQuery) use ($now) {
                        $todayQuery->whereDate('td.date', $now->toDateString())
                            ->whereTime('t.time', '>=', $now->format('H:i:s'));
                    });
            });
        }

        $showtimes = $showtimes
            ->select(
                'td.id as show_id',
                'td.film_id',
                'f.name as film_name',
                'f.image as film_image',
                'f.limit_age',
                'c.id as cinema_id',
                'c.name as cinema_name',
                'mr.id as room_id',
                'mr.name as room_name',
                'td.date',
                'td.time_id',
                't.time',
                DB::raw(
                    'CASE WHEN ' . self::TOTAL_SEATS . ' - COALESCE(chair_counts.booked_seats, 0) < 0 THEN 0 ELSE ' .
                    self::TOTAL_SEATS . ' - COALESCE(chair_counts.booked_seats, 0) END as available_seats'
                ),
                DB::raw($hasTimeDetailStatus ? 'td.status as status' : '1 as status')
            )
            ->orderBy('td.date')
            ->orderBy('t.time')
            ->get();

        return response()->json([
            'data' => $showtimes,
        ]);
    }
}
