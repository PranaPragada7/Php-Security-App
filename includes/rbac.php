<?php
// Role-Based Access Control (RBAC) - Defines roles and permission checks

class RBAC {
    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';
    const ROLE_GUEST = 'guest';

    public static function canViewSensitiveData($role) {
        return $role === self::ROLE_ADMIN;
    }

    public static function canViewPlaintext($role) {
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_USER]);
    }

    public static function canViewEncrypted($role) {
        return $role === self::ROLE_ADMIN;
    }

    public static function canSubmitJobs($role) {
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_USER]);
    }

    public static function canAccessActivityLogs($role) {
        return $role === self::ROLE_ADMIN;
    }

    public static function canManageUsers($role) {
        return $role === self::ROLE_ADMIN;
    }

    public static function getRolePermissions($role) {
        return [
            'view_sensitive' => self::canViewSensitiveData($role),
            'view_plaintext' => self::canViewPlaintext($role),
            'view_encrypted' => self::canViewEncrypted($role),
            'submit_jobs' => self::canSubmitJobs($role),
            'access_logs' => self::canAccessActivityLogs($role),
            'manage_users' => self::canManageUsers($role)
        ];
    }

    public static function isValidRole($role) {
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_USER, self::ROLE_GUEST]);
    }

    public static function getDefaultRole() {
        return self::ROLE_GUEST;
    }

    /**
     * Check if a username is the root user
     * @param string $username
     * @return bool
     */
    public static function isRootUser($username) {
        return defined('ROOT_USERNAME') && $username === ROOT_USERNAME;
    }

    /**
     * Check if current user can change target user's role
     * Only root user can change roles, and cannot change their own role
     * @param array $currentUser User array with 'username' key
     * @param array $targetUser User array with 'username' key
     * @return bool
     */
    public static function canChangeRole($currentUser, $targetUser) {
        $currentUsername = $currentUser['username'] ?? '';
        $targetUsername = $targetUser['username'] ?? '';
        
        // Only root can change roles
        if (!self::isRootUser($currentUsername)) {
            return false;
        }
        
        // Root cannot change their own role
        if (self::isRootUser($targetUsername)) {
            return false;
        }
        
        return true;
    }
}
