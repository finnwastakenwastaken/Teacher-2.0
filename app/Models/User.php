<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use RuntimeException;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Stored lower-case, always. `lowercase_usernames` is on in
     * config/fortify.php, so a login canonicalises what was typed before
     * looking it up — and an address stored with a capital in it then matches
     * nothing, on a site whose only account this is. Claiming as
     * `Teacher@school.nl` locked the owner out permanently:
     * `admin:reset-password` is §3's recovery path and cannot help, because
     * the password was never what failed. Here rather than only in the form
     * request so that `admin:seed`, a factory and tinker are covered too.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower($value),
        );
    }

    /**
     * The single admin account can never be deleted — this site has no
     * recovery path but editing the database by hand. Last of three layers
     * (no delete route, no controller action), and the one that also catches
     * tinker, a seeder, or a cascade. Throws rather than returning false so
     * the mistake is loud.
     */
    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new RuntimeException(
                'The administrator account cannot be deleted. '
                .'To change its credentials use: php artisan admin:reset-password'
            );
        });
    }
}
