<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BackupException;
use App\Http\Controllers\Controller;
use App\Services\BackupArchive;
use App\Support\MediaStream;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Making, downloading and deleting backups from the browser.
 *
 * Every action here is behind `auth`, and unlike everything else that serves a
 * private file this controller never asks App\Support\MediaAccess. That class
 * answers "may *this visitor* see this file", and its answer is yes for
 * anything a public page shows. An archive is the whole database — the right
 * question is "is this the owner", which the middleware already answered.
 */
class BackupController extends Controller
{
    public function index(BackupArchive $archive): InertiaResponse
    {
        return Inertia::render('admin/backups/index', [
            'backups' => $archive->all(),
            'keep' => config('backup.keep'),
        ]);
    }

    /**
     * Make one now. Runs inline rather than on a queue — there is no queue
     * worker in this stack (the technical reference) — so a large library holds
     * this request open for a while; the front end warns it can take minutes.
     */
    public function store(BackupArchive $archive): RedirectResponse
    {
        try {
            $name = $archive->create();
        } catch (BackupException $e) {
            return back()->with('error', __('admin.backups.failed', ['reason' => $e->getMessage()]));
        }

        return back()->with('status', __('admin.backups.created', ['name' => $name]));
    }

    public function download(string $name, BackupArchive $archive): Response
    {
        try {
            $resolved = $archive->resolve($name);
        } catch (BackupException $e) {
            abort(Response::HTTP_NOT_FOUND, $e->getMessage());
        }

        return MediaStream::sendArchive($resolved);
    }

    public function destroy(string $name, BackupArchive $archive): RedirectResponse
    {
        try {
            $archive->delete($name);
        } catch (BackupException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('admin.backups.deleted'));
    }
}
