<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MultiTenantSaas\Contracts\IdGeneratorContract;

/**
 * Review Console 面权限点（提升归位时补发，2026-10-22）。
 *
 * - 待审列表（GET）→ `review.view`
 * - 审核通过/驳回（POST）→ `review.manage`
 *
 * 全新安装由 PermissionSeeder/RolePermissionSeeder 覆盖；本迁移负责存量库升级，
 * 必须幂等。模块可能先于 RBAC 表存在（最小安装）——表不在就跳过。
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['name' => 'review.view', 'display_name' => '查看评价审核', 'group' => 'review'],
        ['name' => 'review.manage', 'display_name' => '管理评价审核', 'group' => 'review'],
    ];

    private const MANAGE_ROLES = ['super_admin', 'platform_admin', 'tenant_admin'];

    private const VIEW_ROLES = ['platform_support', 'member', 'viewer', 'analyst'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $idGen = app(IdGeneratorContract::class);
        $now = now();

        foreach (self::PERMISSIONS as $perm) {
            $permId = DB::table('permissions')->where('name', $perm['name'])->value('permission_id');

            if (! $permId) {
                $permId = $idGen->generate();

                DB::table('permissions')->insert([
                    'permission_id' => $permId,
                    'name' => $perm['name'],
                    'display_name' => $perm['display_name'],
                    'group' => $perm['group'],
                    'description' => $perm['display_name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $roles = str_ends_with($perm['name'], '.view')
                ? array_merge(self::MANAGE_ROLES, self::VIEW_ROLES)
                : self::MANAGE_ROLES;

            $roleIds = DB::table('roles')
                ->whereIn('name', $roles)
                ->whereNull('tenant_id')
                ->pluck('role_id')
                ->all();

            foreach ($roleIds as $roleId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => (int) $roleId,
                    'permission_id' => (int) $permId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permIds = DB::table('permissions')
            ->whereIn('name', array_column(self::PERMISSIONS, 'name'))
            ->pluck('permission_id')
            ->all();

        if ($permIds === []) {
            return;
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission_id', $permIds)->delete();
        }

        DB::table('permissions')->whereIn('permission_id', $permIds)->delete();
    }
};
