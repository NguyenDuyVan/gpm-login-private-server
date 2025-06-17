<?php

namespace App\Services;

use App\Models\Proxy;
use App\Models\User;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
        $query = Proxy::with(['tags', 'creator']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('host', 'like', "%{$search}%");
            });
        }

        // Apply tag filter
        if (!empty($filters['tags'])) {
            $tagIds = is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']);
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        // Apply active status filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Apply user permissions
        if ($user->system_role !== 'ADMIN') {
            $query->where('created_by', $user->id);
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
            $proxy = Proxy::with(['tags', 'creator'])->find($id);

            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'Proxy không tồn tại',
                    'data' => null
                ];
            }

            // Check if user has permission to view this proxy
            if (!$this->canAccessProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền truy cập proxy này',
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
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Create new proxy
     */
    public function createProxy($name, $host, $port, $username = null, $password = null, $type = 'HTTP', $createdBy = null)
    {
        try {
            $proxy = Proxy::create([
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'type' => $type,
                'is_active' => true,
                'created_by' => $createdBy
            ]);

            return [
                'success' => true,
                'message' => 'Tạo proxy thành công',
                'data' => $proxy->load(['tags', 'creator'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Update proxy
     */
    public function updateProxy($id, $name, $host, $port, User $user, $username = null, $password = null, $type = null)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'Proxy không tồn tại',
                    'data' => null
                ];
            }

            // Check if user has permission to update this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật proxy này',
                    'data' => null
                ];
            }

            $updateData = [
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password
            ];

            if ($type !== null) {
                $updateData['type'] = $type;
            }

            $proxy->update($updateData);

            return [
                'success' => true,
                'message' => 'Cập nhật proxy thành công',
                'data' => $proxy->load(['tags', 'creator'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
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
                    'message' => 'Proxy không tồn tại',
                    'data' => null
                ];
            }

            // Check if user has permission to delete this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa proxy này',
                    'data' => null
                ];
            }

            // Remove all tag associations
            $proxy->tags()->detach();

            $proxy->delete();

            return [
                'success' => true,
                'message' => 'Xóa proxy thành công',
                'data' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
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
                    'message' => 'Proxy không tồn tại',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền thay đổi trạng thái proxy này',
                    'data' => null
                ];
            }

            $proxy->update(['is_active' => !$proxy->is_active]);

            return [
                'success' => true,
                'message' => 'Cập nhật trạng thái proxy thành công',
                'data' => $proxy->load(['tags', 'creator'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
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
                    'message' => 'Proxy không tồn tại',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền thêm tag cho proxy này',
                    'data' => null
                ];
            }

            $tags = $this->tagService->findOrCreateTags($tagNames, $user->id);
            $tagIds = collect($tags)->pluck('id')->toArray();

            $proxy->tags()->syncWithoutDetaching($tagIds);

            return [
                'success' => true,
                'message' => 'Thêm tag thành công',
                'data' => $proxy->load(['tags', 'creator'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
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
                    'message' => 'Proxy không tồn tại',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            if (!$this->canManageProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa tag khỏi proxy này',
                    'data' => null
                ];
            }

            $proxy->tags()->detach($tagIds);

            return [
                'success' => true,
                'message' => 'Xóa tag thành công',
                'data' => $proxy->load(['tags', 'creator'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
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
                    'message' => 'Proxy không tồn tại',
                    'data' => null
                ];
            }

            // Check if user has permission to test this proxy
            if (!$this->canAccessProxy($user, $proxy)) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền test proxy này',
                    'data' => null
                ];
            }

            // Build proxy URL
            $proxyUrl = $proxy->type . '://';
            if ($proxy->username && $proxy->password) {
                $proxyUrl .= $proxy->username . ':' . $proxy->password . '@';
            }
            $proxyUrl .= $proxy->host . ':' . $proxy->port;

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
                        'message' => 'Proxy hoạt động bình thường',
                        'data' => [
                            'response_time' => $responseTime . 'ms',
                            'ip' => $response->json('origin') ?? 'Unknown'
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Proxy không thể kết nối (HTTP ' . $response->status() . ')',
                        'data' => null
                    ];
                }
            } catch (\Exception $httpException) {
                return [
                    'success' => false,
                    'message' => 'Không thể kết nối đến proxy: ' . $httpException->getMessage(),
                    'data' => null
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
            ];
        }
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

        // User can only access proxies they created
        return $proxy->created_by === $user->id;
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

        // User can only manage proxies they created
        return $proxy->created_by === $user->id;
    }
}
