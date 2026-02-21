<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 20);

        if (! in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }

        $skills = Skill::query()
            ->withCount('employeeProfiles')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Skill $skill): array => [
                'id' => $skill->id,
                'name' => $skill->name,
                'description' => $skill->description,
                'employee_profiles_count' => (int) $skill->employee_profiles_count,
                'created_at' => $skill->created_at,
            ]);

        return Inertia::render('Admin/Skills/Index', [
            'skills' => $skills,
            'filters' => [
                'q' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:skills,name'],
            'description' => ['nullable', 'string'],
        ]);

        Skill::create($validated);

        return back()->with('status', 'Kompetence oprettet.');
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('skills', 'name')->ignore($skill->id),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $skill->update($validated);

        return back()->with('status', 'Kompetence opdateret.');
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        if ($skill->employeeProfiles()->exists()) {
            return back()->withErrors([
                'skill_delete' => 'Kompetencen kan ikke slettes, fordi den er tildelt medarbejdere.',
            ]);
        }

        $skill->delete();

        return back()->with('status', 'Kompetence slettet.');
    }
}
