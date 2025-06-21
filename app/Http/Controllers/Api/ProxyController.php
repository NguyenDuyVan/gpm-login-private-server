<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\Proxy;
use App\Services\ProxyService;
use App\Http\Requests\CreateProxyRequest;
use App\Http\Requests\UpdateProxyRequest;
use App\Http\Requests\BulkCreateProxyRequest;

class ProxyController extends BaseController
{
    protected $proxyService;

    public function __construct(ProxyService $proxyService)
    {
        $this->proxyService = $proxyService;
    }


    public function index(Request $request)
    {
        $user = $request->user();
        $filters = [
            'search' => $request->search ?? null,
            'tags' => $request->tags ?? null,
            'status' => $request->status ?? null,
            'per_page' => $request->per_page ?? 30
        ];

        $proxies = $this->proxyService->getProxies($user, $filters);
        return $this->getJsonResponse(true, 'OK', $proxies);
    }


    public function store(CreateProxyRequest $request)
    {
        $user = $request->user();

        $result = $this->proxyService->createProxy(
            $request->raw_proxy,
            $request->status ?? null,
            $user->id,
            $user->id
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkStore(BulkCreateProxyRequest $request)
    {
        $user = $request->user();

        $result = $this->proxyService->bulkCreateProxy(
            $request->proxies ?? [],
            $user->id
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }


    public function show($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->getProxy($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }


    public function update(UpdateProxyRequest $request, $id)
    {
        $user = $request->user();

        $result = $this->proxyService->updateProxy(
            $id,
            $request->raw_proxy,
            $request->status,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->deleteProxy($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], null);
    }


    public function toggleStatus($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->toggleProxyStatus($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function addTags($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->addTagsToProxy($id, $request->tags, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function removeTags($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->removeTagsFromProxy($id, $request->tags, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }


    public function testConnection($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->testProxyConnection($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkShare(Request $request)
    {
        $user = $request->user();

        $result = $this->proxyService->bulkShareProxy(
            $request->proxy_ids,
            $request->user_id,
            $request->role,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function getProxyShares($id)
    {
        $proxyShares = $this->proxyService->getProxyShares($id);
        return $this->getJsonResponse(true, 'OK', $proxyShares);
    }

    public function share($id, Request $request)
    {
        $user = $request->user();

        $result = $this->proxyService->shareProxy(
            $id,
            $request->user_id,
            $request->role,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

}
