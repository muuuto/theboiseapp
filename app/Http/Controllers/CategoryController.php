<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Models\Slogan;
use App\Http\Controllers\AccountController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class CategoryController extends Controller
{
    public $accountController;

    public function __construct(AccountController $accountController) {
        $this->accountController = $accountController;
    }

    // Show all categories
    public function index() {
        $user = auth()->user();
        if ($user) {
            $categories = Category::all()->sortBy('title');
        }

        $hided = $user->hidedCategories()->allRelatedIds()->all();
        $categories->filter(function($value, $key) use ($hided, $categories) {
            if(in_array($value->id, $hided)) {
                $categories->forget($key);
            }
        });
        

        $slogan = Slogan::all();

        if(count($slogan) > 0) {
            $randomSlogan = $slogan->random();
        } else {
            $randomSlogan["sloganPhrase"] = "Find your albums";
        }
        
        return view('forum.categories.index', [
            'user' => $user,
            'slogan' => $randomSlogan,
            'categories' => $categories
        ]);
    }

     // Show Create Form
    public function create() {
        $user = auth()->user();
        $users = User::all();

        if (auth()->user()) {
            return view('forum.categories.create', [
                'user' => $user,
                'users' => $users
            ]);
        } else {
            abort(403, 'Unauthorized Action');
        }
    }

    // Store Category Data
    public function store(Request $request) {
        $formFields = $request->validate([
            'title' => 'required',
            'tags' => 'required',
            'description' => 'required',
            'logo' => 'required'
        ]);

        $resizedImage = $this->accountController->resizeImage($request->file('logo')->path(), 144, 144);
        $image = $this->accountController->imageConvert($resizedImage, 100);

        $formFields['logo'] = $image->store('categoryLogo', 'public');
        
        $formFields['created_by'] = auth()->id();

        $category = Category::create($formFields);

        if($request->hideCategoryFrom) {
            foreach($request->hideCategoryFrom as $person) {
                $category->hidedUser()->attach($person);
            }
        }

        return redirect('/forum')->with('message', 'Category created successfully!');
    }

    // Manage Categories
    public function manage() {
        $user = auth()->user();
        $category = $user->categories()->get();

        return view('forum.categories.manage', [
            'category' => $category
        ]);
    }

    // Show Edit Form
    public function edit(Category $category) {
        $categoryCreator = $category->created_by;
        $allUsers = User::all();

        if (auth()->id() == $categoryCreator || auth()->id() == 1) {
            return view('forum.categories.edit', [
                'category' => $category,
                'users' => auth()->user(),
                'allUsers' => $allUsers
            ]);
        } else {
            abort(403, 'Unauthorized Action');
        }
    }

    // Update Category Data
    public function update(Request $request, Category $category) {
        $categoryCreator = $category->created_by;

        // Make sure logged in user is owner
        if($categoryCreator != auth()->id()) {
            abort(403, 'Unauthorized Action');
        }
        
        $formFields = $request->validate([
            'title' => 'required',
            'tags' => 'required',
            'description' => 'required'
        ]);

        if($request->hasFile('logo')) {
            if($category->logo && Storage::disk('public')->exists($category->logo)) {
                Storage::disk('public')->delete($category->logo);
            }

            $resizedImage = $this->accountController->resizeImage($request->file('logo')->path(), 144, 144);
            $image = $this->accountController->imageConvert($resizedImage, 100);

            $formFields['logo'] = $image->store('categoryLogo', 'public');
        }
        
        if($request->hideCategoryFrom) {
            $category->hidedUser()->sync($request->hideCategoryFrom);
        }

        $category->update($formFields);

        return redirect('/forum/category/manage')->with('message', 'Category updated successfully!');
    }

    // Delete Category
    public function destroy(Category $category) {
        // Make sure logged in user is owner
        $user = auth()->user()['isAdmin'];
        if($user != 1) {
            abort(403, 'Unauthorized Action');
        }

        if($category->logo && Storage::disk('public')->exists($category->logo)) {
            Storage::disk('public')->delete($category->logo);
        }

        foreach($category->posts()->getResults() as $post) {
            if($post->cover && Storage::disk('public')->exists($post->cover)) {
                Storage::disk('public')->delete($post->cover);
            }
            
            $attachments = json_decode($post->attachments, true);
            foreach($attachments as $attachment) {
                if($attachment && Storage::disk('public')->exists($attachment)) {
                    Storage::disk('public')->delete($attachment);
                }
            }
        }

        $category->delete();

        return redirect('/forum/category/manage')->with('message', 'Category deleted successfully');
    }
    
    // Store Comments Data
    public function comments(Request $request) {
        $formFields = $request->validate([
            'user_id' => 'required',
            'listing_id' => 'required',
            'comment' => 'required'
        ]);

        $users = Listing::findOrFail($request->listing_id)->users;
        $commentOfUser = User::findOrFail($request->user_id)->name;
        $albumTitle = Listing::findOrFail($request->listing_id)->title;

        foreach($users as $user) {
            $email_data = array(
                'name' => $user['name'],
                'email' => $user['email'],
                'commentOfUser' => $commentOfUser,
                'comment' => $formFields['comment'],
                'albumTitle' => $albumTitle
            );
            Mail::send('/mails/new_comment', $email_data, function ($message) use ($email_data) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject('New comment added to album - theboise.it')
                    ->from('info@theboise.it', 'TheBoise');
            });
        }

        $listing = Comment::create($formFields);
        
        return back()->with('message', 'Comment created successfully!');
    }
}
