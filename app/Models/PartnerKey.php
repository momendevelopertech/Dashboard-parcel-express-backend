<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class PartnerKey extends Model
{
    protected $fillable = ['partner_id', 'key_type', 'key_id', 'secret_hash', 'is_active', 'expires_at'];
    protected $casts = ['is_active' => 'boolean', 'expires_at' => 'datetime'];
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Generate a new API key and secret for a partner
     *
     * @param Partner $partner
     * @param string $keyType 'sandbox' or 'production'
     * @return array ['key_id' => string, 'secret' => string]
     */
    public static function generate(Partner $partner, string $keyType = 'sandbox'): array
    {
        // Validate key type
        if (!in_array($keyType, ['sandbox', 'production'])) {
            throw new \InvalidArgumentException("Key type must be 'sandbox' or 'production'");
        }

        // Set prefix based on key type
        $prefix = $keyType === 'sandbox' ? 'test_' : 'live_';
        $keyId = $prefix . Str::random(32);
        $secret = Str::random(10); // Max 10 characters plain text

        self::create([
            'partner_id' => $partner->id,
            'key_type' => $keyType,
            'key_id' => $keyId,
            'secret_hash' => $secret, // Store as plain text
            'is_active' => true,
            'expires_at' => null,
        ]);

        return [
            'key_id' => $keyId,
            'secret' => $secret,
            'key_type' => $keyType,
        ];
    }

    /**
     * Generate both sandbox and production keys for a partner
     *
     * @param Partner $partner
     * @return array ['sandbox' => [...], 'production' => [...]]
     */
    public static function generateBoth(Partner $partner): array
    {
        $sandbox = self::generate($partner, 'sandbox');
        $production = self::generate($partner, 'production');

        return [
            'sandbox' => $sandbox,
            'production' => $production,
        ];
    }
}
