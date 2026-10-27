<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\User;
use App\Support\Settings;
use App\Support\Tags;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

// Admins: the album tags library. Rename a tag (every album changes), merge two that are the same
// person ("Sarà" into "Sara": the old spelling becomes an alias), other spellings, link an account,
// and look at the new tags members added
class TagController extends Controller
{
    public function index()
    {
        $ready = Schema::hasTable('tags');
        $library = $ready ? Tag::with('user')->get() : collect();
        $counts = Tags::counts();

        return Inertia::render('Admin/Tags', [
            'ready' => $ready,
            // A to Z (like everywhere), each with how many albums have it
            'tags' => $library->sortBy(fn (Tag $tag) => [Tags::plain($tag->name), $tag->name])
                ->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'aliases' => $tag->aliases ?? [],
                    'userId' => $tag->user_id,
                    'albums' => $counts[$tag->name] ?? 0,
                    'isNew' => $tag->is_new,
                ])->values(),
            // Minus the pairs an admin said are different people
            'lookAlikes' => Tags::lookAlikes($library)->map(fn ($pair) => [$pair[0]->id, $pair[1]->id])
                ->reject(fn ($pair) => in_array(implode('-', $pair), Settings::get(Settings::DISTINCT_TAGS, []) ?? [], true))->values(),
            'users' => User::where('is_demo', false)->orderBy('name')->get(['id', 'name'])->map(fn ($user) => ['id' => $user->id, 'name' => $user->name]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:50']);
        $name = trim(str_replace(',', ' ', $data['name']));
        if (Tags::resolve($name, Tag::all())) {
            throw ValidationException::withMessages(['name' => __('There\'s already a tag for ":name".', ['name' => $name])]);
        }
        Tag::create(['name' => $name]);

        return back()->with('message', 'Tag added');
    }

    // Name (renaming changes every album), other spellings, linked account; also "looks fine"
    public function update(Request $request, Tag $tag)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'aliases' => 'array|max:20',
            'aliases.*' => 'string|max:50',
            'userId' => ['nullable', 'integer', 'exists:users,id', Rule::unique('tags', 'user_id')->ignore($tag->id)],
        ], ['userId.unique' => __('That account already has a tag.')]);
        $name = trim(str_replace(',', ' ', $data['name']));
        $others = Tag::where('id', '!=', $tag->id)->get();
        if (Tags::resolve($name, $others)) {
            throw ValidationException::withMessages(['name' => __('There\'s already a tag for ":name": merge them instead.', ['name' => $name])]);
        }
        $aliases = collect($data['aliases'] ?? [])->map(fn ($alias) => trim($alias))->filter()
            ->reject(fn ($alias) => Tags::plain($alias) === Tags::plain($name))
            ->unique(fn ($alias) => Tags::plain($alias))->values();
        if ($taken = $aliases->first(fn ($alias) => Tags::resolve($alias, $others))) {
            throw ValidationException::withMessages(['aliases' => __('":alias" already means :tag.', ['alias' => $taken, 'tag' => Tags::resolve($taken, $others)->name])]);
        }

        $old = $tag->name;
        $tag->update(['name' => $name, 'aliases' => $aliases->all() ?: null, 'user_id' => $data['userId'] ?? null, 'is_new' => false]);
        if ($old !== $name) {
            Tags::replaceInAlbums($old, $name);
        }

        return back()->with('message', 'Tag saved');
    }

    // The same person twice: every album gets $into, the old name becomes one of its spellings
    public function merge(Request $request, Tag $tag)
    {
        $data = $request->validate(['into' => ['required', 'integer', Rule::exists('tags', 'id'), Rule::notIn([$tag->id])]]);
        $into = Tag::findOrFail($data['into']);
        $albums = Tags::replaceInAlbums($tag->name, $into->name);
        $into->update([
            'aliases' => collect($into->aliases ?? [])->concat([$tag->name, ...($tag->aliases ?? [])])
                ->reject(fn ($alias) => Tags::plain($alias) === Tags::plain($into->name))
                ->unique(fn ($alias) => Tags::plain($alias))->values()->all(),
            'user_id' => $into->user_id ?? $tag->user_id,
        ]);
        $tag->delete();

        return back()->with('message', __(':old merged into :new (:count albums)', ['old' => $tag->name, 'new' => $into->name, 'count' => $albums]));
    }

    // "Matteo" and "Matt" look alike but are two people: stop suggesting to merge them
    public function distinct(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|size:2', 'ids.*' => 'integer']);
        $pair = collect($data['ids'])->sort()->implode('-');
        Settings::set(Settings::DISTINCT_TAGS, collect(Settings::get(Settings::DISTINCT_TAGS, []) ?? [])->push($pair)->unique()->values()->all());

        return back();
    }

    // Only a tag no album uses (merge the others)
    public function destroy(Tag $tag)
    {
        abort_if(Tags::counts()[$tag->name] ?? 0, 422, 'Albums still use it: merge it instead');
        $tag->delete();

        return back()->with('message', 'Tag deleted');
    }
}
