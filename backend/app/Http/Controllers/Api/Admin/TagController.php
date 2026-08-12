<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\AuditLogger;
use App\Support\LocalizedName;
use App\Support\LocalizedText;
use App\Support\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        $locale = strtolower(substr((string) request()->header('X-Locale', request()->query('locale', 'el')), 0, 2));

        $tags = Tag::query()->withCount('products')->orderBy('sort_order')->get();
        foreach ($tags as $tag) {
            LocalizedText::applyResolvedForResponse($tag, 'name', $locale);
        }

        return response()->json($tags);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?? Str::slug(LocalizedName::slugSource($data['name']) ?: Str::random(8));

        $tag = Tag::create($data);
        LocalizedText::fillMissingLocales($tag, 'name');
        AuditLogger::log('tag.created', $tag, null, $tag->toArray());

        return response()->json($tag->fresh(), 201);
    }

    public function show(Tag $tag): JsonResponse
    {
        return response()->json($tag->load('products'));
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $old = $tag->toArray();
        $tag->update($this->validated($request, false));
        LocalizedText::fillMissingLocales($tag, 'name');
        AuditLogger::log('tag.updated', $tag, $old, $tag->fresh()->toArray());

        return response()->json($tag->fresh());
    }

    public function destroy(Tag $tag): JsonResponse
    {
        AuditLogger::log('tag.deleted', $tag, $tag->toArray());
        $tag->delete();

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
            'slug' => [
                'nullable',
                'string',
                'max:150',
                TenantRules::unique('tags', 'slug')
                    ->ignore($request->route('tag')?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($data['name'])) {
            LocalizedName::assertPrimaryLocale($data['name'], $creating || array_key_exists('name', $request->all()));
        }

        return $data;
    }
}
