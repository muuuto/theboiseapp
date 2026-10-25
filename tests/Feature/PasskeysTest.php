<?php

namespace Tests\Feature;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Passkeys: Face ID / fingerprint login. A fake phone below makes a real key pair and signs
// like a real one, so the whole check runs (challenge, origin, signature, counter)
class PasskeysTest extends TestCase
{
    use RefreshDatabase;

    private \OpenSSLAsymmetricKey $key;
    private string $credentialId;
    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $this->credentialId = random_bytes(32);
    }

    public function test_turn_it_on_then_log_in_with_face_id(): void
    {
        $anna = User::factory()->create();
        $this->addPasskey($anna)->assertOk();
        $this->assertSame(1, $anna->passkeys()->count());
        $this->actingAs($anna)->get('/account')->assertInertia(fn ($page) => $page->where('passkeys.0.name', 'iPhone'));

        auth()->logout();
        $this->flushSession();
        $this->logIn($anna)->assertOk()->assertJson(['redirect' => url('/')]);
        $this->assertAuthenticatedAs($anna);
        $this->assertNotNull($anna->fresh()->last_login);
        $this->assertNotNull(Passkey::first()->last_used_at);
        $this->assertSame(1, Passkey::first()->sign_count);
    }

    public function test_a_wrong_signature_or_someone_elses_key_never_logs_in(): void
    {
        $anna = User::factory()->create();
        $bob = User::factory()->create();
        $this->addPasskey($anna);
        auth()->logout();

        // Signed by another key
        $real = $this->key;
        $this->key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $this->logIn($anna)->assertStatus(422);
        $this->key = $real;
        // Anna's key, but claiming to be Bob
        $this->logIn($bob)->assertStatus(422);
        // A key the site doesn't know
        $this->credentialId = random_bytes(32);
        $this->logIn($anna)->assertStatus(422);
        // Without asking for a challenge first (an old answer sent again)
        $this->postJson('/passkeys/login', ['id' => 'x', 'clientDataJSON' => 'x', 'authenticatorData' => 'x', 'signature' => 'x'])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_only_your_own_and_never_the_demo(): void
    {
        $anna = User::factory()->create();
        $this->addPasskey($anna);
        $passkey = Passkey::first();

        $this->actingAs(User::factory()->create())->delete("/account/passkeys/{$passkey->id}")->assertNotFound();
        $this->actingAs($anna)->delete("/account/passkeys/{$passkey->id}")->assertRedirect();
        $this->assertSame(0, Passkey::count());

        $demo = User::factory()->create(['is_demo' => true]);
        $this->actingAs($demo)->postJson('/account/passkeys/options')->assertForbidden();
        $this->actingAs($demo)->get('/account')->assertInertia(fn ($page) => $page->where('passkeys', null));
    }

    // --- The fake phone ---

    private function addPasskey(User $user)
    {
        $options = $this->actingAs($user)->postJson('/account/passkeys/options')->assertOk()->json('publicKey');
        $this->assertSame('localhost', $options['rp']['id']);
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);

        $details = openssl_pkey_get_details($this->key)['ec'];
        $coseKey = $this->cbor([1 => 2, 3 => -7, -1 => 1, -2 => new CborBytes($details['x']), -3 => new CborBytes($details['y'])]);
        // rpIdHash, flags (user present + verified + key included), counter, AAGUID, the key
        $authData = hash('sha256', 'localhost', true) . chr(0x45) . pack('N', 0) . str_repeat("\0", 16)
            . pack('n', strlen($this->credentialId)) . $this->credentialId . $coseKey;

        return $this->postJson('/account/passkeys', [
            'clientDataJSON' => $this->b64(json_encode(['type' => 'webauthn.create', 'challenge' => $options['challenge'], 'origin' => 'http://localhost'])),
            'attestationObject' => $this->b64($this->cbor(['fmt' => 'none', 'attStmt' => [], 'authData' => new CborBytes($authData)])),
            'name' => 'iPhone',
        ]);
    }

    private function logIn(User $as)
    {
        $options = $this->postJson('/passkeys/login/options')->assertOk()->json('publicKey');
        $clientData = json_encode(['type' => 'webauthn.get', 'challenge' => $options['challenge'], 'origin' => 'http://localhost']);
        $authData = hash('sha256', 'localhost', true) . chr(0x05) . pack('N', ++$this->counter);
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $this->postJson('/passkeys/login', [
            'id' => $this->b64($this->credentialId),
            'clientDataJSON' => $this->b64($clientData),
            'authenticatorData' => $this->b64($authData),
            'signature' => $this->b64($signature),
            'userHandle' => $this->b64((string) $as->id),
        ]);
    }

    private function b64(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    // Just enough CBOR (the phone's binary format): numbers, text, bytes, maps
    private function cbor(mixed $value): string
    {
        $head = function (int $major, int $length): string {
            $type = $major << 5;

            return match (true) {
                $length < 24 => chr($type | $length),
                $length < 256 => chr($type | 24) . chr($length),
                $length < 65536 => chr($type | 25) . pack('n', $length),
                default => chr($type | 26) . pack('N', $length),
            };
        };

        return match (true) {
            is_int($value) => $value >= 0 ? $head(0, $value) : $head(1, -1 - $value),
            $value instanceof CborBytes => $head(2, strlen($value->data)) . $value->data,
            is_string($value) => $head(3, strlen($value)) . $value,
            default => $head(5, count($value)) . implode('', array_map(fn ($key) => $this->cbor($key) . $this->cbor($value[$key]), array_keys($value))),
        };
    }
}

final class CborBytes
{
    public function __construct(public string $data) {}
}
