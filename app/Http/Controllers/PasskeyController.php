<?php

namespace App\Http\Controllers;

use App\Models\Passkey;
use Illuminate\Http\Request;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

// Passkeys (WebAuthn): log in with Face ID / fingerprint, the password stays as a backup.
// Two steps each: the site sends a challenge, the phone signs it after Face ID, the site checks it
class PasskeyController extends Controller
{
    // --- Adding one (from the account page, on the phone) ---

    public function registerOptions(Request $request)
    {
        $user = $request->user();
        $webauthn = $this->webauthn();
        $args = $webauthn->getCreateArgs(
            (string) $user->id,
            $user->email,
            $user->name,
            60,
            true, // a discoverable key: log in without typing the email
            true, // Face ID / fingerprint / PIN required
            false, // this phone's own (not a USB key)
            $user->passkeys()->pluck('credential_id')->map(fn ($id) => ByteBuffer::fromBase64Url($id))->all(),
        );
        $request->session()->put('passkey.register', base64_encode($webauthn->getChallenge()->getBinaryString()));

        return response()->json($args);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'clientDataJSON' => 'required|string',
            'attestationObject' => 'required|string',
            'name' => 'nullable|string|max:100',
        ]);
        $challenge = $request->session()->pull('passkey.register');
        abort_unless($challenge, 422, 'Start again');

        try {
            $result = $this->webauthn()->processCreate(
                self::binary($data['clientDataJSON']),
                self::binary($data['attestationObject']),
                base64_decode($challenge),
                true,
                true,
                false, // any phone: no list of trusted manufacturers to check against
            );
        } catch (WebAuthnException) {
            return response()->json(['message' => __('That didn\'t work, try again.')], 422);
        }

        $request->user()->passkeys()->create([
            'credential_id' => self::base64url($result->credentialId),
            'public_key' => $result->credentialPublicKey,
            'sign_count' => (int) $result->signatureCounter,
            'name' => $data['name'] ?: 'Phone',
        ]);
        $request->session()->flash('message', 'Face ID / fingerprint login is on for this phone');

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Passkey $passkey)
    {
        abort_unless($passkey->user_id === $request->user()->id, 404);
        $passkey->delete();

        return back()->with('message', 'Removed: that phone logs in with the password again');
    }

    // --- Logging in (login page, on the phone) ---

    public function loginOptions(Request $request)
    {
        $webauthn = $this->webauthn();
        // No list of keys: the phone offers the ones it has for this site
        $args = $webauthn->getGetArgs([], 60, true, true, true, true, true, true);
        $request->session()->put('passkey.login', base64_encode($webauthn->getChallenge()->getBinaryString()));

        return response()->json($args);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|string|max:512',
            'clientDataJSON' => 'required|string',
            'authenticatorData' => 'required|string',
            'signature' => 'required|string',
            'userHandle' => 'nullable|string',
        ]);
        $challenge = $request->session()->pull('passkey.login');
        $passkey = Passkey::with('user')->where('credential_id', $data['id'])->first();
        $user = $passkey?->user;
        $failed = fn () => response()->json(['message' => __('This phone isn\'t set up to log in here. Use your password, then turn it on in Account.')], 422);

        if (! $challenge || ! $user || $user->is_demo || (! empty($data['userHandle']) && self::binary($data['userHandle']) !== (string) $user->id)) {
            return $failed();
        }

        $webauthn = $this->webauthn();
        try {
            $webauthn->processGet(
                self::binary($data['clientDataJSON']),
                self::binary($data['authenticatorData']),
                self::binary($data['signature']),
                $passkey->public_key,
                base64_decode($challenge),
                $passkey->sign_count ?: null,
                true,
                true,
            );
        } catch (WebAuthnException) {
            return $failed();
        }

        $passkey->update(['sign_count' => (int) ($webauthn->getSignatureCounter() ?? $passkey->sign_count), 'last_used_at' => now()]);
        // Like the password with "Remember me": phones stay logged in
        auth()->login($user, true);
        $request->session()->regenerate();
        $user->update(['last_login' => now('Europe/Rome')->format('Y-m-d H:i:s')]);
        $request->session()->flash('message', 'You are now logged in!');

        return response()->json(['redirect' => redirect()->intended('/')->getTargetUrl()]);
    }

    // --- Helpers ---

    // The site's domain, so a passkey made on theboise.it also works on www.theboise.it
    private function webauthn(): WebAuthn
    {
        return new WebAuthn('TheBoise', preg_replace('/^www\./', '', request()->getHost()), null, true);
    }

    private static function binary(string $base64url): string
    {
        return ByteBuffer::fromBase64Url($base64url)->getBinaryString();
    }

    private static function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
