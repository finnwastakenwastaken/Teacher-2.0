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
            // Short on purpose. This is read out to a class and typed on a
            // phone; the admin password policy does not belong here, and the
            // rate limiter is what makes a short one survive contact.
            'password' => ['required', 'string', 'min:4', 'max:255'],
        ], $this->messages());

        AccessPassword::createWithPassword($validated['name'], $validated['password']);

        return back()->with('status', 'Wachtwoord toegevoegd.');
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
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
        ], $this->messages());

        $password->update(['name' => $validated['name']]);

        if (filled($validated['password'] ?? null)) {
            $password->changePassword($validated['password']);

            return back()->with('status', 'Wachtwoord gewijzigd. Iedereen moet het opnieuw invoeren.');
        }

        return back()->with('status', 'Wachtwoord bijgewerkt.');
    }

    public function destroy(AccessPassword $password): RedirectResponse
    {
        try {
            $password->delete();
        } catch (DependentRecordsExistException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Wachtwoord verwijderd.');
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => 'Vul een naam in.',
            'name.unique' => 'Er bestaat al een wachtwoord met deze naam.',
            'password.required' => 'Vul een wachtwoord in.',
            'password.min' => 'Het wachtwoord moet minstens 4 tekens lang zijn.',
        ];
    }
}
