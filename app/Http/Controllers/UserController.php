<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Media;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;

class UserController extends Controller
{
    // Show Register/Create Form: registration is invite-only
    public function create(?string $token = null) {
        if (! Invite::findUsable($token)) {
            return Inertia::render('Auth/InviteOnly');
        }

        return Inertia::render('Auth/Register', ['token' => $token]);
    }

    // Create New User
    public function store(Request $request) {
        $formFields = $request->validate([
            'name' => ['required', 'min:3'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|confirmed|min:6',
            'invite' => 'required|string'
        ]);

        // Lock the invite so the same link can't create two accounts at the same time
        $user = DB::transaction(function () use ($formFields) {
            $invite = Invite::where('token_hash', hash('sha256', $formFields['invite']))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $invite) {
                return null;
            }

            $user = User::create([
                'name' => $formFields['name'],
                'email' => $formFields['email'],
                'password' => bcrypt($formFields['password']),
            ]);

            $invite->update(['used_at' => now(), 'used_by' => $user->id]);

            return $user;
        });

        if (! $user) {
            return back()->withErrors(['invite' => 'This invite link is not valid anymore, ask for a new one.'])->onlyInput('name', 'email');
        }

         // email data
        $email_data = array(
            'name' => $user->name,
            'email' => $user->email,
            'note' => Invite::where('used_by', $user->id)->value('note'),
        );
    
        // send email to user with the template
        Mail::send('/mails/welcome_email', $email_data, function ($message) use ($email_data) {
            $message->to($email_data['email'], $email_data['name'])
                ->subject('Welcome to theboise.it')
                ->from('info@theboise.it', 'TheBoise');
        });
        
        // send email to user with the template
        Mail::send('/mails/user_registered', $email_data, function ($message) {
            $message->to('matteo.gavoni@gmail.com', 'Admin')
                ->subject('New user - theboise.it')
                ->from('info@theboise.it', 'TheBoise');
        });

        // Login
        auth()->login($user);

        return redirect('/')->with('message', 'User created and logged in');
    }

    // Logout User
    public function logout(Request $request) {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // From a Vue page: full page load, nothing of the previous session stays in the browser
        if ($request->header('X-Inertia')) {
            return Inertia::location('/login');
        }

        return redirect('/')->with('message', 'You have been logged out!');
    }

    // Show Login Form
    public function login() {
        return Inertia::render('Auth/Login');
    }

    // Authenticate User
    public function authenticate(Request $request) {
        $formFields = $request->validate([
            'email' => ['required', 'email'],
            'password' => 'required'
        ]);

        // "Remember me": stay logged in on this device
        if(auth()->attempt($formFields, $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            $user = auth()->user();
            date_default_timezone_set('Europe/Rome');
            $user->update(array('last_login' => date('Y-m-d H:i:s', time())));

            return redirect()->intended('/')->with('message', 'You are now logged in!');
        }

        return back()->withErrors(['email' => 'Invalid Credentials'])->onlyInput('email');
    }

    // Secret link for the CV: logs straight into the read-only demo account
    public function demo(string $secret) {
        $expected = config('app.demo_secret');
        abort_unless($expected && hash_equals($expected, $secret), 404);

        $user = User::where('is_demo', true)->firstOrFail();
        auth()->login($user);
        request()->session()->regenerate();

        date_default_timezone_set('Europe/Rome');
        $user->update(array('last_login' => date('Y-m-d H:i:s', time())));

        return redirect('/');
    }

    // Light, dark or follow the device (null)
    public function theme(Request $request) {
        $request->validate(['theme' => 'required|in:system,light,dark']);

        $request->user()->update([
            'dark_mode' => ['system' => null, 'light' => 0, 'dark' => 1][$request->theme],
        ]);

        return back();
    }

    // display form for resetting password
    public function forgotPasswordShow() {
        return Inertia::render('Auth/ForgotPassword');
    }

    // post form of resetting password
    public function forgotPasswordManage(Request $request) {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
                    ? back()->with(['status' => __($status)])
                    : back()->withErrors(['email' => __($status)]);
    }

    // form of the new password
    public function forgotPasswordReset(Request $request, string $token) {
        // The email comes in the link of the reset email
        return Inertia::render('Auth/ResetPassword', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function forgotPasswordUpdate(Request $request) {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);
     
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));
     
                $user->save();
     
                event(new PasswordReset($user));
            }
        );
     
        return $status === Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', 'Your password has been changed: you can log in.')
                    : back()->withErrors(['email' => __($status)]);
    }

    // Admin: who logged in and when
    public function monitor(Request $request) {
        abort_unless(auth()->user()->isAdmin == 1, 403, 'Unauthorized Action');

        $users = User::orderByDesc('last_login')->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => Media::url($user->profilePicture),
            'lastLogin' => $user->last_login ? \Carbon\Carbon::parse($user->last_login, 'Europe/Rome')->toIso8601String() : null,
            'isAdmin' => $user->isAdmin == 1,
            'isDemo' => (bool) $user->is_demo,
        ]);

        return Inertia::render('Admin/Monitor', ['users' => $users]);
    }
}
