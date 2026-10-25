<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripDocument;
use App\Models\User;
use App\Support\TripNotices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Tickets, bookings and PDFs of a trip, so nobody digs through WhatsApp at the airport.
// Any member adds and opens them; who added one, who it's for and the trip's managers remove it
class TripDocumentController extends Controller
{
    public function store(Request $request, Trip $trip)
    {
        $user = $request->user();
        abort_unless($trip->hasMember($user), 403, 'Unauthorized Action');
        $data = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,heic,heif|max:20480',
            'name' => 'nullable|string|max:150',
            'for' => 'nullable|integer',
        ]);
        $file = $request->file('file');
        $for = isset($data['for']) && $trip->members->contains('id', $data['for']) ? (int) $data['for'] : null;

        $document = $trip->documents()->create([
            'uploaded_by' => $user->id,
            'for_user_id' => $for,
            'name' => trim($data['name'] ?? '') ?: Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 150, ''),
            'path' => $file->store("trip-documents/{$trip->id}", TripDocument::DISK),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        // Someone else's ticket: they get told it's there
        if ($for && $for !== $user->id) {
            TripNotices::send(User::whereKey($for)->get(), $trip, fn () => [
                __(':name added ":document" for you', ['name' => $user->name, 'document' => $document->name]),
                __('It\'s in the documents of :trip, ready for the trip.', ['trip' => $trip->title]),
            ]);
        }

        return back()->with('message', 'Document added');
    }

    // Opened in the browser (PDF viewer, photo), only for the trip's members
    public function show(Request $request, Trip $trip, TripDocument $document)
    {
        abort_unless($trip->hasMember($request->user()), 403, 'Unauthorized Action');
        $extension = pathinfo($document->path, PATHINFO_EXTENSION);

        return response()->file(Storage::disk(TripDocument::DISK)->path($document->path), [
            'Content-Type' => $document->mime,
            'Content-Disposition' => 'inline; filename="' . Str::slug($document->name) . ($extension ? ".{$extension}" : '') . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Request $request, Trip $trip, TripDocument $document)
    {
        $user = $request->user();
        abort_unless($trip->hasMember($user) && (in_array($user->id, [$document->uploaded_by, $document->for_user_id]) || $trip->isManagedBy($user)), 403, 'Unauthorized Action');
        $document->delete();

        return back()->with('message', 'Document removed');
    }
}
