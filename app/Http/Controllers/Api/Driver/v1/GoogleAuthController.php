<?php

namespace App\Http\Controllers\Api\Driver\v1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\Driver;
use App\Notifications\TemplatedEmail;
use App\Services\FcmService;
use App\Services\OtpService;
use Carbon\Carbon;
use Google\Merchant as GoogleMerchant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

class GoogleAuthController extends Controller
{
    public function __construct(private FirebaseAuth $firebaseAuth) {}

    public function loginOrRegister(Request $request)
    {
        $request->validate([
            'firebase_id_token' => 'nullable|string',
            'id_token' => 'nullable|string',
            'access_token' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'is_guest' => 'nullable|boolean',
            'fcm_token' => 'nullable|string',
        ]);

        if (
            !$request->filled('firebase_id_token') &&
            !$request->filled('id_token') &&
            !$request->filled('access_token')
        ) {
            return $this->sendResponse('Provide firebase_id_token or id_token or access_token', [], false, [], 422);
        }

        $profile = null;

        if ($request->filled('firebase_id_token')) {
            $profile = $this->profileFromFirebase($request->string('firebase_id_token'));
            if (!$profile) {
                return $this->sendResponse('Invalid Firebase ID token', [], false, [], 401);
            }
        }

        if (!$profile && $request->filled('id_token')) {
            $google = $this->verifyIdToken($request->string('id_token'));
            if (!$google) {
                return $this->sendResponse('Invalid Google id_token', [], false, [], 401);
            }
            $profile = [
                'provider' => 'google',
                'provider_id' => $google['sub'] ?? null,
                'email' => $google['email'] ?? null,
                'email_verified' => (bool) ($google['email_verified'] ?? false),
                'name' => $google['name'] ?? null,
                'avatar' => $google['picture'] ?? null,
                'firebase_uid' => null,
            ];
        }

        if (!$profile && $request->filled('access_token')) {
            try {
                $gUser = Socialite::driver('google')->stateless()
                    ->userFromToken($request->string('access_token'));

                $profile = [
                    'provider' => 'google',
                    'provider_id' => $gUser->getId(),
                    'email' => $gUser->getEmail(),
                    'email_verified' => true,
                    'name' => $gUser->getName() ?: $gUser->getNickname(),
                    'avatar' => $gUser->getAvatar(),
                    'firebase_uid' => null,
                ];
            } catch (\Throwable $e) {
                return $this->sendResponse('Invalid Google access_token', [], false, [$e->getMessage()], 401);
            }
        }

        if (!$profile) {
            return $this->sendResponse('Unable to resolve user profile', [], false, [], 400);
        }

        $user = null;
        $wasNewUser = false;

        if (!empty($profile['firebase_uid'])) {
            $user = User::where('firebase_uid', $profile['firebase_uid'])->first();
        }

        if (!$user && $profile['provider'] === 'google' && !empty($profile['provider_id'])) {
            $user = User::where('google_id', $profile['provider_id'])->first();
        }

        if (!$user && !empty($profile['email'])) {
            $user = User::where('email', $profile['email'])->first();
        }

        if (!$user) {
            $user = User::create([
                'name' => $profile['name'] ?: 'User',
                'email' => $profile['email'] ?? null,
                'google_id' => $profile['provider'] === 'google' ? $profile['provider_id'] : null,
                'firebase_uid' => $profile['firebase_uid'] ?? null,
                'avatar' => $profile['avatar'] ?? null,
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => !empty($profile['email_verified']) ? Carbon::now() : null,
            ]);
            $wasNewUser = true;
        }

        // Use withoutGlobalScope to bypass ExcludeGuestDriversScope
        // This allows us to create and find guest drivers (is_guest = true)
        $driver = Driver::withoutGlobalScope(\App\Models\Scopes\ExcludeGuestDriversScope::class)
            ->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'pending',
                    'phone' => $request->string('phone') ?: null,
                    'is_guest' => (bool) $request->boolean('is_guest', true),
                ]
            );
        if ($request->filled('fcm_token')) {
            // Check if token exists for another user
            $existingToken = DeviceToken::where('token', $request->fcm_token)->first();
            if ($existingToken && $existingToken->user_id !== $user->id) {
                Log::info("FCM Token transfer (Google): from user {$existingToken->user_id} to user {$user->id}", [
                    'token' => substr($request->fcm_token, 0, 20) . '...',
                    'old_user_id' => $existingToken->user_id,
                    'new_user_id' => $user->id,
                ]);
            }

            DeviceToken::updateOrCreate(
                ['token' => $request->fcm_token],
                ['user_id' => $user->id, 'platform' => $request->input('platform')]
            );

            try {
                /** @var FcmService $fcm */
                $fcm = resolve(FcmService::class);
                $fcm->subscribeTokenToDriverTopic($user->id, $request->fcm_token);
            } catch (\Throwable $e) {
                Log::warning('FCM subscribe (google login) failed: ' . $e->getMessage());
            }
        }


        $verificationRequired = false;

        $updates = [];

        // Convert Stringable to string for comparison
        // Convert Stringable to string for comparison
        $requestPhone = $request->phone;

        if ($request->filled('phone') && (string) $driver->phone !== (string)$requestPhone) {
            $updates['phone'] = $requestPhone;
            $verificationRequired = true;
        }

        if ($updates) {
            $driver->fill($updates)->save();
        }

        if ($wasNewUser || $verificationRequired) {
            try {
                $otpService = new OtpService();
                $otp = $otpService->generateAndSendOtp($user, $driver->phone);
               $user->setRelation('driver', $driver);
                return $this->sendResponse("Verification required. Please verify your phone with the OTP sent.", [
                    'user' => $user->load('roles'), // DO NOT load('driver') again
                    'verification_required' => true,
                    'default_otp' => app()->environment(['local', 'testing']) ? $otp : null,
                ]);
            } catch (\Throwable $e) {
                Log::error("Failed to send OTP: " . $e->getMessage());

                $user->update([
                    'verification_code' => '111111',
                    'verification_code_expires_at' => now()->addMinutes(2),
                ]);

                $user->setRelation('driver', $driver);

                return $this->sendResponse("Verification required. Using default OTP.", [
                    'user' => $user->load('roles'), // DO NOT load('driver') again
                    'verification_required' => true,
                    'default_otp' => '111111',
                ]);
            }
        }


        if ($wasNewUser && $user->email) {
            try {
                $user->notify(new TemplatedEmail(
                    'WELCOME_GOOGLE_DRIVER',
                    [
                        'receiver_name' => $user->name,
                        'receiver_email' => $user->email,
                        'driver_status' => $driver->status,
                        'app_name' => config('app.name'),
                        'support_email' => config('mail.from.address') ?? 'support@parcelexpress.om',
                        'marketing_url' => config('app.url') ?? 'https://parcelexpress.om',
                    ]
                ));
            } catch (\Throwable $e) {
                Log::warning('Failed to send welcome email: ' . $e->getMessage());
            }
        }
        $token = $user->createToken('driver-app')->plainTextToken;

        return $this->sendResponse('Authenticated', [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
            'driver' => [
                'id' => $driver->id,
                'status' => $driver->status,
                'rejection_reason' => $driver->rejection_reason,
                'phone' => $driver->phone,
                'is_guest' => (bool) $driver->is_guest,
                'documents' => [
                    'id_card' => (bool) $driver->id_card,
                    'license' => (bool) $driver->license,
                    'car_ownership_id' => (bool) $driver->car_ownership_id,
                ],
            ],
            'verification_required' => false,
        ]);
    }
    private function profileFromFirebase(string $idToken): ?array
    {
        try {
            $verified = $this->firebaseAuth->verifyIdToken($idToken);

            $claims = $verified->claims()->all();
            $uid = $claims['sub'] ?? null;

            if (!$uid) {
                Log::warning('Firebase token missing sub (uid) claim');
                return null;
            }

            $aud = $claims['aud'] ?? null;
            $expected = config('firebase.project_id') ?: env('FIREBASE_PROJECT_ID');

            if ($expected && $aud) {
                $audValues = is_array($aud) ? $aud : [$aud];
                if (!in_array($expected, $audValues, true)) {
                    Log::warning('Firebase aud differs from expected (non-fatal)', [
                        'aud' => $audValues,
                        'expected' => $expected,
                    ]);
                }
            }

            $fbUser = $this->firebaseAuth->getUser($uid);

            return [
                'provider' => 'firebase',
                'provider_id' => null,
                'firebase_uid' => $uid,
                'email' => $fbUser->email,
                'email_verified' => (bool) $fbUser->emailVerified,
                'name' => $fbUser->displayName,
                'avatar' => $fbUser->photoUrl,
            ];
        } catch (\Throwable $e) {
            $this->debugTokenTimes($idToken);
            Log::error('Firebase ID token verify failed: ' . $e->getMessage());
            return null;
        }
    }


    private function debugTokenTimes(string $idToken): void
    {
        try {
            $parts = explode('.', $idToken);
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            $nowUtc = Carbon::now('UTC')->timestamp;
            Log::info('JWT time check', [
                'now_utc_ts' => $nowUtc,
                'now_utc_iso' => Carbon::now('UTC')->toIso8601String(),
                'iat' => $payload['iat'] ?? null,
                'exp' => $payload['exp'] ?? null,
                'diff_now_minus_iat' => isset($payload['iat']) ? $nowUtc - $payload['iat'] : null,
                'diff_exp_minus_now' => isset($payload['exp']) ? $payload['exp'] - $nowUtc : null,
                'iss' => $payload['iss'] ?? null,
                'aud' => $payload['aud'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to decode token payload for debug: ' . $e->getMessage());
        }
    }

    private function verifyIdToken(string $idToken): ?array
    {
        try {
            $merchant = new GoogleMerchant(['merchant_id' => config('services.google.merchant_id')]);
            $payload = $merchant->verifyIdToken($idToken);
            if (!$payload)
                return null;
            if (($payload['aud'] ?? null) !== config('services.google.merchant_id'))
                return null;

            return [
                'sub' => $payload['sub'] ?? null,
                'email' => $payload['email'] ?? null,
                'email_verified' => (bool) ($payload['email_verified'] ?? false),
                'name' => $payload['name'] ?? null,
                'picture' => $payload['picture'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function sendResponse(string $message, $data = [], bool $success = true, array $errors = [], int $code = 200)
    {
        return response()->json(compact('message', 'success', 'data', 'errors'), $code);
    }
}
