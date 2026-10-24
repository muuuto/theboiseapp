<?php

namespace App\Http\Controllers;

use App\Models\GymExercise;
use App\Models\GymSet;
use App\Models\GymWorkout;
use App\Models\User;
use App\Support\AlbumCover;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

// Gym tracker: workouts (exercises, rows of kg × sets × reps), progress per exercise and personal
// records. Only for the people the admins allow; each person sees only their own workouts,
// except the records they choose to share with the other gym users. The exercises are the same
// for everyone: anyone can add one, the admins manage them
class GymController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->gymUser($request);
        $records = $this->recordsPerWorkout($user);
        $workouts = GymWorkout::where('user_id', $user->id)->with('sets.exercise')
            ->orderByDesc('date')->orderByDesc('id')->paginate(20)
            ->through(fn (GymWorkout $workout) => [
                'id' => $workout->id,
                'date' => $workout->date->toDateString(),
                'focus' => $workout->focus(),
                'notes' => $workout->notes,
                'exercises' => $workout->sets->pluck('exercise')->filter()->unique('id')->map(fn ($exercise) => $exercise->label())->values(),
                'sets' => (int) $workout->sets->sum('sets'),
                'volume' => $workout->volume(),
                'records' => $records[$workout->id] ?? 0,
            ]);

        // The exercises done most, for their progress pages
        $done = GymSet::whereHas('workout', fn ($query) => $query->where('user_id', $user->id))
            ->selectRaw('gym_exercise_id, count(distinct gym_workout_id) as times')->groupBy('gym_exercise_id')
            ->orderByDesc('times')->limit(12)->pluck('times', 'gym_exercise_id');
        $doneExercises = GymExercise::whereIn('id', $done->keys())->get()
            ->map(fn ($exercise) => $this->exerciseRow($exercise) + ['times' => $done[$exercise->id]])
            ->sortByDesc('times')->values();

        $thisMonth = GymWorkout::where('user_id', $user->id)->where('date', '>=', now()->startOfMonth()->toDateString());

        return Inertia::render('Gym/Index', [
            'workouts' => $workouts,
            'done' => $doneExercises,
            'today' => now()->toDateString(),
            'stats' => [
                'month' => (clone $thisMonth)->count(),
                'year' => GymWorkout::where('user_id', $user->id)->whereYear('date', now()->year)->count(),
                'monthVolume' => (int) round(GymSet::whereIn('gym_workout_id', (clone $thisMonth)->select('id'))->sum(DB::raw('COALESCE(weight, 0) * COALESCE(reps, 0) * sets'))),
            ],
        ]);
    }

    // "New workout": today's, ready to fill in. From another workout ("repeat as today's"): its
    // exercises, sets and reps, the kg to write again (that day's show as hints)
    public function store(Request $request)
    {
        $user = $this->gymUser($request);
        $data = $request->validate(['from' => 'nullable|integer']);
        $from = null;
        if (isset($data['from'])) {
            $from = GymWorkout::with('sets')->findOrFail($data['from']);
            $this->own($from, $user);
        }

        $workout = DB::transaction(function () use ($user, $from) {
            $workout = GymWorkout::create([
                'user_id' => $user->id,
                'date' => now()->toDateString(),
                'focus' => $from?->focus,
                'copied_from_id' => $from?->id,
            ]);
            foreach ($from?->sets ?? [] as $set) {
                $workout->sets()->create(['gym_exercise_id' => $set->gym_exercise_id, 'position' => $set->position, 'sets' => $set->sets, 'reps' => $set->reps]);
            }

            return $workout;
        });

        return redirect("/gym/workouts/{$workout->id}");
    }

    // The workout editor (also to look back at an old one)
    public function show(Request $request, GymWorkout $workout)
    {
        $user = $this->gymUser($request);
        $this->own($workout, $user);
        $workout->load('sets.exercise', 'copiedFrom.sets');

        // Before this workout: best weight and the last time, per exercise (for 🏆 and the hints)
        $before = GymSet::with('workout', 'exercise')
            ->whereHas('workout', fn ($query) => $query->where('user_id', $user->id)
                ->where(fn ($query) => $query->where('date', '<', $workout->date)->orWhere(fn ($query) => $query->where('date', $workout->date)->where('id', '<', $workout->id))))
            ->get()->groupBy('gym_exercise_id');

        $lastTime = $before->map(function (Collection $sets) {
            $last = $sets->sortBy([fn ($a, $b) => [$b->workout->date, $b->workout->id] <=> [$a->workout->date, $a->workout->id]])->first();
            $lastRows = $sets->where('gym_workout_id', $last->gym_workout_id)->sortBy('position')->values();

            return ['date' => $last->workout->date->toDateString(), 'rows' => $lastRows->map->row()];
        });
        // Repeated from another day: that day is "last time" for its exercises
        if ($source = $workout->copiedFrom) {
            foreach ($source->sets->groupBy('gym_exercise_id') as $exerciseId => $sets) {
                $lastTime[$exerciseId] = ['date' => $source->date->toDateString(), 'rows' => $sets->map->row()->values()];
            }
        }

        return Inertia::render('Gym/Workout', [
            'workout' => [
                'id' => $workout->id,
                'date' => $workout->date->toDateString(),
                'focus' => $workout->focus, // null: from the exercises
                'notes' => $workout->notes ?? '',
                'exercises' => $workout->sets->groupBy('gym_exercise_id')->map(fn (Collection $sets) => [
                    'exerciseId' => $sets->first()->gym_exercise_id,
                    'rows' => $sets->map->row()->values(),
                ])->values(),
            ],
            'today' => now()->toDateString(),
            'exercises' => $this->exerciseList(),
            'previousBest' => $before->map(fn (Collection $sets) => $sets->max('weight')),
            'lastTime' => $lastTime,
        ]);
    }

    // Saved as people type (JSON), or with the form. Every row is kept, also the ones still empty
    // (a workout repeated from another day starts with its plan)
    public function update(Request $request, GymWorkout $workout)
    {
        $user = $this->gymUser($request);
        $this->own($workout, $user);
        $data = $request->validate([
            'date' => 'required|date',
            'focus' => ['nullable', Rule::in(GymWorkout::FOCUSES)],
            'notes' => 'nullable|string|max:2000',
            'exercises' => 'array|max:40',
            'exercises.*.exerciseId' => ['required', 'integer', Rule::exists('gym_exercises', 'id')],
            'exercises.*.rows' => 'array|max:30',
            'exercises.*.rows.*.weight' => 'nullable|numeric|min:0|max:1000',
            'exercises.*.rows.*.sets' => 'nullable|integer|min:1|max:100',
            'exercises.*.rows.*.reps' => 'nullable|integer|min:0|max:1000',
        ]);

        DB::transaction(function () use ($workout, $data) {
            $workout->update(['date' => $data['date'], 'focus' => $data['focus'] ?? null, 'notes' => $data['notes'] ?? null]);
            $workout->sets()->delete();
            $position = 0;
            foreach ($data['exercises'] ?? [] as $exercise) {
                foreach ($exercise['rows'] ?? [] as $row) {
                    $workout->sets()->create([
                        'gym_exercise_id' => $exercise['exerciseId'],
                        'position' => $position++,
                        'weight' => $row['weight'] ?? null ?: null,
                        'sets' => $row['sets'] ?? 1,
                        'reps' => $row['reps'] ?? null ?: null,
                    ]);
                }
            }
        });

        return $request->wantsJson() ? response()->json(['saved' => true]) : back()->with('message', 'Workout saved!');
    }

    public function destroy(Request $request, GymWorkout $workout)
    {
        $this->own($workout, $this->gymUser($request));
        $workout->delete();

        return redirect('/gym')->with('message', 'Workout deleted');
    }

    // A new exercise, added from a workout (JSON) or by an admin on the exercises page (with the
    // Italian name and the defaults too): everyone sees it
    public function storeExercise(Request $request)
    {
        $user = $request->user();
        $admin = $user->can('admin');
        abort_unless($user->canUseGym() || $admin, 403, 'Unauthorized Action');
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'group' => ['nullable', Rule::in(GymExercise::GROUPS)],
            'photo' => 'nullable|image|max:20480',
            'nameIt' => 'nullable|string|max:100',
            'defaultSets' => 'nullable|integer|min:1|max:20',
            'defaultReps' => 'nullable|integer|min:1|max:100',
        ]);
        $name = trim($data['name']);
        // Already there (in English or Italian): that one, not a copy
        $existing = GymExercise::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->orWhereRaw('LOWER(name_it) = ?', [mb_strtolower($name)])->first();
        $exercise = $existing ?? GymExercise::create([
            'name' => $name,
            'name_it' => $admin ? (trim($data['nameIt'] ?? '') ?: null) : null,
            'muscle_group' => $data['group'] ?? null,
            'default_sets' => $admin ? ($data['defaultSets'] ?? null) : null,
            'default_reps' => $admin ? ($data['defaultReps'] ?? null) : null,
            'user_id' => $user->id,
            'photo' => $request->hasFile('photo') ? AlbumCover::store($request->file('photo'), 'exercises', 1000) : null,
        ]);

        return $request->wantsJson()
            ? response()->json($this->exerciseRow($exercise))
            : back()->with('message', $existing ? 'This exercise is already in the list' : 'Exercise added');
    }

    // One exercise over time: chart, records, history
    public function exercise(Request $request, GymExercise $exercise)
    {
        $user = $this->gymUser($request);

        $sets = GymSet::with('workout')->where('gym_exercise_id', $exercise->id)
            ->whereHas('workout', fn ($query) => $query->where('user_id', $user->id))
            ->get()->sortBy(fn ($set) => [$set->workout->date, $set->workout->id, $set->position]);

        $days = $sets->groupBy('gym_workout_id')->map(fn (Collection $daySets) => [
            'workoutId' => $daySets->first()->gym_workout_id,
            'date' => $daySets->first()->workout->date->toDateString(),
            'rows' => $daySets->map->row()->values(),
            'best' => $daySets->max('weight'),
            'oneRepMax' => $daySets->map(fn ($set) => GymSet::oneRepMax($set->weight, $set->reps))->max(),
            'reps' => $daySets->max('reps'),
        ])->values();

        $heaviest = $sets->whereNotNull('weight')->sortBy([['weight', 'desc'], ['reps', 'desc']])->first();
        $bestMax = $sets->sortByDesc(fn ($set) => GymSet::oneRepMax($set->weight, $set->reps) ?? 0)->first();
        $mostReps = $sets->whereNotNull('reps')->sortByDesc('reps')->first();

        return Inertia::render('Gym/Exercise', [
            'exercise' => $this->exerciseRow($exercise),
            'days' => $days->reverse()->values(),
            'chart' => $days->map(fn ($day) => ['date' => $day['date'], 'best' => $day['best'], 'oneRepMax' => $day['oneRepMax'], 'reps' => $day['reps']])->values(),
            'records' => $sets->isEmpty() ? null : [
                'heaviest' => $heaviest ? ['weight' => $heaviest->weight, 'reps' => $heaviest->reps, 'date' => $heaviest->workout->date->toDateString()] : null,
                'oneRepMax' => ($value = GymSet::oneRepMax($bestMax->weight, $bestMax->reps)) ? ['value' => $value, 'date' => $bestMax->workout->date->toDateString()] : null,
                'mostReps' => $mostReps ? ['reps' => $mostReps->reps, 'weight' => $mostReps->weight, 'date' => $mostReps->workout->date->toDateString()] : null,
            ],
        ]);
    }

    // The records of the gym users who share them
    public function records(Request $request)
    {
        $user = $this->gymUser($request);
        $people = User::where('can_use_gym', true)->where('gym_share_records', true)->where('is_demo', false)->orderBy('name')->get();

        $best = GymSet::query()
            ->join('gym_workouts', 'gym_workouts.id', '=', 'gym_sets.gym_workout_id')
            ->whereIn('gym_workouts.user_id', $people->pluck('id'))
            ->whereNotNull('gym_sets.weight')
            ->get(['gym_workouts.user_id', 'gym_sets.gym_exercise_id', 'gym_sets.weight', 'gym_sets.reps'])
            ->groupBy('user_id');
        $names = GymExercise::all()->keyBy('id');

        return Inertia::render('Gym/Records', [
            'sharing' => (bool) $user->gym_share_records,
            'people' => $people->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'avatar' => Media::url($person->profilePicture),
                'me' => $person->id === $user->id,
                // Their strongest lifts first (by estimated one-rep max, the heaviest without reps)
                'lifts' => ($best[$person->id] ?? collect())->groupBy('gym_exercise_id')->map(function (Collection $sets, $exerciseId) use ($names) {
                    $top = $sets->sortByDesc(fn ($set) => GymSet::oneRepMax((float) $set->weight, $set->reps))->first();

                    return [
                        'exercise' => $names[$exerciseId]?->label(),
                        'exerciseId' => (int) $exerciseId,
                        'weight' => (float) $sets->max('weight'),
                        'oneRepMax' => GymSet::oneRepMax((float) $top->weight, $top->reps),
                    ];
                })->sortByDesc(fn ($lift) => $lift['oneRepMax'] ?? $lift['weight'])->take(8)->values(),
            ]),
        ]);
    }

    public function share(Request $request)
    {
        $user = $this->gymUser($request);
        $data = $request->validate(['on' => 'required|boolean']);
        $user->update(['gym_share_records' => $data['on']]);

        return back()->with('message', $data['on'] ? 'Your records are shared with the other gym users' : 'Your records are private');
    }

    // --- Managing the exercises (admins) ---

    public function exercises()
    {
        $uses = GymSet::selectRaw('gym_exercise_id, count(distinct gym_workout_id) as times')->groupBy('gym_exercise_id')->pluck('times', 'gym_exercise_id');

        return Inertia::render('Gym/Exercises', [
            'exercises' => GymExercise::with('user')->get()
                ->map(fn (GymExercise $exercise) => $this->exerciseRow($exercise) + [
                    'name' => $exercise->name,
                    'nameIt' => $exercise->name_it,
                    'addedBy' => $exercise->user?->name,
                    'uploadedPhoto' => (bool) $exercise->photo,
                    'readyPhotos' => (bool) $exercise->image,
                    'uses' => (int) ($uses[$exercise->id] ?? 0),
                ])
                ->sortBy(fn ($exercise) => [$this->groupOrder($exercise['group']), mb_strtolower($exercise['label'])])->values(),
            'groups' => GymExercise::GROUPS,
        ]);
    }

    public function updateExercise(Request $request, GymExercise $exercise)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'nameIt' => 'nullable|string|max:100',
            'group' => ['nullable', Rule::in(GymExercise::GROUPS)],
            'defaultSets' => 'nullable|integer|min:1|max:20',
            'defaultReps' => 'nullable|integer|min:1|max:100',
        ]);
        $exercise->update([
            'name' => trim($data['name']),
            'name_it' => trim($data['nameIt'] ?? '') ?: null,
            'muscle_group' => $data['group'] ?? null,
            'default_sets' => $data['defaultSets'] ?? null,
            'default_reps' => $data['defaultReps'] ?? null,
        ]);

        return back()->with('message', 'Exercise saved');
    }

    // A photo of how it's done (for the ready list it takes the place of its two photos)
    public function exercisePhoto(Request $request, GymExercise $exercise)
    {
        $request->validate(['photo' => 'required|image|max:20480']);
        $exercise->update(['photo' => AlbumCover::store($request->file('photo'), 'exercises', 1000)]);

        return back()->with('message', 'Photo saved');
    }

    public function removeExercisePhoto(GymExercise $exercise)
    {
        $exercise->update(['photo' => null]);

        return back()->with('message', 'Photo removed');
    }

    // Only one nobody has used: deleting it would delete those rows from people's workouts
    public function destroyExercise(GymExercise $exercise)
    {
        abort_if(GymSet::where('gym_exercise_id', $exercise->id)->exists(), 422, 'This exercise is in some workouts: it can\'t be deleted');
        $exercise->delete();

        return back()->with('message', 'Exercise deleted');
    }

    // --- Helpers ---

    private function gymUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->canUseGym(), 403, 'Unauthorized Action');

        return $user;
    }

    private function own(GymWorkout $workout, User $user): void
    {
        abort_unless($workout->user_id === $user->id, 404);
    }

    private function exerciseList(): Collection
    {
        return GymExercise::all()->map(fn ($exercise) => $this->exerciseRow($exercise))
            ->sortBy(fn ($exercise) => [$this->groupOrder($exercise['group']), mb_strtolower($exercise['label'])])
            ->values();
    }

    private function groupOrder(?string $group): int
    {
        $index = array_search($group, GymExercise::GROUPS);

        return $index === false ? 99 : $index;
    }

    private function exerciseRow(GymExercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'label' => $exercise->label(),
            'group' => $exercise->muscle_group,
            'photos' => $exercise->photos(),
            'defaultSets' => $exercise->default_sets,
            'defaultReps' => $exercise->default_reps,
        ];
    }

    // Workout id → how many exercises beat their personal record in it (heavier than in every
    // earlier workout; the first time isn't a record yet)
    private function recordsPerWorkout(User $user): array
    {
        $best = [];
        $records = [];
        $workouts = GymSet::with('workout')->whereHas('workout', fn ($query) => $query->where('user_id', $user->id))->whereNotNull('weight')->get()
            ->sortBy(fn ($set) => [$set->workout->date, $set->workout->id])->groupBy('gym_workout_id');
        foreach ($workouts as $workoutId => $sets) {
            foreach ($sets->groupBy('gym_exercise_id') as $exerciseId => $exerciseSets) {
                $heaviest = $exerciseSets->max('weight');
                if (isset($best[$exerciseId]) && $heaviest > $best[$exerciseId]) {
                    $records[$workoutId] = ($records[$workoutId] ?? 0) + 1;
                }
                $best[$exerciseId] = max($best[$exerciseId] ?? 0, $heaviest);
            }
        }

        return $records;
    }
}
