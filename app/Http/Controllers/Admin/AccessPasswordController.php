<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DependentRecordsExistException;
use App\Http\Controllers\Controller;
use App\Models\AccessPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Managing the named passwords that guard topic branches and pages.
 *
 * The plaintext is never stored and never shown again after it is set — if
 * the owner forgets one, they change it, which is also the moment every
 * cookie issued under the old one stops working.
 */
class AccessPasswordController extends Controller
{
    /**
     * Short enough to read out to a class, long enough that the limiter is
     * bounding a real search rather than a list of a dozen obvious guesses.
     */
    private const MIN_LENGTH = 8;

    public function index(): Response
    {
        return Inertia::render('admin/passwords/index', [
            'passwords' => AccessPassword::query()
                ->withCount(['topics', 'pages'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (AccessPassword $password) => [
                    'id' => $password->id,
                    'name' => $password->name,
                    'topicsCount' => $password->topics_count,
                    'pagesCount' => $password->pages_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('access_passwords', 'name')],
            // Short on purpose — this is read out to a class and typed on a
            // phone, so the admin password policy does not belong here. But
            // not as short as it was. Four characters is a keyspace of about
            // 1.7 million in theory and about a dozen in practice, because
            // what people actually pick is `2024`, `havo`, a class code. The
            // limiter below is what has to survive that guess, and at four
            // characters it was being asked to hold off an attack measured
            // in hours. Eight is still sayable across a classroom.
            'password' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:255'],
        ], $this->messages());

        AccessPassword::createWithPassword($validated['name'], $validated['password']);

        return back()->with('status', __('admin.passwords.created'));
    }

    /**
     * Rename, and optionally set a new secret.
     *
     * Changing the secret invalidates every cookie issued under the old one,
     * because the cookie carries a fingerprint of the hash — see
     * App\Support\AccessControl. That is the point: it is the only way to
     * revoke access after a password has been passed around.
     */
    public function update(Request $request, AccessPassword $password): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('access_passwords', 'name')->ignore($password),
            ],
            'password' => ['nullable', 'string', 'min:'.self::MIN_LENGTH, 'max:255'],
        ], $this->messages());

        $password->update(['name' => $validated['name']]);

        if (filled($validated['password'] ?? null)) {
            $password->changePassword($validated['password']);

            return back()->with('status', __('admin.passwords.changed'));
        }

        return back()->with('status', __('admin.passwords.updated'));
    }

    public function destroy(AccessPassword $password): RedirectResponse
    {
        try {
            $password->delete();
            // Thrown from a `deleting` model event, which PHPStan cannot
            // see from here — so it reports this catch as dead. It is not:
            // remove it and "this still has things depending on it" becomes
            // a 500. The guard lives on the model exactly so that no delete
            // path can skip it.
            // @phpstan-ignore catch.neverThrown
        } catch (DependentRecordsExistException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('admin.passwords.deleted'));
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => __('admin.passwords.name_required'),
            'name.unique' => __('admin.passwords.name_taken'),
            'password.required' => __('admin.passwords.password_required'),
            'password.min' => __('admin.passwords.password_min', ['count' => self::MIN_LENGTH]),
        ];
    }
}
