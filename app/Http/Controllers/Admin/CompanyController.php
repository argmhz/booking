<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $perPage = (int) $request->query('per_page', 15);

        if (! in_array($perPage, [15, 25, 50], true)) {
            $perPage = 15;
        }

        $companies = Company::query()
            ->with(['addresses' => fn ($query) => $query->select([
                'id',
                'company_id',
                'label',
                'address_line_1',
                'address_line_2',
                'postal_code',
                'city',
                'country',
                'is_default',
            ])->orderByDesc('is_default')->orderBy('label')])
            ->with(['users' => fn ($query) => $query
                ->select(['users.id', 'users.name', 'users.email', 'users.is_active'])
                ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'company'))
                ->orderBy('users.name')])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $like = '%'.$search.'%';

                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('cvr', 'like', $like);
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters' => [
                'q' => $search,
                'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCompany($request);

        $company = Company::create(collect($validated)->except(['addresses'])->all());
        $this->syncAddresses($company, $validated['addresses'] ?? []);

        return back()->with('status', 'Virksomhed oprettet.');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $this->validateCompany($request);

        $company->update(collect($validated)->except(['addresses'])->all());
        $this->syncAddresses($company, $validated['addresses'] ?? []);

        return back()->with('status', 'Virksomhed opdateret.');
    }

    public function toggleActive(Company $company): RedirectResponse
    {
        $company->update([
            'is_active' => ! $company->is_active,
        ]);

        return back()->with('status', $company->is_active ? 'Virksomhed aktiveret.' : 'Virksomhed deaktiveret.');
    }

    public function attachUser(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        Role::findOrCreate('company', 'web');

        if (! $user) {
            if (empty($validated['password'])) {
                return back()->withErrors([
                    'email' => 'Password er påkrævet for at oprette en ny virksomhedsbruger.',
                ]);
            }

            $user = User::create([
                'name' => trim((string) ($validated['name'] ?? '')) ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make($validated['password']),
                'locale' => 'da',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        } elseif (! empty($validated['password'])) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        if (! $user->hasRole('company')) {
            $user->assignRole('company');
        }

        $company->users()->syncWithoutDetaching([$user->id]);

        return back()->with('status', 'Virksomhedsbruger tilknyttet.');
    }

    public function detachUser(Company $company, User $user): RedirectResponse
    {
        if (! $company->users()->where('users.id', $user->id)->exists()) {
            abort(404);
        }

        $company->users()->detach($user->id);

        return back()->with('status', 'Virksomhedsbruger frakoblet.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $hasBookings = $company->bookings()->exists();
        $hasUsers = User::query()
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $company->id))
            ->exists();

        if ($hasBookings || $hasUsers) {
            return back()->withErrors([
                'company_delete' => 'Virksomheden kan ikke slettes, fordi den har tilknytninger.',
            ]);
        }

        $company->delete();

        return back()->with('status', 'Virksomhed slettet.');
    }

    private function validateCompany(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'cvr' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
            'addresses' => ['nullable', 'array', 'max:50'],
            'addresses.*.id' => ['nullable', 'integer'],
            'addresses.*.label' => ['nullable', 'string', 'max:120'],
            'addresses.*.address_line_1' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'addresses.*.city' => ['nullable', 'string', 'max:120'],
            'addresses.*.country' => ['nullable', 'string', 'max:120'],
            'addresses.*.is_default' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $addresses
     */
    private function syncAddresses(Company $company, array $addresses): void
    {
        $existingIds = $company->addresses()->pluck('id')->all();
        $defaultApplied = false;
        $keptIds = [];

        foreach ($addresses as $rawAddress) {
            $label = trim((string) ($rawAddress['label'] ?? ''));
            $line1 = trim((string) ($rawAddress['address_line_1'] ?? ''));
            $city = trim((string) ($rawAddress['city'] ?? ''));

            if ($label === '' || $line1 === '' || $city === '') {
                continue;
            }

            $isDefault = (bool) ($rawAddress['is_default'] ?? false);

            if ($defaultApplied) {
                $isDefault = false;
            }

            if ($isDefault) {
                $defaultApplied = true;
            }

            $payload = [
                'label' => $label,
                'address_line_1' => $line1,
                'address_line_2' => $this->normalizeNullableText($rawAddress['address_line_2'] ?? null),
                'postal_code' => $this->normalizeNullableText($rawAddress['postal_code'] ?? null),
                'city' => $city,
                'country' => $this->normalizeNullableText($rawAddress['country'] ?? null) ?? 'Denmark',
                'is_default' => $isDefault,
            ];

            $id = isset($rawAddress['id']) ? (int) $rawAddress['id'] : null;

            if ($id && in_array($id, $existingIds, true)) {
                CompanyAddress::query()
                    ->where('company_id', $company->id)
                    ->where('id', $id)
                    ->update($payload);

                $keptIds[] = $id;
                continue;
            }

            $created = $company->addresses()->create($payload);
            $keptIds[] = $created->id;
        }

        if ($keptIds === []) {
            $company->addresses()->delete();

            return;
        }

        $company->addresses()
            ->whereNotIn('id', $keptIds)
            ->delete();

        if (! $defaultApplied) {
            $firstId = $company->addresses()
                ->whereIn('id', $keptIds)
                ->orderBy('id')
                ->value('id');

            if ($firstId) {
                $company->addresses()->whereIn('id', $keptIds)->update(['is_default' => false]);
                $company->addresses()->where('id', $firstId)->update(['is_default' => true]);
            }
        }
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
