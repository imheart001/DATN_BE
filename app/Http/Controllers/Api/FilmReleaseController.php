<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Film;
use App\Models\FilmRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FilmReleaseController extends Controller
{
    /**
     * List all releases for a given film.
     */
    public function index(string $film)
    {
        $filmModel = Film::find($film);
        if (!$filmModel) {
            return response()->json(['message' => 'Không tìm thấy phim'], 404);
        }

        $releases = FilmRelease::where('film_id', $film)
            ->orderBy('release_date', 'desc')
            ->get();

        return response()->json(['data' => $releases]);
    }

    /**
     * Create a new release (re-release) for a film.
     */
    public function store(Request $request, string $film)
    {
        $filmModel = Film::find($film);
        if (!$filmModel) {
            return response()->json(['message' => 'Không tìm thấy phim'], 404);
        }

        $validator = Validator::make($request->all(), [
            'release_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:release_date',
            'label' => 'nullable|string|max:100',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for overlapping releases
        $overlap = FilmRelease::where('film_id', $film)
            ->where(function ($query) use ($request) {
                $query->whereBetween('release_date', [$request->release_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->release_date, $request->end_date])
                      ->orWhere(function ($q) use ($request) {
                          $q->where('release_date', '<=', $request->release_date)
                            ->where('end_date', '>=', $request->end_date);
                      });
            })
            ->whereNull('deleted_at')
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Đợt chiếu bị trùng thời gian với đợt chiếu khác của phim này',
            ], 422);
        }

        $release = FilmRelease::create([
            'film_id' => $film,
            'release_date' => $request->release_date,
            'end_date' => $request->end_date,
            'label' => $request->label,
            'note' => $request->note,
        ]);

        // Update the film's release_date/end_date to match the latest release
        $filmModel->update([
            'release_date' => $request->release_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json([
            'message' => 'Đợt chiếu mới đã được tạo thành công',
            'data' => $release,
        ], 201);
    }

    /**
     * Show a specific release.
     */
    public function show(string $film, string $id)
    {
        $release = FilmRelease::where('film_id', $film)->find($id);

        if (!$release) {
            return response()->json(['message' => 'Không tìm thấy đợt chiếu'], 404);
        }

        return response()->json(['data' => $release]);
    }

    /**
     * Update a release.
     */
    public function update(Request $request, string $film, string $id)
    {
        $release = FilmRelease::where('film_id', $film)->find($id);

        if (!$release) {
            return response()->json(['message' => 'Không tìm thấy đợt chiếu'], 404);
        }

        $validator = Validator::make($request->all(), [
            'release_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:release_date',
            'label' => 'nullable|string|max:100',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for overlapping releases (excluding current release)
        $newReleaseDate = $request->release_date ?? $release->release_date;
        $newEndDate = $request->end_date ?? $release->end_date;

        $overlap = FilmRelease::where('film_id', $film)
            ->where('id', '!=', $id)
            ->where(function ($query) use ($newReleaseDate, $newEndDate) {
                $query->whereBetween('release_date', [$newReleaseDate, $newEndDate])
                      ->orWhereBetween('end_date', [$newReleaseDate, $newEndDate])
                      ->orWhere(function ($q) use ($newReleaseDate, $newEndDate) {
                          $q->where('release_date', '<=', $newReleaseDate)
                            ->where('end_date', '>=', $newEndDate);
                      });
            })
            ->whereNull('deleted_at')
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Đợt chiếu bị trùng thời gian với đợt chiếu khác của phim này',
            ], 422);
        }

        $release->update($request->only(['release_date', 'end_date', 'label', 'note']));

        return response()->json([
            'message' => 'Đợt chiếu đã được cập nhật',
            'data' => $release,
        ]);
    }

    /**
     * Delete a release (soft delete).
     */
    public function destroy(string $film, string $id)
    {
        $release = FilmRelease::where('film_id', $film)->find($id);

        if (!$release) {
            return response()->json(['message' => 'Không tìm thấy đợt chiếu'], 404);
        }

        // Prevent deleting the only release
        $releaseCount = FilmRelease::where('film_id', $film)->whereNull('deleted_at')->count();
        if ($releaseCount <= 1) {
            return response()->json([
                'message' => 'Không thể xóa đợt chiếu duy nhất của phim',
            ], 422);
        }

        $release->delete();

        return response()->json(['message' => 'Đợt chiếu đã được xóa']);
    }
}
