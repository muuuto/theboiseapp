<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Support\AlbumCover;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CategoryController extends Controller
{
    // Forum home: the categories not hidden from the user
    public function index()
    {
        $user = auth()->user();
        $hidden = $user->hidedCategories()->allRelatedIds()->all();

        $categories = Category::with(['posts.hidedUser', 'hidedUser'])
            ->whereNotIn('id', $hidden)
            ->orderBy('title')
            ->get()
            ->map(function (Category $category) use ($user) {
                // Posts know their category already: no query per post in isVisibleTo()
                $category->posts->each->setRelation('category', $category);
                $posts = $category->posts->filter(fn ($post) => $post->isVisibleTo($user));

                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'description' => $category->description,
                    'tags' => $this->tags($category->tags),
                    'logo' => Media::url($category->logo) ?? asset('images/no-image.png'),
                    'postCount' => $posts->count(),
                    'lastPostAt' => $posts->max('created_at')?->toIso8601String(),
                    'canEdit' => $this->canEdit($category),
                    'canDelete' => $user->isAdmin == 1,
                ];
            });

        return Inertia::render('Forum/Index', ['categories' => $categories]);
    }

    // Show Create Form
    public function create()
    {
        return Inertia::render('Forum/CategoryForm', $this->formProps(null));
    }

    // Store Category Data
    public function store(Request $request)
    {
        $formFields = $this->validateCategory($request, logoRequired: true);
        $formFields['logo'] = AlbumCover::store($request->file('logo'), 'categoryLogo', 400);
        $formFields['created_by'] = auth()->id();

        $category = Category::create($formFields);
        $category->hidedUser()->sync($request->input('hideFrom', []));

        return redirect('/forum/' . $category->id)->with('message', 'Category created!');
    }

    // The old "manage" page: everything is on the forum home now
    public function manage()
    {
        return redirect('/forum');
    }

    // Show Edit Form (creator and admins)
    public function edit(Category $category)
    {
        abort_unless($this->canEdit($category), 403, 'Unauthorized Action');

        return Inertia::render('Forum/CategoryForm', $this->formProps($category));
    }

    // Update Category Data (creator and admins)
    public function update(Request $request, Category $category)
    {
        abort_unless($this->canEdit($category), 403, 'Unauthorized Action');

        $formFields = $this->validateCategory($request, logoRequired: false);

        if ($request->hasFile('logo')) {
            if ($category->logo && Storage::disk('public')->exists($category->logo)) {
                Storage::disk('public')->delete($category->logo);
            }
            $formFields['logo'] = AlbumCover::store($request->file('logo'), 'categoryLogo', 400);
        }

        // Nobody selected means visible to everyone
        $category->hidedUser()->sync($request->input('hideFrom', []));
        $category->update($formFields);

        return redirect('/forum/' . $category->id)->with('message', 'Category updated!');
    }

    // Delete Category (admins), with its posts' files
    public function destroy(Category $category)
    {
        abort_unless(auth()->user()->isAdmin == 1, 403, 'Unauthorized Action');

        if ($category->logo && Storage::disk('public')->exists($category->logo)) {
            Storage::disk('public')->delete($category->logo);
        }

        foreach ($category->posts()->getResults() as $post) {
            if ($post->cover && Storage::disk('public')->exists($post->cover)) {
                Storage::disk('public')->delete($post->cover);
            }

            // Posts without attachments have null here
            $attachments = json_decode($post->attachments ?? '', true) ?? [];
            foreach ($attachments as $attachment) {
                if ($attachment && Storage::disk('public')->exists($attachment)) {
                    Storage::disk('public')->delete($attachment);
                }
            }
        }

        $category->delete();

        return redirect('/forum')->with('message', 'Category deleted');
    }

    private function canEdit(Category $category): bool
    {
        return auth()->id() == $category->created_by || auth()->user()->isAdmin == 1;
    }

    private function validateCategory(Request $request, bool $logoRequired): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'tags' => 'required|array|min:1',
            'tags.*' => 'string|max:50',
            'logo' => ($logoRequired ? 'required' : 'nullable') . '|image|max:20480',
            'hideFrom' => 'nullable|array',
            'hideFrom.*' => 'integer|exists:users,id',
        ]);

        return [
            'title' => $data['title'],
            'description' => $data['description'],
            'tags' => collect($data['tags'])->map(fn ($tag) => trim(str_replace(',', ' ', $tag)))->filter()->unique()->implode(', '),
        ];
    }

    private function formProps(?Category $category): array
    {
        return [
            'category' => $category ? [
                'id' => $category->id,
                'title' => $category->title,
                'description' => $category->description,
                'tags' => $this->tags($category->tags),
                'logo' => Media::url($category->logo),
                'hideFrom' => $category->hidedUser()->pluck('users.id'),
            ] : null,
            'users' => $this->people(),
        ];
    }

    // Everyone a category or post can be hidden from (not the demo account: it never sees the forum)
    private function people()
    {
        return User::where('is_demo', false)->where('id', '!=', auth()->id())->orderBy('name')->get()
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'avatar' => Media::url($user->profilePicture)]);
    }

    private function tags(?string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $tags))));
    }
}
