<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $perPage = (int) $request->query('per_page', 15);

        if (! in_array($perPage, [15, 25, 50], true)) {
            $perPage = 15;
        }

        $admins = User::query()
            ->role('admin')
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at,
            ]);

        return Inertia::render('Admin/Admins/Index', [
            'admins' => $admins,
            'filters' => [
                'q' => $search,
                'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAdmin($request);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'locale' => 'da',
            'is_active' => $validated['is_active'],
            'email_verified_at' => now(),
        ]);

        $user->assignRole('admin');

        return back()->with('status', 'Administrator oprettet.');
    }

    public function update(Request $request, User $admin): RedirectResponse
    {
        if (! $admin->hasRole('admin')) {
            abort(404);
        }

        $validated = $this->validateAdmin($request, $admin);

        $admin->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
        ]);

        if (! empty($validated['password'])) {
            $admin->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        return back()->with('status', 'Administrator opdateret.');
    }

    public function toggleActive(User $admin): RedirectResponse
    {
        if (! $admin->hasRole('admin')) {
            abort(404);
        }

        $activeAdmins = User::query()
            ->role('admin')
            ->where('is_active', true)
            ->count();

        if ($admin->is_active && $activeAdmins <= 1) {
            return back()->withErrors([
                'admin' => 'Du kan ikke deaktivere den sidste aktive administrator.',
            ]);
        }

        $admin->update([
            'is_active' => ! $admin->is_active,
        ]);

        return back()->with('status', $admin->is_active ? 'Administrator aktiveret.' : 'Administrator deaktiveret.');
    }

    public function removeRole(User $admin): RedirectResponse
    {
        if (! $admin->hasRole('admin')) {
            abort(404);
        }

        $adminCount = User::query()->role('admin')->count();

        if ($adminCount <= 1) {
            return back()->withErrors([
                'admin' => 'Du kan ikke fjerne den sidste administrator.',
            ]);
        }

        $admin->removeRole('admin');

        return back()->with('status', 'Adminrolle fjernet fra brugeren.');
    }

    private function validateAdmin(Request $request, ?User $admin = null): array
    {
        $passwordRules = $admin
            ? ['nullable', 'string', 'min:8', 'confirmed']
            : ['required', 'string', 'min:8', 'confirmed'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($admin?->id),
            ],
            'is_active' => ['required', 'boolean'],
            'password' => $passwordRules,
        ]);
    }
}
