<?php

namespace App\Http\Controllers;

use App\Models\ExpenseGroup;
use App\Models\Trip;
use App\Models\TripDateOption;
use App\Models\TripDocument;
use App\Models\TripItem;
use App\Models\User;
use App\Support\Media;
use App\Support\TripNotices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

// Upcoming trips: planned together (date poll, checklist, shared expenses), then made an album.
// Only the people going see a trip; who created it (and admins) manages it
class TripController extends Controller
{
    public function index(Request $request)
    {
        $trips = $request->user()->trips()->with(['members', 'items', 'options', 'listing'])->get()
            ->map(fn (Trip $trip) => $this->card($trip));

        $active = $trips->whereIn('status', ['planning', 'upcoming', 'ongoing', 'finished']);

        return Inertia::render('Trips/Index', [
            // Soonest first, then the ones still voting dates
            'trips' => $active->sortBy(fn ($trip) => [$trip['dateFrom'] === null ? 1 : 0, $trip['dateFrom']])->values(),
            'done' => $trips->where('status', 'album')->sortByDesc('dateFrom')->values(),
        ]);
    }

    public function show(Request $request, Trip $trip)
    {
        $user = $request->user();
        $trip->load(['members', 'options.voters', 'items.assignee', 'listing', 'expenseGroup', 'creator']);
        abort_unless($trip->hasMember($user), 403, 'Unauthorized Action');
        $managed = $trip->isManagedBy($user);

        return Inertia::render('Trips/Show', [
            'trip' => $this->card($trip) + [
                'notes' => $trip->notes,
                'createdBy' => $trip->creator?->name,
                'album' => $trip->listing ? ['id' => $trip->listing->id, 'title' => $trip->listing->title] : null,
                'expenseGroup' => $trip->expenseGroup ? ['id' => $trip->expenseGroup->id, 'name' => $trip->expenseGroup->name] : null,
            ],
            'me' => $user->id,
            'members' => $trip->members->map(fn (User $member) => $this->person($member)),
            'options' => $trip->options->map(fn (TripDateOption $option) => [
                'id' => $option->id,
                'dateFrom' => $option->date_from->toDateString(),
                'dateTo' => $option->date_to->toDateString(),
                'voters' => $option->voters->pluck('id'),
                'canDelete' => $managed || $option->created_by == $user->id,
            ]),
            'items' => $trip->items->map(fn (TripItem $item) => [
                'id' => $item->id,
                'text' => $item->text,
                'done' => $item->done,
                'assignedTo' => $item->assigned_to,
                'canDelete' => $managed || $item->created_by == $user->id,
            ]),
            // null until its migration runs
            'documents' => Schema::hasTable('trip_documents') ? $trip->documents()->with('uploader')->get()->map(fn (TripDocument $document) => [
                'id' => $document->id,
                'name' => $document->name,
                'image' => str_starts_with($document->mime, 'image/'),
                'size' => $document->size,
                'forUser' => $document->for_user_id,
                'uploadedBy' => $document->uploader?->name,
                'createdAt' => $document->created_at->toIso8601String(),
                'url' => "/trips/{$trip->id}/documents/{$document->id}",
                'canDelete' => $managed || in_array($user->id, [$document->uploaded_by, $document->for_user_id]),
            ]) : null,
            'can' => [
                'manage' => $managed,
                // After the start, who can post albums turns it into one
                'makeAlbum' => ! $trip->listing_id && $trip->date_from && ! $trip->date_from->isFuture() && $user->canPostAlbums(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Trips/Form', $this->formProps($request->user(), null));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validateTrip($request);

        $trip = Trip::create([
            'title' => $data['title'],
            'destination' => $data['destination'] ?? null,
            'notes' => $data['notes'] ?? null,
            'date_from' => $data['dates'] === 'fixed' ? $data['date_from'] : null,
            'date_to' => $data['dates'] === 'fixed' ? $data['date_to'] : null,
            'created_by' => $user->id,
        ]);
        $members = collect($data['members'] ?? [])->push($user->id)->unique();
        $trip->members()->sync($members->all());
        if ($data['dates'] === 'poll') {
            foreach ($data['options'] as $option) {
                $trip->options()->create(['date_from' => $option['from'], 'date_to' => $option['to'], 'created_by' => $user->id]);
            }
        }

        $this->invite($trip, User::findMany($members->reject(fn ($id) => $id == $user->id)), $user);

        return redirect("/trips/{$trip->id}")->with('message', 'Trip created!');
    }

    public function edit(Request $request, Trip $trip)
    {
        abort_unless($trip->isManagedBy($request->user()), 403, 'Unauthorized Action');

        return Inertia::render('Trips/Form', $this->formProps($request->user(), $trip));
    }

    public function update(Request $request, Trip $trip)
    {
        $user = $request->user();
        abort_unless($trip->isManagedBy($user), 403, 'Unauthorized Action');
        $data = $this->validateTrip($request, $trip);

        $before = $trip->members()->pluck('users.id');
        $members = collect($data['members'] ?? [])->push($trip->created_by)->unique();
        $hadDates = (bool) $trip->date_from;

        $trip->update([
            'title' => $data['title'],
            'destination' => $data['destination'] ?? null,
            'notes' => $data['notes'] ?? null,
            'date_from' => $data['dates'] === 'fixed' ? $data['date_from'] : null,
            'date_to' => $data['dates'] === 'fixed' ? $data['date_to'] : null,
        ]);
        $trip->members()->sync($members->all());

        // Who left: their votes and their jobs on the list go too
        $left = $before->diff($members);
        TripDateOption::where('trip_id', $trip->id)->get()->each(fn ($option) => $option->voters()->detach($left));
        $trip->items()->whereIn('assigned_to', $left)->update(['assigned_to' => null]);

        $this->invite($trip, User::findMany($members->diff($before)), $user);
        if (! $hadDates && $trip->date_from) {
            $this->datesDecided($trip, $user);
        }

        return redirect("/trips/{$trip->id}")->with('message', 'Trip saved!');
    }

    public function destroy(Request $request, Trip $trip)
    {
        abort_unless($trip->isManagedBy($request->user()), 403, 'Unauthorized Action');
        $trip->delete();

        return redirect('/trips')->with('message', 'Trip deleted');
    }

    // --- Date poll ---

    public function addOption(Request $request, Trip $trip)
    {
        abort_unless($trip->hasMember($request->user()), 403, 'Unauthorized Action');
        $data = $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);
        $option = $trip->options()->create(['date_from' => $data['from'], 'date_to' => $data['to'], 'created_by' => $request->user()->id]);
        // Who suggests some dates can make them
        $option->voters()->attach($request->user()->id);

        return back();
    }

    public function deleteOption(Request $request, Trip $trip, TripDateOption $option)
    {
        abort_unless($trip->isManagedBy($request->user()) || ($trip->hasMember($request->user()) && $option->created_by == $request->user()->id), 403, 'Unauthorized Action');
        $option->delete();

        return back();
    }

    public function vote(Request $request, Trip $trip, TripDateOption $option)
    {
        abort_unless($trip->hasMember($request->user()), 403, 'Unauthorized Action');
        $option->voters()->toggle([$request->user()->id]);

        return back();
    }

    public function choose(Request $request, Trip $trip, TripDateOption $option)
    {
        abort_unless($trip->isManagedBy($request->user()), 403, 'Unauthorized Action');
        $trip->update(['date_from' => $option->date_from, 'date_to' => $option->date_to]);
        $this->datesDecided($trip, $request->user());

        return back()->with('message', 'Dates decided!');
    }

    // --- Checklist ---

    public function addItem(Request $request, Trip $trip)
    {
        abort_unless($trip->hasMember($request->user()), 403, 'Unauthorized Action');
        $data = $request->validate(['text' => 'required|string|max:255', 'assigned_to' => 'nullable|integer']);
        $trip->items()->create([
            'text' => $data['text'],
            'assigned_to' => $this->memberOrNull($trip, $data['assigned_to'] ?? null),
            'created_by' => $request->user()->id,
        ]);

        return back();
    }

    public function updateItem(Request $request, Trip $trip, TripItem $item)
    {
        abort_unless($trip->hasMember($request->user()), 403, 'Unauthorized Action');
        $data = $request->validate(['done' => 'sometimes|boolean', 'assigned_to' => 'sometimes|nullable|integer']);
        if (array_key_exists('assigned_to', $data)) {
            $data['assigned_to'] = $this->memberOrNull($trip, $data['assigned_to']);
        }
        $item->update($data);

        return back();
    }

    public function deleteItem(Request $request, Trip $trip, TripItem $item)
    {
        abort_unless($trip->isManagedBy($request->user()) || ($trip->hasMember($request->user()) && $item->created_by == $request->user()->id), 403, 'Unauthorized Action');
        $item->delete();

        return back();
    }

    // --- Shared expenses and the album ---

    public function expenses(Request $request, Trip $trip)
    {
        $user = $request->user();
        abort_unless($trip->hasMember($user), 403, 'Unauthorized Action');
        if (! $trip->expense_group_id) {
            $group = ExpenseGroup::create(['name' => $trip->title, 'listing_id' => $trip->listing_id, 'created_by' => $user->id]);
            $group->members()->sync($trip->members()->where('is_demo', false)->pluck('users.id')->all());
            $trip->update(['expense_group_id' => $group->id]);
        }

        return redirect("/expenses/{$trip->expense_group_id}");
    }

    // Album form filled in from the trip (it's linked when the album is saved)
    public function makeAlbum(Request $request, Trip $trip)
    {
        abort_unless($trip->hasMember($request->user()) && $request->user()->canPostAlbums() && ! $trip->listing_id, 403, 'Unauthorized Action');

        return redirect("/listings/create?trip={$trip->id}");
    }

    // --- Helpers ---

    private function invite(Trip $trip, iterable $people, User $by): void
    {
        TripNotices::send($people, $trip, fn () => [
            __(':name invited you to :trip', ['name' => $by->name, 'trip' => $trip->title]),
            ($dates = TripNotices::dates($trip))
                ? __(":dates. See who's going and what to bring.", ['dates' => $dates])
                : __('Vote the dates that work for you and see what to bring.'),
        ]);
    }

    private function datesDecided(Trip $trip, User $by): void
    {
        TripNotices::send($trip->members()->where('users.id', '!=', $by->id)->get(), $trip, fn () => [
            __(':trip: dates decided', ['trip' => $trip->title]),
            __(':dates. Put it in the calendar!', ['dates' => TripNotices::dates($trip)]),
        ]);
    }

    private function memberOrNull(Trip $trip, $userId): ?int
    {
        return $userId && $trip->members()->where('users.id', $userId)->exists() ? (int) $userId : null;
    }

    private function validateTrip(Request $request, ?Trip $trip = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'destination' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'members' => 'array',
            'members.*' => 'integer|exists:users,id',
            'dates' => 'required|in:fixed,poll',
            'date_from' => 'required_if:dates,fixed|nullable|date',
            'date_to' => 'required_if:dates,fixed|nullable|date|after_or_equal:date_from',
            // The poll's options are given when creating; afterwards they're added on the trip page
            'options' => $trip ? 'nullable|array' : 'required_if:dates,poll|array|min:2|max:10',
            'options.*.from' => 'required|date',
            'options.*.to' => 'required|date|after_or_equal:options.*.from',
        ], [
            'options.min' => __('Give at least two date options to vote on.'),
            'options.required_if' => __('Give at least two date options to vote on.'),
        ]);

        if (User::whereIn('id', $data['members'] ?? [])->where('is_demo', true)->exists()) {
            throw ValidationException::withMessages(['members' => __('The demo account can\'t join a trip.')]);
        }

        return $data;
    }

    private function card(Trip $trip): array
    {
        return [
            'id' => $trip->id,
            'title' => $trip->title,
            'destination' => $trip->destination,
            'dateFrom' => $trip->date_from?->toDateString(),
            'dateTo' => $trip->date_to?->toDateString(),
            'dates' => TripNotices::dates($trip),
            'status' => $trip->status(),
            'members' => $trip->members->map(fn (User $member) => $this->person($member)),
            'itemsLeft' => $trip->items->where('done', false)->count(),
            'optionCount' => $trip->options->count(),
            'albumId' => $trip->listing_id,
        ];
    }

    private function formProps(User $user, ?Trip $trip): array
    {
        return [
            'trip' => $trip ? [
                'id' => $trip->id,
                'title' => $trip->title,
                'destination' => $trip->destination,
                'notes' => $trip->notes,
                'dates' => $trip->date_from ? 'fixed' : 'poll',
                'date_from' => $trip->date_from?->toDateString(),
                'date_to' => $trip->date_to?->toDateString(),
                'members' => $trip->members()->pluck('users.id'),
                'optionCount' => $trip->options()->count(),
            ] : null,
            'me' => $user->id,
            'creator' => $trip?->created_by ?? $user->id,
            'users' => User::where('is_demo', false)->orderBy('name')->get()->map(fn ($person) => $this->person($person)),
        ];
    }

    private function person(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'avatar' => Media::url($user->profilePicture)];
    }
}
