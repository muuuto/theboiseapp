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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

// Gym tracker: workouts (exercises, sets of kg × reps), progress per exercise and personal
// records. Only for the people the admins allow; each person sees only their own data,
// except the records they choose to share with the other gym users
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
                'notes' => $workout->notes,
                'exercises' => $workout->sets->pluck('exercise')->filter()->unique('id')->map(fn ($exercise) => $exercise->label())->values(),
                'sets' => $workout->sets->count(),
                'volume' => (int) round($workout->sets->sum(fn ($set) => ($set->weight ?? 0) * $set->reps)),
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
            'stats' => [
                'month' => (clone $thisMonth)->count(),
                'year' => GymWorkout::where('user_id', $user->id)->whereYear('date', now()->year)->count(),
                'monthVolume' => (int) round(GymSet::whereIn('gym_workout_id', (clone $thisMonth)->select('id'))->sum(DB::raw('COALESCE(weight, 0) * reps'))),
            ],
        ]);
    }

    // "New workout": today's, ready to fill in
    public function store(Request $request)
    {
        $user = $this->gymUser($request);
        $workout = GymWorkout::create(['user_id' => $user->id, 'date' => now()->toDateString()]);

        return redirect("/gym/workouts/{$workout->id}");
    }

    // The workout editor (also to look back at an old one)
    public function show(Request $request, GymWorkout $workout)
    {
        $user = $this->gymUser($request);
        $this->own($workout, $user);
        $workout->load('sets.exercise');

        // Before this workout: best weight and the last time, per exercise (for 🏆 and the hints)
        $before = GymSet::with('workout', 'exercise')
            ->whereHas('workout', fn ($query) => $query->where('user_id', $user->id)
                ->where(fn ($query) => $query->where('date', '<', $workout->date)->orWhere(fn ($query) => $query->where('date', $workout->date)->where('id', '<', $workout->id))))
            ->get()->groupBy('gym_exercise_id');

        return Inertia::render('Gym/Workout', [
            'workout' => [
                'id' => $workout->id,
                'date' => $workout->date->toDateString(),
                'notes' => $workout->notes ?? '',
                'exercises' => $workout->sets->groupBy('gym_exercise_id')->map(fn (Collection $sets) => [
                    'exerciseId' => $sets->first()->gym_exercise_id,
                    'sets' => $sets->map(fn ($set) => ['weight' => $set->weight, 'reps' => $set->reps])->values(),
                ])->values(),
            ],
            'exercises' => $this->exerciseList($user),
            'previousBest' => $before->map(fn (Collection $sets) => $sets->max('weight')),
            'lastTime' => $before->map(function (Collection $sets) {
                $last = $sets->sortBy([fn ($a, $b) => [$b->workout->date, $b->workout->id] <=> [$a->workout->date, $a->workout->id]])->first();
                $lastSets = $sets->where('gym_workout_id', $last->gym_workout_id)->sortBy('position')->values();

                return ['date' => $last->workout->date->toDateString(), 'sets' => $lastSets->map(fn ($set) => ['weight' => $set->weight, 'reps' => $set->reps])];
            }),
        ]);
    }

    // Saved as people type (JSON), or with the form
    public function update(Request $request, GymWorkout $workout)
    {
        $user = $this->gymUser($request);
        $this->own($workout, $user);
        $visible = GymExercise::for($user)->pluck('id')->all();
        $data = $request->validate([
            'date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'exercises' => 'array|max:40',
            'exercises.*.exerciseId' => ['required', 'integer', Rule::in($visible)],
            'exercises.*.sets' => 'array|max:30',
            'exercises.*.sets.*.weight' => 'nullable|numeric|min:0|max:1000',
            'exercises.*.sets.*.reps' => 'nullable|integer|min:0|max:1000',
        ]);

        DB::transaction(function () use ($workout, $data) {
            $workout->update(['date' => $data['date'], 'notes' => $data['notes'] ?? null]);
            $workout->sets()->delete();
            $position = 0;
            foreach ($data['exercises'] ?? [] as $exercise) {
                foreach ($exercise['sets'] ?? [] as $set) {
                    // A set without reps isn't done yet: not saved
                    if (empty($set['reps'])) {
                        continue;
                    }
                    $workout->sets()->create([
                        'gym_exercise_id' => $exercise['exerciseId'],
                        'position' => $position++,
                        'weight' => $set['weight'] !== null && $set['weight'] !== '' ? $set['weight'] : null,
                        'reps' => $set['reps'],
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

    // Someone's own exercise (only they see it)
    public function storeExercise(Request $request)
    {
        $user = $this->gymUser($request);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'group' => ['nullable', Rule::in(GymExercise::GROUPS)],
            'photo' => 'nullable|image|max:20480',
        ]);
        $name = trim($data['name']);
        $existing = GymExercise::for($user)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        $exercise = $existing ?? GymExercise::create([
            'name' => $name,
            'muscle_group' => $data['group'] ?? null,
            'user_id' => $user->id,
            'photo' => $request->hasFile('photo') ? AlbumCover::store($request->file('photo'), 'exercises', 1000) : null,
        ]);

        return response()->json($this->exerciseRow($exercise));
    }

    // A new photo for someone's own exercise
    public function exercisePhoto(Request $request, GymExercise $exercise)
    {
        $user = $this->gymUser($request);
        abort_unless($exercise->user_id === $user->id, 404);
        $request->validate(['photo' => 'required|image|max:20480']);
        $exercise->update(['photo' => AlbumCover::store($request->file('photo'), 'exercises', 1000)]);

        return back()->with('message', 'Photo saved');
    }

    // One exercise over time: chart, records, history
    public function exercise(Request $request, GymExercise $exercise)
    {
        $user = $this->gymUser($request);
        abort_unless(! $exercise->isCustom() || $exercise->user_id === $user->id, 404);

        $sets = GymSet::with('workout')->where('gym_exercise_id', $exercise->id)
            ->whereHas('workout', fn ($query) => $query->where('user_id', $user->id))
            ->get()->sortBy(fn ($set) => [$set->workout->date, $set->workout->id, $set->position]);

        $days = $sets->groupBy('gym_workout_id')->map(fn (Collection $daySets) => [
            'workoutId' => $daySets->first()->gym_workout_id,
            'date' => $daySets->first()->workout->date->toDateString(),
            'sets' => $daySets->map(fn ($set) => ['weight' => $set->weight, 'reps' => $set->reps])->values(),
            'best' => $daySets->max('weight'),
            'oneRepMax' => $daySets->map(fn ($set) => GymSet::oneRepMax($set->weight, $set->reps))->max(),
            'reps' => $daySets->max('reps'),
        ])->values();

        $heaviest = $sets->sortBy([['weight', 'desc'], ['reps', 'desc']])->first();
        $bestMax = $sets->sortByDesc(fn ($set) => GymSet::oneRepMax($set->weight, $set->reps) ?? 0)->first();
        $mostReps = $sets->sortByDesc('reps')->first();

        return Inertia::render('Gym/Exercise', [
            'exercise' => $this->exerciseRow($exercise),
            'days' => $days->reverse()->values(),
            'chart' => $days->map(fn ($day) => ['date' => $day['date'], 'best' => $day['best'], 'oneRepMax' => $day['oneRepMax'], 'reps' => $day['reps']])->values(),
            'records' => $sets->isEmpty() ? null : [
                'heaviest' => $heaviest?->weight ? ['weight' => $heaviest->weight, 'reps' => $heaviest->reps, 'date' => $heaviest->workout->date->toDateString()] : null,
                'oneRepMax' => ($value = GymSet::oneRepMax($bestMax->weight, $bestMax->reps)) ? ['value' => $value, 'date' => $bestMax->workout->date->toDateString()] : null,
                'mostReps' => ['reps' => $mostReps->reps, 'weight' => $mostReps->weight, 'date' => $mostReps->workout->date->toDateString()],
            ],
        ]);
    }

    // The records of the gym users who share them (the ready-list exercises: the same for everyone)
    public function records(Request $request)
    {
        $user = $this->gymUser($request);
        $people = User::where('can_use_gym', true)->where('gym_share_records', true)->where('is_demo', false)->orderBy('name')->get();

        $best = GymSet::query()
            ->join('gym_workouts', 'gym_workouts.id', '=', 'gym_sets.gym_workout_id')
            ->join('gym_exercises', 'gym_exercises.id', '=', 'gym_sets.gym_exercise_id')
            ->whereNull('gym_exercises.user_id')
            ->whereIn('gym_workouts.user_id', $people->pluck('id'))
            ->whereNotNull('gym_sets.weight')
            ->get(['gym_workouts.user_id', 'gym_sets.gym_exercise_id', 'gym_sets.weight', 'gym_sets.reps'])
            ->groupBy('user_id');
        $names = GymExercise::whereNull('user_id')->get()->keyBy('id');

        return Inertia::render('Gym/Records', [
            'sharing' => (bool) $user->gym_share_records,
            'people' => $people->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'avatar' => Media::url($person->profilePicture),
                'me' => $person->id === $user->id,
                // Their strongest lifts first (by estimated one-rep max)
                'lifts' => ($best[$person->id] ?? collect())->groupBy('gym_exercise_id')->map(function (Collection $sets, $exerciseId) use ($names) {
                    $top = $sets->sortByDesc(fn ($set) => GymSet::oneRepMax((float) $set->weight, $set->reps))->first();

                    return [
                        'exercise' => $names[$exerciseId]?->label(),
                        'exerciseId' => (int) $exerciseId,
                        'weight' => (float) $sets->max('weight'),
                        'oneRepMax' => GymSet::oneRepMax((float) $top->weight, $top->reps),
                    ];
                })->sortByDesc('oneRepMax')->take(8)->values(),
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

    private function exerciseList(User $user): Collection
    {
        return GymExercise::for($user)->get()->map(fn ($exercise) => $this->exerciseRow($exercise))
            ->sortBy(fn ($exercise) => [array_search($exercise['group'], GymExercise::GROUPS) === false ? 99 : array_search($exercise['group'], GymExercise::GROUPS), mb_strtolower($exercise['label'])])
            ->values();
    }

    private function exerciseRow(GymExercise $exercise): array
    {
        return ['id' => $exercise->id, 'label' => $exercise->label(), 'group' => $exercise->muscle_group, 'custom' => $exercise->isCustom(), 'photos' => $exercise->photos()];
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
