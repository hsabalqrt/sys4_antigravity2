<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * يقوم هذا السيدر بإنشاء الأدوار الأساسية للنظام.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * تشغيل السيدر.
     *
     * @return void
     */
    public function run(): void
    {
        // إعادة تعيين أدوار وصلاحيات Spatie المؤقتة
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'admin' => 'Admin - المدير العام',
            'hr' => 'Human Resources - الموارد البشرية',
            'accountant' => 'Accountant - المحاسب',
            'designer' => 'Designer - المصمم',
            'reviewer' => 'Reviewer - المراجع',
            'supervisor' => 'Supervisor - المشرف',
            'social_media' => 'Social Media - سوشيال ميديا',
            'content_creator' => 'Content Creator - مدخل أفكار ومحتوى',
        ];

        // إنشاء الصلاحيات المطلوبة
        $permissions = [
            // Users
            'view_any_users', 'view_users', 'create_users', 'update_users', 'delete_users',
            
            // Core
            'view_any_category', 'view_category', 'create_category', 'update_category', 'delete_category',
            'view_any_currency', 'view_currency', 'create_currency', 'update_currency', 'delete_currency',
            'view_any_location', 'view_location', 'create_location', 'update_location', 'delete_location',
            'view_any_tag', 'view_tag', 'create_tag', 'update_tag', 'delete_tag',
            'view_any_tag_group', 'view_tag_group', 'create_tag_group', 'update_tag_group', 'delete_tag_group',
            
            // Operations
            'view_any_client', 'view_client', 'create_client', 'update_client', 'delete_client',
            'view_any_client_need', 'view_client_need', 'create_client_need', 'update_client_need', 'delete_client_need',
            'view_any_designer', 'view_designer', 'create_designer', 'update_designer', 'delete_designer',
            'view_any_idea', 'view_idea', 'create_idea', 'update_idea', 'delete_idea',
            'view_any_custody', 'view_custody', 'create_custody', 'update_custody', 'delete_custody',
            'view_any_social_media', 'view_social_media', 'create_social_media', 'update_social_media', 'delete_social_media',

            // Custom Pages
            'view_designer_dashboard',
            'view_reviewer_dashboard',
            'view_designer_distribution',
            'view_tag_distribution',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        foreach ($roles as $key => $description) {
            $role = Role::firstOrCreate(['name' => $key]);
            
            // في حالة المشرف العام، نمنحه كل الصلاحيات (كإجراء احتياطي بالإضافة للـ Gate)
            if ($key === 'admin') {
                $role->givePermissionTo(\Spatie\Permission\Models\Permission::all());
            }
        }

        // تعيين أول مستخدم كمدير عام
        $firstUser = \App\Models\User::first();
        if ($firstUser) {
            $firstUser->assignRole('admin');
        }
    }
}
