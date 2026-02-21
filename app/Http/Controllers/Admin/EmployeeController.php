<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\Skill;
use App\Models\User;
use App\Models\BookingAssignment;
use App\Models\BookingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $perPage = (int) $request->query('per_page', 15);

        if (! in_array($perPage, [15, 25, 50], true)) {
            $perPage = 15;
        }

        $employees = User::query()
            ->role('employee')
            ->with(['employeeProfile.skills:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $like = '%'.$search.'%';

                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('employeeProfile', function ($profileQuery) use ($like): void {
                            $profileQuery->where('phone', 'like', $like)
                                ->orWhereHas('skills', fn ($skillsQuery) => $skillsQuery->where('name', 'like', $like));
                        });
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
                'phone' => $user->employeeProfile?->phone,
                'hourly_wage' => $user->employeeProfile?->hourly_wage,
                'hourly_customer_rate' => $user->employeeProfile?->hourly_customer_rate,
                'skills' => $user->employeeProfile?->skills->map(fn (Skill $skill): array => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                ])->values()->all() ?? [],
            ]);

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
            'skills' => Skill::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEmployee($request);

        $name = trim((string) ($validated['name'] ?? ''));
        $email = $validated['email'];

        $user = User::create([
            'name' => $name !== '' ? $name : Str::before($email, '@'),
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'locale' => 'da',
            'is_active' => $validated['is_active'],
        ]);

        $user->assignRole('employee');

        $profile = EmployeeProfile::create([
            'user_id' => $user->id,
            'phone' => $validated['phone'] ?? null,
            'hourly_wage' => $validated['hourly_wage'] ?? null,
            'hourly_customer_rate' => $validated['hourly_customer_rate'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        $profile->skills()->sync($validated['skill_ids'] ?? []);

        return back()->with('status', 'Medarbejder oprettet.');
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        if (! $employee->hasRole('employee')) {
            abort(404);
        }

        $validated = $this->validateEmployee($request, $employee);

        $employee->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
        ]);

        if (! empty($validated['password'])) {
            $employee->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        $profile = $employee->employeeProfile()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'phone' => $validated['phone'] ?? null,
                'hourly_wage' => $validated['hourly_wage'] ?? null,
                'hourly_customer_rate' => $validated['hourly_customer_rate'] ?? null,
                'is_active' => $validated['is_active'],
            ],
        );

        $profile->skills()->sync($validated['skill_ids'] ?? []);

        return back()->with('status', 'Medarbejder opdateret.');
    }

    public function toggleActive(User $employee): RedirectResponse
    {
        if (! $employee->hasRole('employee')) {
            abort(404);
        }

        $newState = ! $employee->is_active;

        $employee->update([
            'is_active' => $newState,
        ]);

        $employee->employeeProfile()->updateOrCreate(
            ['user_id' => $employee->id],
            ['is_active' => $newState],
        );

        return back()->with('status', $newState ? 'Medarbejder aktiveret.' : 'Medarbejder deaktiveret.');
    }

    public function destroy(User $employee): RedirectResponse
    {
        if (! $employee->hasRole('employee')) {
            abort(404);
        }

        $hasAssignments = BookingAssignment::query()
            ->where('employee_user_id', $employee->id)
            ->exists();
        $hasRequests = BookingRequest::query()
            ->where('employee_user_id', $employee->id)
            ->exists();

        if ($hasAssignments || $hasRequests) {
            return back()->withErrors([
                'employee_delete' => 'Medarbejderen kan ikke slettes, fordi der er bookinghistorik.',
            ]);
        }

        $employee->delete();

        return back()->with('status', 'Medarbejder slettet.');
    }

    private function validateEmployee(Request $request, ?User $employee = null): array
    {
        $nameRules = $employee
            ? ['required', 'string', 'max:255']
            : ['nullable', 'string', 'max:255'];

        $passwordRules = $employee
            ? ['nullable', 'string', 'min:8', 'confirmed']
            : ['required', 'string', 'min:8', 'confirmed'];

        return $request->validate([
            'name' => $nameRules,
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee?->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'hourly_wage' => ['required', 'numeric', 'min:0'],
            'hourly_customer_rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            'password' => $passwordRules,
        ]);
    }
}
