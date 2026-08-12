<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Support\LocalizedName;
use App\Support\LocalizedText;
use App\Support\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $locale = strtolower(substr((string) request()->header('X-Locale', request()->query('locale', 'el')), 0, 2));

        $categories = Category::query()->withCount('products')->orderBy('sort_order')->get();
        foreach ($categories as $category) {
            LocalizedText::applyResolvedForResponse($category, 'name', $locale);
            LocalizedText::applyResolvedForResponse($category, 'description', $locale);
        }

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?? Str::slug(LocalizedName::slugSource($data['name']) ?: Str::random(8));

        $category = Category::create($data);
        LocalizedText::fillMissingLocales($category, 'name');
        LocalizedText::fillMissingLocales($category, 'description');
        AuditLogger::log('category.created', $category, null, $category->toArray());

        return response()->json($category->fresh(), 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category->load('products'));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $old = $category->toArray();
        $category->update($this->validated($request, false));
        LocalizedText::fillMissingLocales($category, 'name');
        LocalizedText::fillMissingLocales($category, 'description');
        AuditLogger::log('category.updated', $category, $old, $category->fresh()->toArray());

        return response()->json($category->fresh());
    }

    public function destroy(Category $category): JsonResponse
    {
        AuditLogger::log('category.deleted', $category, $category->toArray());
        $category->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $data = $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'array'],
            'name.el' => ['nullable', 'string', 'max:120'],
            'name.en' => ['nullable', 'string', 'max:120'],
            'name.it' => ['nullable', 'string', 'max:120'],
            'name.de' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'array'],
            'slug' => [
                'nullable',
                'string',
                'max:150',
                TenantRules::unique('categories', 'slug')
                    ->ignore($request->route('category')?->id),
            ],
            'image_path' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($data['name'])) {
            LocalizedName::assertPrimaryLocale($data['name'], $creating || array_key_exists('name', $request->all()));
        }

        return $data;
    }
}
