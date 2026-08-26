<?php

namespace App\Models;

use App\Exceptions\DependentRecordsExistException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

/**
 * A named, reusable password guarding a topic branch or a single page.
 *
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones.
 *
 * @property int $id
 * @property string $name
 * @property string $password_hash
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Topic> $topics
 * @property-read Collection<int, Page> $pages
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
     * `password_hash` is deliberately not fillable, so this and
     * changePassword() are the only ways it is ever written.
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
                $parts[] = __('admin.dependents.topics').': '.implode(', ', $topics);
            }

            if ($pages !== []) {
                $parts[] = __('admin.dependents.pages').': '.implode(', ', $pages);
            }

            throw new DependentRecordsExistException(__('admin.dependents.password_in_use', [
                'usages' => implode(' — ', $parts),
            ]));
        });
    }

    /** @return HasMany<Topic, $this> */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    /** @return HasMany<Page, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
}
