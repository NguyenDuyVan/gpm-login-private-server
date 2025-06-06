# Controllers and API Updates - Database Schema Migration

## Summary

This document outlines the controller and API updates that have been completed as part of the comprehensive database schema migration. The updates ensure that all controllers work properly with the new database structure and provide enhanced functionality.

## Completed Updates

### 1. API Controllers Updated

#### AuthController

-   **Updated**: Login method now uses `email` instead of `user_name`
-   **Change**: `$request->user_name` → `$request->email`

#### UserController

-   **Updated**: User creation and update methods
-   **Changes**:
    -   Create user: `$request->user_name` → `$request->email`
    -   Update user: `$request->role` → `$request->system_role`

#### GroupController

-   **Updated**: Model imports and method signatures
-   **Changes**:
    -   Import: `GroupRole` → `GroupShare`
    -   Create/Update: `$request->sort` → `$request->order`
    -   Method: `getGroupRoles()` → `getGroupShares()`

#### ProfileController

-   **Updated**: Create and update methods with new fields
-   **Changes**:
    -   Create: Added `storage_type` parameter, `s3_path` → `storage_path`
    -   Update: Simplified parameters, removed deprecated fields
    -   Method: `getProfileRoles()` → `getProfileShares()`
-   **Added**: New methods for enhanced functionality:
    -   `startUsing()` - Track profile usage
    -   `stopUsing()` - Stop tracking usage
    -   `addTags()` - Add tags to profiles
    -   `removeTags()` - Remove tags from profiles
    -   `restore()` - Restore soft-deleted profiles

### 2. New Controllers Created

#### TagController

-   **Purpose**: Manage tag entities
-   **Methods**:
    -   `index()` - List all tags
    -   `store()` - Create new tag
    -   `show()` - Get tag details
    -   `update()` - Update tag
    -   `destroy()` - Delete tag
    -   `getTagsWithCount()` - Get tags with profile count

#### ProxyController

-   **Purpose**: Manage proxy entities
-   **Methods**:
    -   `index()` - List proxies with filters
    -   `store()` - Create new proxy
    -   `show()` - Get proxy details
    -   `update()` - Update proxy
    -   `destroy()` - Delete proxy
    -   `toggleStatus()` - Toggle proxy active status
    -   `addTags()` - Add tags to proxy
    -   `removeTags()` - Remove tags from proxy
    -   `testConnection()` - Test proxy connectivity

### 3. New Services Created

#### TagService

-   **Features**:
    -   CRUD operations for tags
    -   Permission checking (users can only manage their own tags, admins can manage all)
    -   Usage validation (prevent deletion of tags in use)
    -   Bulk tag creation with `findOrCreateTags()`

#### ProxyService

-   **Features**:
    -   CRUD operations for proxies
    -   Advanced filtering (search, tags, status)
    -   Permission-based access control
    -   Tag management integration
    -   Connection testing functionality
    -   HTTP proxy support with authentication

### 4. API Routes Updated

#### Updated Routes

-   Groups: `/api/groups/roles/{id}` → `/api/groups/shares/{id}`
-   Profiles: `/api/profiles/roles/{id}` → `/api/profiles/shares/{id}`

#### New Routes Added

**Tags**:

-   `GET /api/tags` - List tags
-   `GET /api/tags/with-count` - List tags with usage count
-   `GET /api/tags/{id}` - Get tag details
-   `POST /api/tags/create` - Create tag
-   `POST /api/tags/update/{id}` - Update tag
-   `GET /api/tags/delete/{id}` - Delete tag

**Proxies**:

-   `GET /api/proxies` - List proxies
-   `GET /api/proxies/{id}` - Get proxy details
-   `POST /api/proxies/create` - Create proxy
-   `POST /api/proxies/update/{id}` - Update proxy
-   `GET /api/proxies/delete/{id}` - Delete proxy
-   `POST /api/proxies/toggle-status/{id}` - Toggle status
-   `POST /api/proxies/add-tags/{id}` - Add tags
-   `POST /api/proxies/remove-tags/{id}` - Remove tags
-   `POST /api/proxies/test-connection/{id}` - Test connection

**Enhanced Profile Routes**:

-   `POST /api/profiles/start-using/{id}` - Start using profile
-   `POST /api/profiles/stop-using/{id}` - Stop using profile
-   `POST /api/profiles/add-tags/{id}` - Add tags to profile
-   `POST /api/profiles/remove-tags/{id}` - Remove tags from profile
-   `POST /api/profiles/restore/{id}` - Restore deleted profile

## Permission System

### Role Hierarchy

1. **System Roles** (users.system_role):

    - `ADMIN` - Full system access
    - `MOD` - Moderate access
    - `USER` - Basic access

2. **Share Roles** (group_shares.role, profile_shares.role):
    - `FULL` - Complete management access
    - `EDIT` - Modification access
    - `VIEW` - Read-only access

### Access Control

-   **Admins**: Can manage all resources
-   **Moderators**: Can manage tags and have elevated permissions
-   **Users**: Can only manage resources they own or have been granted access to

## New Features Enabled

### Profile Management

-   **Usage Tracking**: Track when profiles are being used and by whom
-   **Soft Deletes**: Profiles can be soft-deleted and restored
-   **Tag System**: Organize profiles with colored tags
-   **Storage Types**: Support for different storage backends (S3, Local, etc.)
-   **Metadata Storage**: Store additional profile metadata

### Proxy Management

-   **Connection Testing**: Test proxy connectivity before use
-   **Tag Organization**: Organize proxies with tags
-   **Status Management**: Enable/disable proxies
-   **Multiple Proxy Types**: Support HTTP, HTTPS, SOCKS proxies
-   **Authentication**: Support username/password authentication

### Enhanced Sharing

-   **Granular Permissions**: Fine-grained role-based access control
-   **Share Management**: Improved sharing interface with role management
-   **Audit Trail**: Track who has access to what resources

## Backward Compatibility

To maintain compatibility, the services include backward compatibility methods:

-   `getGroupRoles()` → maps to `getGroupShares()`
-   `getProfileRoles()` → maps to `getProfileShares()`

## Testing Status

✅ **Application Boot**: Confirmed application boots successfully with all changes
✅ **Route Registration**: All new routes properly registered
✅ **Controller Syntax**: All controllers pass syntax validation
✅ **Service Integration**: Services properly integrated with dependency injection

## Next Steps

1. **Frontend Updates**: Update frontend components to use new API endpoints
2. **Data Validation**: Add comprehensive request validation
3. **API Documentation**: Update API documentation with new endpoints
4. **Testing**: Create automated tests for new functionality
5. **Migration Guide**: Create migration guide for existing API consumers

## Files Modified/Created

### Controllers

-   `app/Http/Controllers/Api/AuthController.php` (updated)
-   `app/Http/Controllers/Api/UserController.php` (updated)
-   `app/Http/Controllers/Api/GroupController.php` (updated)
-   `app/Http/Controllers/Api/ProfileController.php` (updated)
-   `app/Http/Controllers/Api/TagController.php` (created)
-   `app/Http/Controllers/Api/ProxyController.php` (created)

### Services

-   `app/Services/TagService.php` (created)
-   `app/Services/ProxyService.php` (created)

### Routes

-   `routes/api.php` (updated)

The database schema migration is now complete with full controller and API support for all new features!
