<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Models\PostComment;
use App\Models\Slogan;
use App\Http\Controllers\AccountController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class PostController extends Controller
{
    public $accountController;

    public function __construct(AccountController $accountController) {
        $this->accountController = $accountController;
    }

    // Show all categories
    public function index(Category $category) {
        $user = auth()->user();
        if ($category->hidedUser) {
            $category->hidedUser->each(function ($hidedUser) use ($user) {
                if($hidedUser->id == $user->id) {
                    abort(403, 'Unauthorized Action');
                };
            });
        }

        if ($user) {
            $posts = $category->posts;
        }

        $hided = $user->hidedPost()->allRelatedIds()->all();
        $posts->filter(function($value, $key) use ($hided, $posts) {
            if(in_array($value->id, $hided)) {
                $posts->forget($key);
            }
        });
        
        $slogan = Slogan::all();

        if(count($slogan) > 0) {
            $randomSlogan = $slogan->random();
        } else {
            $randomSlogan["sloganPhrase"] = "Find your albums";
        }
        
        date_default_timezone_set('Europe/Rome');

        return view('forum.posts.index', [
            'user' => $user,
            'slogan' => $randomSlogan,
            'category' => $category,
            'posts' => $posts
        ]);
    }

     // Show Create Form
    public function create(Category $category) {
        $user = auth()->user();
        $users = User::all();

        if (auth()->user()) {
            return view('forum.posts.create', [
                'user' => $user,
                'users' => $users,
                'category' => $category
            ]);
        } else {
            abort(403, 'Unauthorized Action');
        }
    }

    // Store Post Data
    public function store(Request $request, Category $category) {
        $formFields = $request->validate([
            'title' => 'required',
            'description' => 'required',
            'tags' => 'required',
            'content' => 'required'
        ]);

        $request->has('canEdit') ? $formFields['canEdit'] = 1 : $formFields['canEdit'] = 0;
        $request->has('is_private') ? $formFields['is_private'] = 1 : $formFields['is_private'] = 0;

        if($request->hasFile('cover')) {
            $resizedImage = $this->accountController->resizeImage($request->file('cover')->path(), 0, 0);
            $image = $this->accountController->imageConvert($resizedImage, 100);

            $formFields['cover'] = $image->store('cover', 'public');
        }

        if($request->file('attachments')) {
            $jsonPaths = [];
            foreach($request->file('attachments') as $key => $file)
            {
                $resizedImage = $this->accountController->resizeImage($file->path(), 256, 256);
                $image = $this->accountController->imageConvert($resizedImage, 100);

                array_push($jsonPaths, $image->store('attachments', 'public'));
            }

            $formFields['attachments'] = json_encode(array_values($jsonPaths));
        }

        $formFields['author'] = auth()->id();
        $formFields['counter'] = 0;
        $formFields['category_id'] = $category->id;

        $post = Post::create($formFields);
        
        if($request->hideFrom) {
            foreach($request->hideFrom as $person) {
                $post->hidedUser()->attach($person);
            }
        }

        $email_data = array(
            'name' => $formFields['title']
        );
        Mail::send('/mails/new_post', $email_data, function ($message) {
            $message->to('matteo.gavoni@gmail.com', 'Admin')
                ->subject('New post - theboise.it')
                ->from('info@theboise.it', 'TheBoise');
        });

        return redirect('/forum/' . $category->id)->with('message', 'Post created successfully!');
    }

    // Show single Post
    public function show(Category $category, Post $post) {
        $user = auth()->user();
        if ($post->hidedUser) {
            $post->hidedUser->each(function ($hidedUser) use ($user) {
                if($hidedUser->id == $user->id) {
                    abort(403, 'Unauthorized Action');
                };
            });
        }
        
        $comments = $post->comments;
        $author = $post->author()->getResults()->name; 
        $attachments = json_decode($post->attachments, true);
        
        if($post->is_private && ($post->author()->getResults()->id != auth()->id())) {
            abort(403, 'Unauthorized Action');
        }

        $formFields['counter'] = $post->counter + 1;
        $post->update($formFields);

        return view('forum.posts.show', [
            'category' => $category,
            'post' => $post,
            'comments' => $comments,
            'user' => $user,
            'author' => $author,
            'attachments' => $attachments
        ]);
    }

    // Store Comments Data
    public function comment(Request $request, Category $category, Post $post) {
        $user = auth()->id();
        $formFields = $request->validate([
            'comment' => 'required'
        ]);
        $formFields['user_id'] = $user;
        $formFields['post_id'] = $post->id;

        $uniqueComments = $post->comments()->getResults()->unique('user_id');
        foreach($uniqueComments as $comment) {
            if($comment->user_id != $user) {
                $email_data = array(
                    'name' => $comment->user()->getResults()->name,
                    'email' => $comment->user()->getResults()->email,
                    'commentOfUser' => User::findOrFail(auth()->id())->name,
                    'comment' => $formFields['comment'],
                    'postTitle' => $post->title,
                    'postLink' => 'https://theboise.it/forum/' . $category->id . '/' . $post->id
                );

                Mail::send('/mails/new_comment_post', $email_data, function ($message) use ($email_data, $post) {
                    $message->to($email_data['email'], $email_data['name'])
                        ->subject('New comment added to post ' . $post->title . ' - theboise.it')
                        ->from('info@theboise.it', 'TheBoise');
                });
            }
        }

        if (!$uniqueComments->contains('user_id', null, $category->created_by)) {
            $email_data = array(
                'name' => $post->author()->getResults()->name,
                'email' => $post->author()->getResults()->email,
                'commentOfUser' => User::findOrFail(auth()->id())->name,
                'comment' => $formFields['comment'],
                'postTitle' => $post->title,
                'postLink' => 'https://theboise.it/forum/' . $category->id . '/' . $post->id
            );

            Mail::send('/mails/new_comment_post', $email_data, function ($message) use ($email_data, $post) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject('New comment added to post ' . $post->title . ' - theboise.it')
                    ->from('info@theboise.it', 'TheBoise');
            });
        }

        $email_data = array(
            'name' => $post->author()->getResults()->name,
            'email' => $post->author()->getResults()->email,
            'commentOfUser' => $post->author()->getResults()->name,
            'comment' => $formFields['comment'],
            'postTitle' => $post->title,
            'postLink' => 'https://theboise.it/forum/' . $category->id . '/' . $post->id
        );

        Mail::send('/mails/new_comment_post', $email_data, function ($message) use ($email_data, $post) {
            $message->to($email_data['email'], $email_data['name'])
                ->subject('New comment added to post ' . $post->title . ' - theboise.it')
                ->from('info@theboise.it', 'TheBoise');
        });

        PostComment::create($formFields);
        
        return back()->with('message', 'Comment created successfully!');
    }


    // Manage Posts
    public function manage(Category $category, Post $post) {
        $user = auth()->user();

        $posts = $category->posts;

        return view('forum.posts.manage', [
            'user' => $user,
            'category' => $category,
            'posts' => $posts
        ]);
    }

    // Show Edit Form
    public function edit(Category $category, Post $post) {
        $author = $post->author()->getResults();
        $users = User::all();

        if ($post->canEdit || auth()->id() == $author->id || auth()->id() == 1) {
            return view('forum.posts.edit', [
                'category' => $category,
                'post' => $post,
                'author' => $author,
                'user' => auth()->user(),
                'users' => $users
            ]);
        } else {
            abort(403, 'Unauthorized Action');
        }
    }

    // Update Post Data
    public function update(Request $request, Category $category, Post $post) {
        $author = $post->author()->getResults();
        $currentUser = auth()->user();
        
        // Make sure logged in user is owner
        if($post->canEdit || auth()->id() == $author->id || auth()->id() == 1) {
            $request->has('is_private') ? $formFields['is_private'] = 1 : $formFields['is_private'] = 0;

            $formFields = $request->validate([
                'title' => 'required',
                'description' => 'required',
                'tags' => 'required',
                'content' => 'required'
            ]);

            if($request->hasFile('cover')) {
                if($post->cover && Storage::disk('public')->exists($post->cover)) {
                    Storage::disk('public')->delete($post->cover);
                }

                $resizedImage = $this->accountController->resizeImage($request->file('cover')->path(), 0, 0);
                $image = $this->accountController->imageConvert($resizedImage, 100);

                $formFields['cover'] = $image->store('cover', 'public');
            }

            if($request->file('attachments')) {
                $attachments = json_decode($post->attachments, true);
                foreach($attachments as $attachment)
                {
                    if($attachment && Storage::disk('public')->exists($attachment)) {
                        Storage::disk('public')->delete($attachment);
                    }
                }

                $jsonPaths = [];
                foreach($request->file('attachments') as $key => $file)
                {
                    $resizedImage = $this->accountController->resizeImage($file->path(), 256, 256);
                    $image = $this->accountController->imageConvert($resizedImage, 100);

                    array_push($jsonPaths, $image->store('attachments', 'public'));
                }

                $formFields['attachments'] = json_encode(array_values($jsonPaths));
            }
            
            date_default_timezone_set('Europe/Rome');
            $arrayLastChanges = [];
            if($post->last_changes) {
                $arrayLastChanges = json_decode($post->last_changes, true);
            }
            array_push($arrayLastChanges, date('Y-m-d H:i:s', time()) . ': ' . $currentUser->name);
            $formFields['last_changes'] = json_encode(array_values($arrayLastChanges));
            
            if($request->hideFrom) {
                $post->hidedUser()->sync($request->hideFrom);
            }

            $post->update($formFields);

            return $this->show($category, $post)->with('message', 'Post updated successfully!');
        } else {
            abort(403, 'Unauthorized Action');
        }
    }

    // Delete Post
    public function destroy(Category $category, Post $post) {
        // Make sure logged in user is owner
        $author = $post->author()->getResults();

        if($author->id != auth()->id()) {
            abort(403, 'Unauthorized Action');
        }

        if($post->cover && Storage::disk('public')->exists($post->cover)) {
            Storage::disk('public')->delete($post->cover);
        }
        
        $attachments = json_decode($post->attachments, true);

        foreach($attachments as $attachment) {
            if($attachment && Storage::disk('public')->exists($attachment)) {
                Storage::disk('public')->delete($attachment);
            }
        }

        $post->delete();

        return redirect('/forum/' . $category->id)->with('message', 'Post deleted successfully');
    }
}
