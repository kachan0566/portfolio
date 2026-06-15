<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function index(): View
    {
        $materials = Material::orderBy('id')->get();

        return view('materials.index', compact('materials'));
    }

    public function create(): View
    {
        return view('materials.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
        ]);

        Material::create($validated);

        return redirect()
            ->route('materials.index')
            ->with('success', '原材料を登録しました。');
    }

    public function edit(Material $material): View
    {
        return view('materials.edit', compact('material'));
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
        ]);

        $material->update($validated);

        return redirect()
            ->route('materials.index')
            ->with('success', '原材料を更新しました。');
    }

    public function destroy(Material $material): RedirectResponse
    {
        $material->delete();

        return redirect()
            ->route('materials.index')
            ->with('success', '原材料を削除しました。');
    }
}
