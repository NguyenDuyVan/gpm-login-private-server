<?php

namespace App\Services;

use App\Models\Proxy;
use App\Models\ProxyShare;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Log;

class ProxyService
{
    protected $tagService;

    public function __construct(TagService $tagService)
    {
        $this->tagService = $tagService;
    }

    /**
     * Get proxies with filters
     */
    public function getProxies(User $user, array $filters = [])
    {
        $query = Proxy::with(['tags', 'creator', 'updater']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('raw_proxy', 'like', "%{$search}%");
            });
        }

        // Apply tag filter
        if (!empty($filters['tags'])) {
            $tagIds = is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']);
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        // Apply status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply user permissions
        if ($user->system_role !== 'ADMIN') {
            $proxyShareIds = ProxyShare::where('user_id', $user->id)->pluck('proxy_id');

            $query->where(function ($q) use ($user, $proxyShareIds) {
                $q->where('created_by', $user->id)
                    ->orWhereIn('id', $proxyShareIds);
            });
        }

        $perPage = $filters['per_page'] ?? 30;
        return $query->paginate($perPage);
    }

    /**
     * Get proxy by ID
     */
    public function getProxy($id, User $user)
    {
        try {
            $proxy = Proxy::with(['tags', 'creator', 'updater'])->find($id);

            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to view this proxy
            if (!$this->canAccessProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy',
                    'data' => null
                ];
            }

            return [
                'success' => true,
                'message' => 'OK',
                'data' => $proxy
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
     * Create new proxy
     */
    public function createProxy($rawProxy, $status = null, $createdBy = null, $updatedBy = null)
    {
        try {
            $proxy = Proxy::create([
                'raw_proxy' => $rawProxy,
                'status' => $status ?? Proxy::STATUS_ACTIVE,
                'created_by' => $createdBy,
                'updated_by' => $updatedBy ?? $createdBy
            ]);

            return [
                'success' => true,
                'message' => 'ok',
                'data' => $proxy
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create proxy: ' . $e->getMessage(), [
                'raw_proxy' => $rawProxy,
                'created_by' => $createdBy,
                'updated_by' => $updatedBy
            ]);
            return [
                'success' => false,
                'message' => 'error_with_details',
                'data' => ['details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Bulk create proxies
     */
    public function bulkCreateProxy(array $proxiesData, $createdBy = null)
    {
        try {
            $errorProxies = [];
            $successCount = 0;

            foreach ($proxiesData as $index => $proxyData) {
                $result = $this->createProxy(
                    $proxyData['raw_proxy'] ?? null,
                    $proxyData['status'] ?? Proxy::STATUS_ACTIVE,
                    $createdBy,
                    $createdBy
                );

                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorProxies[] = [
                        'index' => $index,
                        'error' => $result['message'],
                        'proxy_data' => $proxyData
                    ];
                }
            }

            $totalCount = count($proxiesData);
            $errorCount = count($errorProxies);

            return [
                'success' => $successCount > 0,
                'message' => $successCount === $totalCount ? 'all_proxies_created' :
                    ($successCount > 0 ? 'partial_proxies_created' : 'no_proxies_created'),
                'data' => [
                    'created_count' => $successCount,
                    'total_count' => $totalCount,
                    'error_count' => $errorCount,
                    'errors' => $errorProxies
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'bulk_create_failed',
                'data' => ['details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Update proxy
     */
    public function updateProxy($id, $rawProxy, $status, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to update this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_edit',
                    'data' => null
                ];
            }

            $updateData = [
                'raw_proxy' => $rawProxy,
                'status' => $status,
                'updated_by' => $user->id
            ];

            $proxy->update($updateData);

            return [
                'success' => true,
                'message' => 'proxy_updated',
                'data' => $proxy->load(['tags', 'creator', 'updater'])
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
     * Delete proxy
     */
    public function deleteProxy($id, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to delete this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_delete',
                    'data' => null
                ];
            }

            // Remove all tag associations
            $proxy->tags()->detach();

            $proxy->delete();

            return [
                'success' => true,
                'message' => 'proxy_deleted',
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
     * Toggle proxy status
     */
    public function toggleProxyStatus($id, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_status',
                    'data' => null
                ];
            }

            $newStatus = $proxy->status === Proxy::STATUS_ACTIVE ? Proxy::STATUS_INACTIVE : Proxy::STATUS_ACTIVE;
            $proxy->update([
                'status' => $newStatus,
                'updated_by' => $user->id
            ]);

            return [
                'success' => true,
                'message' => 'proxy_status_updated',
                'data' => $proxy->load(['tags', 'creator', 'updater'])
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
     * Add tags to proxy
     */
    public function addTagsToProxy($id, array $tagNames, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_tags',
                    'data' => null
                ];
            }

            $tags = $this->tagService->findOrCreateTags($tagNames, $user->id);
            $tagIds = collect($tags)->pluck('id')->toArray();

            $proxy->tags()->syncWithoutDetaching($tagIds);
            $proxy->update(['updated_by' => $user->id]);

            return [
                'success' => true,
                'message' => 'ok',
                'data' => $proxy->load(['tags', 'creator', 'updater'])
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
     * Remove tags from proxy
     */
    public function removeTagsFromProxy($id, array $tagIds, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_remove_tags',
                    'data' => null
                ];
            }

            $proxy->tags()->detach($tagIds);
            $proxy->update(['updated_by' => $user->id]);

            return [
                'success' => true,
                'message' => 'ok',
                'data' => $proxy->load(['tags', 'creator', 'updater'])
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
     * Test proxy connection
     */
    public function testProxyConnection($id, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to test this proxy
            if (!$this->canAccessProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_test',
                    'data' => null
                ];
            }

            // Build proxy URL from raw_proxy
            $proxyUrl = $proxy->connection_string;

            // If no valid connection string, try raw proxy directly
            if (empty($proxyUrl) || $proxyUrl === $proxy->raw_proxy) {
                $proxyUrl = $proxy->raw_proxy;
                // If it doesn't start with a protocol, assume HTTP
                if (!preg_match('/^https?:\/\//', $proxyUrl) && !preg_match('/^socks[45]:\/\//', $proxyUrl)) {
                    $proxyUrl = 'http://' . $proxyUrl;
                }
            }

            // Test connection with a simple HTTP request
            $startTime = microtime(true);

            try {
                $response = Http::timeout(10)->withOptions([
                    'proxy' => $proxyUrl,
                    'verify' => false
                ])->get('http://httpbin.org/ip');

                $endTime = microtime(true);
                $responseTime = round(($endTime - $startTime) * 1000, 2);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'message' => 'ok',
                        'data' => [
                            'response_time' => $responseTime . 'ms',
                            'ip' => $response->json('origin') ?? 'Unknown'
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'proxy_connection_failed',
                        'data' => null
                    ];
                }
            } catch (\Exception $httpException) {
                return [
                    'success' => false,
                    'message' => 'proxy_connection_error',
                    'data' => ['details' => $httpException->getMessage()]
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'error_with_details',
                'data' => ['details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Get proxy shares
     *
     * @param int $proxyId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProxyShares(int $proxyId)
    {
        return ProxyShare::where('proxy_id', $proxyId)
            ->with(['proxy', 'user'])
            ->get();
    }

    /**
     * Share proxy with user
     *
     * @param int $proxyId
     * @param int $userId
     * @param string $role
     * @param User $currentUser
     * @return array
     */
    public function shareProxy(int $proxyId, int $userId, string $role, User $currentUser)
    {
        // Validate shared user
        $sharedUser = User::find($userId);
        if ($sharedUser == null) {
            return ['success' => false, 'message' => 'user_not_found', 'data' => null];
        }

        if ($sharedUser->isAdmin()) {
            return ['success' => false, 'message' => 'no_need_set_admin_permission', 'data' => null];
        }

        // Validate proxy
        $proxy = Proxy::find($proxyId);
        if ($proxy == null) {
            return ['success' => false, 'message' => 'proxy_not_found', 'data' => null];
        }

        // Check permission
        if (!$currentUser->isAdmin() && $proxy->created_by != $currentUser->id) {
            return ['success' => false, 'message' => 'owner_required', 'data' => null];
        }

        // Handle proxy share
        $proxyShare = ProxyShare::where('proxy_id', $proxyId)
            ->where('user_id', $userId)
            ->first();

        // If role is empty or invalid, remove the share
        if (empty($role) || !in_array($role, [ProxyShare::ROLE_FULL, ProxyShare::ROLE_EDIT, ProxyShare::ROLE_VIEW])) {
            if ($proxyShare != null) {
                $proxyShare->delete();
            }
            return ['success' => true, 'message' => 'ok', 'data' => null];
        }

        // Create or update share
        if ($proxyShare == null) {
            $proxyShare = new ProxyShare();
        }

        $proxyShare->proxy_id = $proxyId;
        $proxyShare->user_id = $userId;
        $proxyShare->role = $role;
        $proxyShare->save();

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }

    /**
     * Bulk share proxies with user
     *
     * @param array $proxyIds
     * @param int $userId
     * @param string $role
     * @param User $currentUser
     * @return array
     */
    public function bulkShareProxy(array $proxyIds, int $userId, string $role, User $currentUser)
    {
        // Validate proxies

        $count = 0;
        foreach ($proxyIds as $id) {
            $result = $this->shareProxy($id, $userId, $role, $currentUser);
            if ($result['success']) {
                $count++;
            }
        }

        return [
            'success' => true,
            'message' => 'ok',
            'data' => [
                'shared_count' => $count,
                'total_proxies' => count($proxyIds)
            ]
        ];
    }

    /**
     * Check if user can access proxy
     */
    private function canAccessProxy(User $user, Proxy $proxy)
    {
        // Admin can access any proxy
        if ($user->system_role === 'ADMIN') {
            return true;
        }

        // User can access proxies they created
        if ($proxy->created_by === $user->id) {
            return true;
        }

        // Check proxy shares
        $proxyShare = ProxyShare::where('user_id', $user->id)
            ->where('proxy_id', $proxy->id)
            ->first();

        return $proxyShare !== null;
    }

    /**
     * Check if user can manage proxy
     */
    private function canManageProxy(User $user, Proxy $proxy)
    {
        // Admin can manage any proxy
        if ($user->system_role === 'ADMIN') {
            return true;
        }

        // User can manage proxies they created
        if ($proxy->created_by === $user->id) {
            return true;
        }

        // Check proxy shares with FULL access
        $proxyShare = ProxyShare::where('user_id', $user->id)
            ->where('proxy_id', $proxy->id)
            ->where('role', ProxyShare::ROLE_FULL)
            ->first();

        return $proxyShare !== null;
    }
}