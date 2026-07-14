<?php

namespace Helper\Permission;

class PermissionActionHelper {

    /**
     * Convert permission codes to backend usage examples.
     * Example: 'user:update:group' => 'useCanGroup'
     */
    public static function convertPermissionsToBackendUsage($permissions) {
        $examples = [];
        foreach ($permissions as $perm) {
            $lastPart = self::parsePermissionParts($perm);
            if (!$lastPart) {
                continue;
            }
            $examples[] = 'userCan' . ucfirst($lastPart);
        }
        return $examples;
    }

    public static function convertFromFrontendToBackendUsage($actions) {
        $examples = [];
        foreach ($actions as $action) {
            $examples[] = 'userCan' . ucfirst($action);
        }
        return $examples;
    }

    /**
     * Convert permission codes to simple action names.
     * Example: 'user:update:group' => 'group'
     */
    public static function convertPermissionsToActions($permissions) {
        $actions = [];
        foreach ($permissions as $perm) {
            $lastPart = self::parsePermissionParts($perm);
            if (!$lastPart) {
                continue;
            }
            $actions[] = lcfirst($lastPart);
        }
        return $actions;
    }
    /**
     * Decrypt an encrypted action and convert it back to permission format.
     */
    public static function decryptAndConvertToPermission($encryptedActions) {
        $key = getenv('PERMISSION_ACTION_KEY');
        if (!$key) {
            throw new \Exception('Encryption key not found in environment variables.');
        }

        $iv = hex2bin(substr(hash('sha256', $key), 0, 32));

        $decryptAction = function ($encryptedAction) use ($key, $iv) {
            $decryptedAction = openssl_decrypt(base64_decode($encryptedAction), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

            if (!$decryptedAction) {
                return null;
            }

            return strtolower(preg_replace('/([a-z])([A-Z])/', '$1:$2', $decryptedAction));
        };

        if (is_array($encryptedActions)) {
            return array_map($decryptAction, $encryptedActions);
        }

        return $decryptAction($encryptedActions);
    }
    /**
     * Convert a permission string to a camelCase action name.
     * Example: 'user:group:update' => 'updateUserGroup'
     */
    private static function parsePermissionParts($perm) {
        $parts = explode(':', $perm);

        if (count($parts) < 2) {
            return $perm;
        }

        $action = array_shift($parts);

        $others = array_map('ucfirst', $parts);

        return $action . implode('', $others);
    }


    /**
     * Convert permission codes to action names and encrypt them using AES-256-CBC (compatible with Vue/CryptoJS)
     */
    public static function convertAndEncryptPermissions($permissions, $key) {
        $actions = self::convertPermissionsToActions($permissions);
        $iv = hex2bin(substr(hash('sha256', $key), 0, 32)); // 16 bytes = 32 hex chars
        return array_map(function ($action) use ($key, $iv) {
            return base64_encode(openssl_encrypt($action, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));
        }, $actions);
    }
}
