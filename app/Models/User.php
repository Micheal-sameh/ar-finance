<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['tenant_id', 'name', 'email', 'phone', 'password', 'status', 'avarewase_membership_code'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Stored as digits with an optional leading + so "+20 100-123 4567" and
     * "+201001234567" are the same login.
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : self::normalizePhone($value));
    }

    public static function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        return str_starts_with(trim($value), '+') ? '+'.$digits : $digits;
    }

    /**
     * The login identifier is an email address when it contains an @,
     * otherwise a phone number.
     */
    public static function findByLoginIdentifier(string $identifier): ?self
    {
        $identifier = trim($identifier);

        return str_contains($identifier, '@')
            ? self::query()->where('email', Str::lower($identifier))->first()
            : self::query()->where('phone', self::normalizePhone($identifier))->first();
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Marks the authenticator as enrolled and issues one-time recovery codes.
     *
     * @return array<int, string>
     */
    public function confirmTwoFactor(): array
    {
        $codes = collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $this->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        return $codes;
    }

    public function consumeRecoveryCode(string $code): bool
    {
        $code = Str::lower(trim($code));
        $codes = $this->two_factor_recovery_codes ?? [];

        $match = collect($codes)->first(fn (string $stored) => hash_equals($stored, $code));

        if ($match === null) {
            return false;
        }

        $this->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$match]))])->save();

        return true;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * A Platform Admin has no tenant of their own — they act across every
     * tenant, or inside whichever one they've switched into (see
     * TenantContext). Their tenant_id column value is irrelevant. Portal
     * Manager shares this cross-tenant access (same permission set) but is
     * still barred from granting the Platform Admin role itself — see
     * UpdateUserRequest.
     */
    public function isPlatformAdmin(): bool
    {
        return $this->hasAnyRole(['Platform Admin', 'Portal Manager']);
    }
}
