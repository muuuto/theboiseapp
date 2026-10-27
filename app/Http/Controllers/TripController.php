<?php

namespace App\Http\Controllers;

use App\Models\ExpenseGroup;
use App\Models\Trip;
use App\Models\TripDateOption;
use App\Models\TripDocument;
use App\Models\TripPoll;
use App\Models\TripPollOption;
use App\Support\Weather;
use App\Models\TripItem;
use App\Models\User;
use App\Support\Media;
use App\Support\TripNotices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

// Upcoming trips: planned together (date poll, checklist, polls, shared expenses), then made an album.
// Events (a dinner, a party) are planned the same way but are one day, have RSVP and never become
// an album. Only the people invited see one; who created it (and admins) manages it
class TripController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $all = $user->trips()->with(['members', 'items', 'options', 'listing'])->get()
            ->map(fn (Trip $trip) => $this->card($trip, $user));
        $trips = $all->where('kind', 'trip');
        $events = $all->where('kind', 'event');
        // Soonest first, then the ones still voting dates
        $soonest = fn ($cards) => $cards->sortBy(fn ($card) => [$card['dateFrom'] === null ? 1 : 0, $card['dateFrom'], $card['time']])->values();

        return Inertia::render('Trips/Index', [
            'trips' => $soonest($trips->whereIn('status', ['planning', 'upcoming', 'ongoing', 'finished'])),
            'done' => $trips->where('status', 'album')->sortByDesc('dateFrom')->values(),
            'events' => $soonest($events->whereIn('status', ['planning', 'today', 'upcoming'])),
            'pastEvents' => $events->where('status', 'past')->sortByDesc('dateFrom')->take(10)->values(),
        ]);
    }

    public function show(Request $request, Trip $trip)
    {
        $user = $request->user();
        $trip->load(['members', 'options.voters', 'items.assignee', 'listing', 'expenseGroup', 'creator']);
        abort_unless($trip->hasMember($user), 403, 'Unauthorized Action');
        $managed = $trip->isManagedBy($user);

        return Inertia::render('Trips/Show', [
            // Polls (null until their migration runs)
            'polls' => Schema::hasTable('trip_polls') ? $trip->polls()->with('options.voters')->get()->map(fn (TripPoll $poll) => [
                'id' => $poll->id,
                'question' => $poll->question,
                'options' => $poll->options->map(fn (TripPollOption $option) => ['id' => $option->id, 'text' => $option->text, 'voters' => $option->voters->pluck('id')]),
                'canDelete' => $managed || $poll->created_by == $user->id,
            ]) : null,
            // The forecast, once it's within the next two weeks
            'weather' => in_array($trip->status(), ['upcoming', 'ongoing', 'today']) ? Weather::forTrip($trip) : null,
            'trip' => $this->card($trip, $user) + [
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
                'makeAlbum' => ! $trip->isEvent() && ! $trip->listing_id && $trip->date_from && ! $trip->date_from->isFuture() && $user->canPostAlbums(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Trips/Form', $this->formProps($request->user(), null) + ['kind' => $request->query('kind') === 'event' ? 'event' : 'trip']);
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
        ] + $this->eventFields($data));
        $members = collect($data['members'] ?? [])->push($user->id)->unique();
        $trip->members()->sync($members->all());
        // Who organizes an event is in
        if ($trip->isEvent()) {
            $trip->members()->updateExistingPivot($user->id, ['rsvp' => 'yes']);
        }
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
        ] + $this->eventFields($data, $trip));
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

    // --- Events: I'm in / maybe / no ---

    public function rsvp(Request $request, Trip $trip)
    {
        $user = $request->user();
        abort_unless($trip->isEvent() && $trip->hasMember($user), 403, 'Unauthorized Action');
        $data = $request->validate(['answer' => 'required|in:yes,maybe,no']);
        $trip->members()->updateExistingPivot($user->id, ['rsvp' => $data['answer']]);

        return back();
    }

    // --- Polls: "Pizza or sushi?" (trips and events) ---

    public function addPoll(Request $request, Trip $trip)
    {
        abort_unless($trip->hasMember($request->user()), 403, 'Unauthorized Action');
        // Empty rows of the form don't count
        $request->merge(['options' => collect($request->input('options', []))->map(fn ($text) => trim((string) $text))->filter()->values()->all()]);
        $data = $request->validate([
            'question' => 'required|string|max:200',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:100',
        ], ['options.min' => __('Give at least two options.')]);
        $poll = $trip->polls()->create(['question' => $data['question'], 'created_by' => $request->user()->id]);
        foreach (collect($data['options'])->map(fn ($text) => trim($text))->filter()->unique() as $text) {
            $poll->options()->create(['text' => $text]);
        }

        return back();
    }

    public function votePoll(Request $request, Trip $trip, TripPoll $poll, TripPollOption $option)
    {
        abort_unless($trip->hasMember($request->user()) && $option->trip_poll_id === $poll->id, 403, 'Unauthorized Action');
        $option->voters()->toggle([$request->user()->id]);

        return back();
    }

    public function deletePoll(Request $request, Trip $trip, TripPoll $poll)
    {
        abort_unless($trip->isManagedBy($request->user()) || ($trip->hasMember($request->user()) && $poll->created_by == $request->user()->id), 403, 'Unauthorized Action');
        $poll->delete();

        return back();
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
            match (true) {
                $trip->isEvent() && (bool) TripNotices::dates($trip) => __(':dates. Are you in?', ['dates' => TripNotices::when($trip)]),
                $trip->isEvent() => __('Vote the dates that work for you, and say if you\'re in.'),
                (bool) ($dates = TripNotices::dates($trip)) => __(":dates. See who's going and what to bring.", ['dates' => $dates]),
                default => __('Vote the dates that work for you and see what to bring.'),
            },
        ]);
    }

    // An event: kind and time (an event is one day: its end is its start). Nothing before the migration
    private function eventFields(array $data, ?Trip $trip = null): array
    {
        if (! Trip::hasEvents()) {
            return [];
        }
        $isEvent = ($trip?->kind ?? $data['kind'] ?? 'trip') === 'event';

        return ['kind' => $isEvent ? 'event' : 'trip', 'start_time' => $isEvent ? ($data['start_time'] ?? null) : null]
            + ($isEvent && $data['dates'] === 'fixed' ? ['date_to' => $data['date_from']] : []);
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
        // An event's options are single days: the end is the start
        if ($request->input('kind', $trip?->kind) === 'event') {
            $request->merge([
                'date_to' => $request->input('date_from'),
                'options' => collect($request->input('options', []))->map(fn ($option) => ['from' => $option['from'] ?? null, 'to' => $option['from'] ?? null])->all(),
            ]);
        }
        $data = $request->validate([
            'kind' => 'nullable|in:trip,event',
            'start_time' => 'nullable|date_format:H:i',
            'title' => 'required|string|max:100',
            'destination' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'members' => 'array',
            'members.*' => 'integer|exists:users,id',
            'dates' => 'required|in:fixed,poll',
            'date_from' => 'required_if:dates,fixed|nullable|date',
            'date_to' => 'required_if:dates,fixed|nullable|date|after_or_equal:date_from',
            // The poll's options are given when creating; afterwards they're added on the trip page.
            // With fixed dates they're ignored (the form still sends its empty poll rows)
            'options' => $trip ? 'exclude_unless:dates,poll|nullable|array' : 'exclude_unless:dates,poll|required|array|min:2|max:10',
            'options.*.from' => 'exclude_unless:dates,poll|required|date',
            'options.*.to' => 'exclude_unless:dates,poll|required|date|after_or_equal:options.*.from',
        ], [
            'options.min' => __('Give at least two date options to vote on.'),
            'options.required' => __('Give at least two date options to vote on.'),
        ]);

        if (User::whereIn('id', $data['members'] ?? [])->where('is_demo', true)->exists()) {
            throw ValidationException::withMessages(['members' => __('The demo account can\'t join a trip.')]);
        }

        return $data;
    }

    private function card(Trip $trip, ?User $viewer = null): array
    {
        $rsvp = fn ($answer) => $trip->members->filter(fn ($member) => ($member->pivot->rsvp ?? null) === $answer)->count();

        return [
            'id' => $trip->id,
            'kind' => $trip->kind ?? 'trip',
            'time' => $trip->start_time ? substr($trip->start_time, 0, 5) : null,
            // Events: how many are in, and my answer
            'goingCount' => $trip->isEvent() ? $rsvp('yes') : null,
            'myRsvp' => $viewer ? ($trip->members->firstWhere('id', $viewer->id)?->pivot->rsvp ?? null) : null,
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
                'kind' => $trip->kind ?? 'trip',
                'start_time' => $trip->start_time ? substr($trip->start_time, 0, 5) : '',
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
        return ['id' => $user->id, 'name' => $user->name, 'avatar' => Media::url($user->profilePicture), 'rsvp' => $user->pivot->rsvp ?? null];
    }
}
