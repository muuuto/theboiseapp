<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;

class UserController extends Controller
{
    // Show Register/Create Form
    public function create() {
        return view('users.register');
    }

    // Create New User
    public function store(Request $request) {
        $formFields = $request->validate([
            'name' => ['required', 'min:3'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|confirmed|min:6'
        ]);

        // Hash Password
        $formFields['password'] = bcrypt($formFields['password']);

        // Create User
        $user = User::create($formFields);
        
         // email data
        $email_data = array(
            'name' => $formFields['name'],
            'email' => $formFields['email'],
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

        return redirect('/')->with('message', 'You have been logged out!');

    }

    // Show Login Form
    public function login() {
        return view('users.login');
    }

    // Authenticate User
    public function authenticate(Request $request) {
        $formFields = $request->validate([
            'email' => ['required', 'email'],
            'password' => 'required'
        ]);

        if(auth()->attempt($formFields)) {
            $request->session()->regenerate();
            
            $user = auth()->user();
            date_default_timezone_set('Europe/Rome');
            $user->update(array('last_login' => date('Y-m-d H:i:s', time())));

            return redirect()->intended('/')->with('message', 'You are now logged in!');
        }

        return back()->withErrors(['email' => 'Invalid Credentials'])->onlyInput('email');
    }

    public function guest() {
        $user = auth()->loginUsingId(28, true);
        date_default_timezone_set('Europe/Rome');
        $user->update(array('last_login' => date('Y-m-d H:i:s', time())));

        return redirect()->intended('/')->with('message', 'You are now logged in!');
    }

    // display form for resetting password
    public function forgotPasswordShow() {
        return view('users.forgot-password');
    }

    // post form of resetting password
    public function forgotPasswordManage(Request $request) {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
                    ? back()->with(['status' => $status])
                    : back()->withErrors(['email' => $status]);
    }

    // form of the new password
    public function forgotPasswordReset(string $token) {
        return view('users.reset-password', ['token' => $token]);
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
                    ? redirect()->route('login')->with('status', "Succesfully reset of password")
                    : back()->withErrors(['email' => [($status)]]);
    }

    public function monitor(Request $request) {
        $users = User::all()->sortByDesc('last_login');
        $user = auth()->user()['isAdmin'];
        if ($user == 1) {
            return view('monitor.index', [
                'users' => $users
            ]);        } else {
            abort(403, 'Unauthorized Action');
        }
    }
}
