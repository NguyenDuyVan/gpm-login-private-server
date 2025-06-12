<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\Proxy;
use App\Services\ProxyService;

class ProxyController extends BaseController
{
    protected $proxyService;

    public function __construct(ProxyService $proxyService)
    {
        $this->proxyService = $proxyService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = [
            'search' => $request->search ?? null,
            'tags' => $request->tags ?? null,
            'is_active' => $request->is_active ?? null,
            'per_page' => $request->per_page ?? 30
        ];

        $proxies = $this->proxyService->getProxies($user, $filters);
        return $this->getJsonResponse(true, 'OK', $proxies);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $result = $this->proxyService->createProxy(
            $request->name,
            $request->host,
            $request->port,
            $request->username ?? null,
            $request->password ?? null,
            $request->type ?? 'HTTP',
            $user->id
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->getProxy($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $result = $this->proxyService->updateProxy(
            $id,
            $request->name,
            $request->host,
            $request->port,
            $request->username ?? null,
            $request->password ?? null,
            $request->type ?? null,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->deleteProxy($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], null);
    }

    /**
     * Toggle proxy status
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function toggleStatus($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->toggleProxyStatus($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Add tags to proxy
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addTags($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->addTagsToProxy($id, $request->tags, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Remove tags from proxy
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function removeTags($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->removeTagsFromProxy($id, $request->tags, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Test proxy connection
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function testConnection($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->testProxyConnection($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }
}
