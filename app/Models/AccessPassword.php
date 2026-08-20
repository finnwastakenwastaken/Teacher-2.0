<?php

namespace App\Models;

use App\Exceptions\DependentRecordsExistException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

/**
 * A named, reusable password guarding a topic branch or a single page.
 */
#[Fillable(['name'])]
class AccessPassword extends Model
{
    /**
     * The hash is never sent to the browser. It is also what the unlock
     * cookie is bound to, so leaking it would leak every issued cookie.
     */
    protected $hidden = ['password_hash'];

    /**
     * The only two ways the hash is ever written.
     *
     * `password_hash` is deliberately not fillable, and creating the record
     * before hashing would hit the NOT NULL constraint anyway — so there is
     * no path that accidentally stores a row without a secret, or stores a
     * plaintext one.
     */
    public static function createWithPassword(string $name, string $plain): self
    {
        $password = new self(['name' => $name]);
        $password->password_hash = Hash::make($plain);
        $password->save();

        return $password;
    }

    /**
     * Changing this invalidates every unlock cookie issued under the old
     * secret, because the cookie carries a fingerprint of the hash — see
     * App\Support\AccessControl. That is the only revocation mechanism there
     * is, and it is the reason the hash is what the cookie is bound to.
     */
    public function changePassword(string $plain): void
    {
        $this->password_hash = Hash::make($plain);
        $this->save();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $password): void {
            $topics = $password->topics()->pluck('title')->all();
            $pages = $password->pages()->pluck('title')->all();

            if ($topics === [] && $pages === []) {
                return;
            }

            // Blocks rather than nulling the foreign key: silently detaching
            // would publish protected material, and the only sign would be
            // that the prompt stopped appearing.
            $parts = [];

            if ($topics !== []) {
                $parts[] = 'onderwerpen: '.implode(', ', $topics);
            }

            if ($pages !== []) {
                $parts[] = "pagina's: ".implode(', ', $pages);
            }

            throw new DependentRecordsExistException(
                'Dit wachtwoord is nog in gebruik en kan niet worden verwijderd ('
                .implode(' — ', $parts).').'
            );
        });
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
}
