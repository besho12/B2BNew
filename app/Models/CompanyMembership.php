<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyMembership extends Model
{
    private const PERMISSION_GROUPS = [
        'General Permissions' => [
            'view_team' => 'View BP users',
            'manage_members' => 'Manage BP users',
            'view_product_catalog' => 'View product catalogue',
            'create_quick_orders' => 'Create quick orders',
            'manage_basket' => 'Manage basket',
            'view_account_details' => 'View account details',
            'view_address_book' => 'View address book',
            'view_inventory' => 'View inventory',
            'manage_inventory' => 'Manage inventory',
            'view_own_leads' => 'View assigned leads',
            'view_all_leads' => 'View all leads',
            'create_leads' => 'Create leads',
            'update_own_leads' => 'Update assigned leads',
            'update_leads' => 'Update all leads',
            'archive_leads' => 'Archive leads',
            'reassign_leads' => 'Reassign leads',
            'manage_own_tasks' => 'Manage own tasks',
            'manage_tasks' => 'Manage all tasks',
            'view_own_support_cases' => 'View own support cases',
            'view_all_support_cases' => 'View all support cases',
            'create_support_cases' => 'Create support cases',
            'reply_support_cases' => 'Reply to support cases',
            'manage_support_cases' => 'Manage support cases',
            'manage_settings' => 'Manage workspace settings',
        ],
        'Sales Permissions' => [
            'view_sales_orders' => 'View sales order history',
            'view_own_deals' => 'View own deals',
            'view_deals' => 'View all deals',
            'create_deals' => 'Create deals',
            'update_own_deals' => 'Update own deals',
            'manage_deals' => 'Manage all deals',
            'view_own_reports' => 'View own reports',
            'view_reports' => 'View company reports',
            'export_data' => 'Export company data',
            'view_own_commissions' => 'View own commissions',
            'view_commissions' => 'View all commissions',
            'manage_commissions' => 'Manage commissions',
        ],
    ];

    protected $fillable = ['company_id', 'user_id', 'role', 'status', 'permissions', 'external_account_status', 'invited_at', 'joined_at'];

    protected $casts = [
        'permissions' => 'array',
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function can(string $permission): bool
    {
        if (in_array($this->role, ['owner', 'admin'], true)) {
            return true;
        }

        return in_array($permission, $this->effectivePermissions(), true);
    }

    public static function permissionGroups(): array
    {
        return self::PERMISSION_GROUPS;
    }

    public static function permissionKeys(): array
    {
        return array_keys(array_merge(...array_values(self::PERMISSION_GROUPS)));
    }

    public function effectivePermissions(): array
    {
        if (in_array($this->role, ['owner', 'admin'], true)) {
            return self::permissionKeys();
        }

        if ($this->permissions !== null) {
            return array_values(array_intersect(self::permissionKeys(), array_unique($this->permissions)));
        }

        $defaults = [
            'manager' => ['view_team', 'view_product_catalog', 'create_quick_orders', 'manage_basket', 'view_sales_orders', 'view_account_details', 'view_address_book', 'view_inventory', 'manage_inventory', 'view_all_leads', 'create_leads', 'update_leads', 'archive_leads', 'reassign_leads', 'manage_tasks', 'view_deals', 'manage_deals', 'view_reports', 'view_commissions', 'view_all_support_cases', 'create_support_cases', 'manage_support_cases', 'reply_support_cases'],
            'agent' => ['view_team', 'view_product_catalog', 'create_quick_orders', 'manage_basket', 'view_sales_orders', 'view_account_details', 'view_address_book', 'view_inventory', 'view_own_leads', 'create_leads', 'update_own_leads', 'manage_own_tasks', 'view_own_deals', 'create_deals', 'update_own_deals', 'view_own_reports', 'view_own_commissions', 'view_own_support_cases', 'create_support_cases', 'reply_support_cases'],
            'operations' => ['view_team', 'view_product_catalog', 'create_quick_orders', 'manage_basket', 'view_sales_orders', 'view_account_details', 'view_address_book', 'view_inventory', 'manage_inventory', 'view_all_leads', 'create_leads', 'update_leads', 'archive_leads', 'manage_tasks', 'view_deals', 'manage_deals', 'export_data', 'view_all_support_cases', 'create_support_cases', 'manage_support_cases', 'reply_support_cases'],
            'finance' => ['view_team', 'view_product_catalog', 'manage_basket', 'view_sales_orders', 'view_account_details', 'view_address_book', 'view_deals', 'view_commissions', 'manage_commissions', 'view_reports', 'view_all_support_cases'],
        ];

        return array_values(array_intersect(self::permissionKeys(), $defaults[$this->role] ?? []));
    }
}
