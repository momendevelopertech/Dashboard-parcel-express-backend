<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Driver;
use App\Notifications\TemplatedEmail;
use App\Services\FcmService;
use App\Services\OtpService;
use Carbon\Carbon;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileAppleController extends Controller
{
    public function loginOrRegister(Request $request)
    {
        $request->validate([
            'identity_token' => 'required|string',
            'full_name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'is_guest' => 'nullable|boolean',
            'fcm_token' => 'nullable|string',
        ]);

        // 1) Verify the Apple identity token (signature + claims)
        $claims = $this->verifyAppleIdentityToken($request->string('identity_token'));
        if (!$claims) {
            return $this->sendResponse('Invalid Apple identity token', [], false, [], 401);
        }

        if (($claims['iss'] ?? null) !== 'https://appleid.apple.com') {
            return $this->sendResponse('Invalid issuer', [], false, [], 401);
        }

        $expectedAud = config('services.apple.merchant_id_ios', env('APPLE_MERCHANT_ID_IOS'));
        if (($claims['aud'] ?? null) !== $expectedAud) {
            return $this->sendResponse('Audience mismatch', [], false, [], 401);
        }

        // Extract identity info
        $appleSub = $claims['sub'] ?? null;
        $email = $claims['email'] ?? $request->input('email');
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $name = $request->input('full_name');

        if (!$appleSub) {
            return $this->sendResponse('Missing Apple subject', [], false, [], 401);
        }

        DB::beginTransaction();
        try {
            // 2) Upsert user
            $user = User::where('apple_id', $appleSub)->first();
            if (!$user && $email) {
                $user = User::where('email', $email)->first();
            }

            $wasNewUser = false;
            if (!$user) {
                $user = User::create([
                    'name' => $name ?: 'User',
                    'email' => $email,
                    'apple_id' => $appleSub,
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => $email && $emailVerified ? Carbon::now() : null,
                ]);
                $wasNewUser = true;
            }

            // 3) Ensure Driver record exists/updated
            $driver = Driver::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'pending',
                    'phone' => $request->string('phone') ?: null,
                    'is_guest' => (bool) $request->boolean('is_guest', true),
                ]
            );

            $phoneChanged = false;
            if ($request->filled('phone') && $driver->phone !== $request->string('phone')) {
                $driver->phone = $request->string('phone');
                $driver->save();
                $phoneChanged = true;
            }


            // 4) Send welcome email if new user
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
                    \Log::warning('Failed to send welcome email: ' . $e->getMessage());
                }
            }

            // 5) Send OTP if phone exists (new user OR phone changed)
            if ($request->filled('phone') && ($wasNewUser || $phoneChanged)) {
                try {
                    $otpService = app(OtpService::class);
                    $otp = $otpService->generateAndSendOtp($user, $request->string('phone'));

                    DB::commit();
                    return $this->sendResponse(
                        "User created/updated successfully. Please verify your phone with the OTP sent.",
                        [
                            'user' => $user->load('roles', 'driver'),
                            'driver' => $driver,
                            'verification_required' => true,
                            'default_otp' => app()->environment(['local', 'testing']) ? $otp : null,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('Apple OTP send failed: ' . $e->getMessage());

                    $user->forceFill([
                        'verification_code' => '111111',
                        'verification_code_expires_at' => now()->addMinutes(2),
                        'phone_verified_at' => null,
                    ])->save();

                    DB::commit();
                    return $this->sendResponse(
                        "User created/updated successfully. Using default OTP.",
                        [
                            'user' => $user->load('roles', 'driver'),
                            'driver' => $driver,
                            'verification_required' => true,
                            'default_otp' => app()->environment(['local', 'testing']) ? '111111' : null,
                        ]
                    );
                }
            }

            $token = $user->createToken('driver-app')->plainTextToken;
            DB::commit();
            if ($request->filled('fcm_token')) {
                DeviceToken::updateOrCreate(
                    ['token' => $request->fcm_token],
                    ['user_id' => $user->id, 'platform' => $request->input('platform')]
                );

                try {
                    /** @var FcmService $fcm */
                    $fcm = resolve(FcmService::class);
                    $fcm->subscribeTokenToDriverTopic($user->id, $request->fcm_token);
                } catch (\Throwable $e) {
                    \Log::warning('FCM subscribe (Apple login) failed: ' . $e->getMessage());
                }
            }
            $payload = [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
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
            ];

            return $this->sendResponse('Authenticated via Apple', $payload);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendResponse('Error occurred.', [], false, [$e->getMessage()], 422);
        }
    }


    /**
     * Verify Apple identity token (JWT) using Apple JWKS.
     * Returns claims array on success, null on failure.
     */
    private function verifyAppleIdentityToken(string $jwt): ?array
    {
        try {
            // Cache Apple JWKS for 1 day
            $jwks = Cache::remember('apple_jwks', 86400, function () {
                $json = file_get_contents('https://appleid.apple.com/auth/keys');
                return json_decode($json, true);
            });

            if (!isset($jwks['keys']))
                return null;

            $keys = JWK::parseKeySet($jwks);
            // Accept RS256 only (Apple uses RS256)
            $decoded = JWT::decode($jwt, $keys);
            // Convert to associative array
            $claims = json_decode(json_encode($decoded), true);

            // exp check (Firebase\JWT already checks iat/nbf/exp by default with leeway)
            return $claims;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // Use your global helper if you have it
    private function sendResponse(string $message, $data = [], bool $success = true, array $errors = [], int $code = 200)
    {
        return response()->json([
            'message' => $message,
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
        ], $code);
    }
}
