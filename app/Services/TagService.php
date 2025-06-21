<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TagService
{
    /**
     * Get all tags with pagination and search
     */
    public function getAllTags(array $filters = [])
    {
        $query = Tag::query();

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Default ordering
        $query->orderBy('name');

        $perPage = $filters['per_page'];
        $result = $query->paginate($perPage);

        // Return all results if no pagination
        return [
            'success' => false,
            'message' => 'ok',
            'data' => $result
        ];
    }

    /**
     * Get tag by ID
     */
    public function getTag($id)
    {
        return Tag::find($id);
    }

    /**
     * Create new tag
     */
    public function createTag($name, $color = '#007bff', $createdBy = null)
    {
        try {
            // Check if tag with same name already exists
            $existingTag = Tag::where('name', $name)->first();
            if ($existingTag) {
                return [
                    'success' => false,
                    'message' => 'tag_name_exists',
                    'data' => null
                ];
            }

            $tag = Tag::create([
                'name' => $name,
                'color' => $color,
                'created_by' => $createdBy
            ]);

            return [
                'success' => true,
                'message' => 'tag_created',
                'data' => $tag
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'error_with_details',
                'data' => ['details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Update tag
     */
    public function updateTag($id, $name, $color, User $user)
    {
        try {
            $tag = Tag::find($id);
            if (!$tag) {
                return [
                    'success' => false,
                    'message' => 'tag_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to update this tag
            if (!$this->canManageTag($user, $tag)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_tag_edit',
                    'data' => null
                ];
            }

            // Check if another tag with same name already exists
            $existingTag = Tag::where('name', $name)->where('id', '!=', $id)->first();
            if ($existingTag) {
                return [
                    'success' => false,
                    'message' => 'tag_name_exists',
                    'data' => null
                ];
            }

            $tag->update([
                'name' => $name,
                'color' => $color
            ]);

            return [
                'success' => true,
                'message' => 'tag_updated',
                'data' => $tag
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'error_with_details',
                'data' => ['details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Delete tag
     */
    public function deleteTag($id, User $user)
    {
        try {
            $tag = Tag::find($id);
            if (!$tag) {
                return [
                    'success' => false,
                    'message' => 'tag_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to delete this tag
            if (!$this->canManageTag($user, $tag)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_tag_delete',
                    'data' => null
                ];
            }

            // Check if tag is being used by any profiles or proxies
            $profileTagsCount = $tag->profileTags()->count();
            $proxyTagsCount = $tag->proxyTags()->count();

            if ($profileTagsCount > 0 || $proxyTagsCount > 0) {
                return [
                    'success' => false,
                    'message' => 'tag_in_use',
                    'data' => null
                ];
            }

            $tag->delete();

            return [
                'success' => true,
                'message' => 'tag_deleted',
                'data' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'error_with_details',
                'data' => ['details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Get tags with profile count
     */
    public function getTagsWithProfileCount()
    {
        return Tag::withCount('profileTags')
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if user can manage tag
     */
    private function canManageTag(User $user, Tag $tag)
    {
        // Admin and mod can manage any tag
        if (in_array($user->system_role, ['ADMIN', 'MOD'])) {
            return true;
        }

        // User can only manage tags they created
        return $tag->created_by === $user->id;
    }

    /**
     * Find or create tags by names
     */
    public function findOrCreateTags(array $tagNames, $createdBy = null)
    {
        $tags = [];

        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(
                ['name' => trim($tagName)],
                [
                    'color' => '#007bff',
                    'created_by' => $createdBy
                ]
            );
            $tags[] = $tag;
        }

        return $tags;
    }
}
