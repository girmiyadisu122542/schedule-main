<?php

namespace Translation\Message;

use Common\Lang\Lang;
use Helper\Type\Gender\Gender;
use Helper\Type\State\State;

class English extends Lang {

    protected static $key = 'en';
    protected static $name = 'english';
    protected static $icon = 'us.png';

    /**
     * The language translations
     *
     * @return array<string, string>
     */
    public static function translations(): array {
        return [
            'not_found' => 'Resource not found',
            'unauthorized' => 'You are not authorized to access this page.',
            'forbidden' => 'Forbidden',
            'bad_request' => 'Bad request',
            'too_many_requests' => 'Too many requests',
            'payment_required' => 'A subscription is required to access this resource.',

            'unable_to_create_user' => 'Unable to create user',
            'user_successfully_created' => '{{name}}\'s account has been created successfully.',
            'invalid_credentials' => 'Invalid credentials.',
            'logout_successfully' => 'Logout successfully',
            'too_many_login_attempts' => 'Too many login attempts',
            'unable_to_login_please_contact_administrator' => 'Unable to login please contact administrator',
            'logout_completed_but_logging_failed' => 'Logout completed but logging failed',
            'successfully_loggedout' => 'Successfully loggedout',
            'user_not_found' => 'User not found',
            'user_detail_not_found' => 'User detail not found',
            'user_logged_out_successfully' => 'User logged out successfully',
            'unable_to_logout_user' => 'Unable to logout user',
            'auto_created_on_logout' => 'Auto created on logout',

            'permission_group_has_child' => 'Permission group has child',
            'permission_group_has_related_permissions' => 'Permission group has related permissions',
            'permission_group_has_related_users' => 'Permission group has related users',
            'invalid_permission_group_parent' => 'Invalid permission group parent',
            'unable_to_update_permission_group' => 'Unable to update permission group',
            'unable_to_create_permission' => 'Unable to create permission',
            'unable_to_update_permission' => 'Unable to update permission',
            'permission_group_successfully_created' => 'Permission group successfully created',
            'permission_group_successfully_updated' => 'Permission group successfully updated',
            'permission_group_successfully_deleted' => 'Permission group successfully deleted',
            'permission_group_can_not_be_added_to_its_own_child' => 'Permission group can not be added to its own child',
            'permission_group_not_found' => 'Permission group could not be found',
            'failed_to_get_permission_group' => 'Failed to get permission group',

            'permission_successfully_created' => '{{name}} Permission successfully created',
            'permission_successfully_updated' => '{{name}} Permission successfully updated',
            'permission_successfully_deleted' => '{{name}} Permission successfully deleted',
            'permissions_activated_successfully' => 'Permissions activated successfully',
            'permissions_deactivated_successfully' => 'Permissions deactivated successfully',
            'permissions_deleted_successfully' => 'Permissions deleted successfully',
            'some_permissions_deleted_others_in_use' => 'Some permissions were deleted; others are in use and were skipped',
            'permission_duplicated_successfully' => '{{name}} created as a duplicate',
            'unable_to_duplicate_permission' => 'Unable to duplicate permission',

            'permission_successfully_activated' => '{{name}} Permission successfully activated',
            'permission_successfully_deactivated' => '{{name}} Permission successfully deactivated',
            'permission_has_been_assigned_to_a_role' => 'Permission has been assigned to a role',
            'permission_has_been_assigned_to_a_user' => 'Permission has been assigned to a user',
            'unable_to_fetch_user_permission_overrides' => 'Unable to fetch user permission overrides',

            'role_successfully_created' => '{{name}} Role successfully created',
            'role_successfully_updated' => '{{name}} Role successfully updated',
            'unable_to_create_role' => 'Unable to create role',
            'unable_to_update_role' => 'Unable to update role',
            'role_status_changed_successfully' => '{{name}} Role status changed successfully',
            'unable_to_change_role_status' => 'Unable to change role status',
            'role_successfully_deleted' => '{{name}} Role successfully deleted',

            'role_has_permissions' => 'Role has permissions',
            'role_has_permissions_with_count' => 'Role has {{count}} permission(s)',
            'role_has_bindings_with_count' => 'Role has {{count}} active binding(s)',
            'role_has_dependencies' => 'Role cannot be deleted because it has active assignments',
            'role_successfully_activated' => '{{name}} Role successfully activated',
            'role_successfully_deactivated' => '{{name}} Role successfully deactivated',
            'roles_activated_successfully' => 'Roles activated successfully',
            'roles_deactivated_successfully' => 'Roles deactivated successfully',
            'role_has_been_assigned_to_a_user' => 'Role has been assigned to a user',
            'role_successfully_changed_to_system' => '{{name}} type successfully changed to system',
            'role_successfully_changed_to_non_system' => '{{name}} type successfully changed to non-system',
            'role_permissions_updated' => 'Role permissions updated',
            'role_binding_revoked' => 'Role binding successfully revoked',
            'unable_to_revoke_role_binding' => 'Unable to revoke role binding',
            'role_binding_not_found' => 'Role binding could not be found',
            'can_not_find_role' => 'Can not find role',

            'some_permissions_could_not_be_found' => 'Some permissions could not be found',
            'permissions_successfully_added_to_role' => 'Permissions successfully added to {{name}}',
            'permissions_successfully_removed_from_role' => 'Permissions successfully removed from {{name}}',

            'someone_has_already_been_assigned_as_role' => 'Someone has already been assigned as {{role}}',
            'you_can_not_assign_this_role_multiple_times' => '{{user}} has already been assigned as {{role}}',
            'role_can_only_be_assigned_to_one_user' => '{{role}} can only be assigned to one user and is already assigned',
            'cannot_assign_role_to_inactive_user' => 'Cannot assign a role to an inactive user',
            'cannot_assign_permission_to_inactive_user' => 'Cannot assign a permission to an inactive user',
            'cannot_assign_role_to_unapproved_user' => 'Cannot assign a role to a pending or rejected user',
            'someone_has_already_been_assigned_permission' => 'Someone has already been assigned {{permission}}',
            'you_can_not_assign_this_permission_multiple_times' => '{{user}} has already been assigned {{permission}}',

            'unable_to_assign_role_to_user' => 'Unable to assign user role',
            'role_assigned_to_user' => '{{user}} has been assigned to {{role}} role',
            'role_is_already_assigned_to_user' => '{{user}} has already been assigned to {{role}} role',

            'unable_to_assign_permission_to_user' => 'Unable to assign user permission',
            'permissions_assigned_to_user' => '{{permissions}} permission\'s has been assigned to {{user}}',
            'permission_is_already_assigned_to_user' => '{{user}} has already been assigned to {{permission}} permission',

            'role_could_not_be_found' => 'Role could not be found',
            'user_could_not_be_found' => 'User could not be found',
            'permissions_could_not_be_found' => 'Permissions could not be found',
            'assigned_role_could_not_be_found' => 'Assigned role could not be found',

            'nothing_is_changed' => 'Nothing is changed',

            // class / exam schedule (sample feature)
            'class_schedule_not_found' => 'Class schedule not found',
            'class_schedule_created_successfully' => 'Schedule "{{name}}" created successfully',
            'class_schedule_updated_successfully' => 'Schedule "{{name}}" updated successfully',
            'class_schedule_deleted_successfully' => 'Schedule "{{name}}" deleted successfully',
            'class_schedule_activated' => 'Schedule "{{name}}" activated',
            'class_schedule_deactivated' => 'Schedule "{{name}}" deactivated',
            'unable_to_create_class_schedule' => 'Unable to create class schedule',
            'unable_to_update_class_schedule' => 'Unable to update class schedule',
            'schedule_time_conflict' => 'Another active schedule already uses this room at the selected time',

            'profile_fetched_successfully' => 'Profile fetched successfully',
            'profile_updated_successfully' => 'Profile updated successfully',
            'unable_to_update_profile' => 'Unable to update profile',
            'session_terminated_successfully' => 'Session terminated successfully',
            'all_sessions_terminated_successfully' => 'All sessions terminated successfully',
            'user_successfully_activated' => '{{name}} User successfully activated',
            'user_successfully_deactivated' => '{{name}} User successfully deactivated',
            'user_status_successfully_changed' => '{{name}} User status successfully changed',

            'invalid_disk' => 'Invalid disk',
            'file_not_found' => 'File not found',
            'internal_server_error' => 'Internal server error',









            'duplicate_permission_name' => 'Permission is already registered with this name',
            'duplicate_permission_group_name' => 'Permission group is already registered with this name',
            'duplicate_role_name' => 'Role is already registered with this name',
            'model_has_related_relations' => '"Cannot delete this {{modelName}} because it has related: {{relations}}."',


            'email_not_found' => 'Email not found',
            'otp_sent_failed' => 'Otp sent failed',
            'otp_sent_successfully' => 'Email will be sent if the email is registered in our system',
            'otp_verified_successfully' => 'Otp verified successfully',
            'invalid_or_expired_reset_token' => 'Invalid or expired reset token',
            'unable_to_reset_password' => 'Unable to reset password',
            'password_reset_successfully' => 'Password reset successfully',
            'user_already_verified' => 'User already verified',
            'invalid_otp' => 'Invalid otp',
            'otp_expired' => 'Otp expired',
            'otp_verification_failed' => 'Otp verification failed',
            'identifier_token_not_found' => 'Identifier token not found',
            'too_many_attempts' => 'Too many attempts. Please try again in {{minutes}}',
            'two_fa_already_enabled' => 'Two fa already enabled',
            'unable_to_enable_two_fa' => 'Unable to enable two fa',
            'two_fa_enabled_successfully' => 'Two fa enabled successfully',
            'invalid_password' => 'Invalid password',
            'unable_to_disable_two_fa' => 'Unable to disable two fa',
            'two_fa_disabled_successfully' => 'Two fa disabled successfully',
            'two_fa_not_enabled' => 'Two fa not enabled',
            'unable_to_regenerate_backup_codes' => 'Unable to regenerate backup codes',
            'backup_codes_regenerated' => 'Backup codes regenerated',
            'invalid_or_expired_mfa_token' => 'Invalid or expired mfa token',
            'invalid_backup_code' => 'Invalid backup code',

            'otp_message_register' => 'Please verify your registration.',
            'otp_message_login' => 'Use this code to complete your login.',
            'otp_message_reset' => 'Use this code to reset your password.',
            'otp_message_two_fa_enable' => 'Use this code to enable two-factor authentication.',

            'role' => 'Role',
            'email_will_be_sent' => 'Email will be sent if the email is registered in our system',
            'unknown_device' => 'Unknown device',
            'cannot_terminate_current_device' => 'Cannot terminate current device',
            'session_terminated' => 'Session terminated',
            'all_sessions_terminated' => 'All sessions terminated',


            'user' => [
                'phone.required' => 'Please enter phone number',
                'phone.unique' => 'Phone number has already been taken',

                'national_id.required' => 'National ID is required',
                'national_id.digits' => 'National ID must be exactly ' . NATIONAL_ID_LENGTH . ' digits',
                'national_id.unique' => 'National ID has already been taken',
                'national_id.string' => 'Invalid national id',
                'birth_date.required' => 'Please select enter Birth date',
                'birth_date.date' => 'Invalid birth date',
                'birth_date.before' => 'Birth date is in the future',
                'birth_date.date_format' => 'Invalid birth date format',

                'photo.required' => 'Please select a photo',
                'photo.image' => 'Please select a valid image file',
                'email.required' => 'Please enter Email Address',
                'email.email' => 'Please enter a valid email',
                'email.unique' => 'Email address has already been taken',

                'first_name' => 'Please enter First Name',
                'first_name.min' => 'First Name can not be less than ' . MIN_NAME_LENGTH . ' characters',
                'first_name.max' => 'First Name can not be more than than ' . MAX_NAME_LENGTH . ' characters',
                'middle_name' => 'Please enter Middle Name',
                'middle_name.min' => 'Middle Name can not be less than ' . MIN_NAME_LENGTH . ' characters',
                'middle_name.max' => 'Middle Name can not be more than than ' . MAX_NAME_LENGTH . ' characters',
                'last_name' => 'Please enter Last Name',
                'last_name.min' => 'Last Name can not be less than ' . MIN_NAME_LENGTH . ' characters',
                'last_name.max' => 'Last Name can not be more than than ' . MAX_NAME_LENGTH . ' characters',

                'gender' => 'please select Gender',
                'gender.in' => 'Please select a valid gender. gender names should be ' . implode(', ', Gender::typeNames()),

                'entity_id.required' => 'Please select an entity for the user',
                'entity_id.exists' => 'Please select a valid entity',
            ],

            'permission' => [
                'key.required' => 'Please enter the permisison key',
                'key.unique' => 'Permission is already registered with this key',
                'name.required' => 'Please enter the permisison name',
                'name.unique' => 'Permission is already registered with this name',
                'module_id.required' => 'Module is required',
                'module_id.exists' => 'Module could not be found',
                'permission_group_id.required' => 'Permission group is required',
                'permission_group_id.exists' => 'Permission group could not be found',
                'state.required' => 'Please provide the state',
                'state.in' => 'Permission state should be either ' . implode(', ', State::typeNames()),

                'unique_per_user.required' => 'Please provide if the role is unque per user or not',
                'unique_per_user.boolean' => 'Invalid uniquess type, it should be either true or false',
                'unique_per_entity.required' => 'Please provide if the role is unque per entity or not',
                'unique_per_entity.boolean' => 'Invalid uniquess type, it should be either true or false',
            ],

            'roles' => [
                'name.required' => 'Please enter the role name',
                'name.unique' => 'Role is already registered with this name',
                'is_system.required' => 'Please provide if the role is system or not',
                'is_system.boolean' => 'Invalid role type, it should be either true or false',
                'state.required' => 'Please provide the state',
                'state.in' => 'Role state should be either ' . implode(', ', State::typeNames()),

                'permissions.array' => 'Invalid permissions',
                'permissions.required' => 'Please provide the permissions',
                'permissions.min' => 'Please select atleaset 1 permission',
                'permissions.*.integer' => 'Invalid permission type',
                'permissions.*.exists' => 'Some permissions could not be found',
                'permissions.*.distinct' => 'Please avoid the same permission multiple times',

                'unique_per_user.required' => 'Please provide if the role is unque per user or not',
                'unique_per_user.boolean' => 'Invalid uniquess type, it should be either true or false',
                'unique_per_entity.required' => 'Please provide if the role is unque per entity or not',
                'unique_per_entity.boolean' => 'Invalid uniquess type, it should be either true or false',
            ],

            'permission_group' => [
                'name.required' => 'Please enter the group name',
                'name.unique' => 'Permission group is already registered',
                'permission_group_id.exists' => 'Permission group could not be found',
            ],

            'user_role_binding' => [
                'role_id.required' => 'Role should be selected',
                'role_id.exists' => 'Please select a valid role',

                'entity_id.exists' => 'Please select a valid entity',
                'include_descendants.required' => 'You should specify whether descendants are included or not',
                'include_descendants.boolean' => 'Choose a valid choice for include descendants',

                'ends_at.date' => 'Ends at should be a date',
                'ends_at.after' => 'Ends at should be now or a future date.',
                'starts_at.date' => 'Please chose a proper date value.',
                'starts_at.after' => 'Excercising this role should start right now or in a future date.',
                'starts_at.required' => 'You should specify when to apply this role to the user',

                'descendants.array' => 'Invalid descendants',
                'descendants.min' => 'You should atleast choose one descendant',
                'descendants.*.entity_id.required' => 'Please select descendant',
                'descendants.*.entity_id.exists' => 'Invalid entity provided',
                'descendants.*.include_descendants.required' => 'Please select whether the descendant have its own descendants',
                'descendants.*.include_descendants.boolean' => 'Choose a valid choice for include descendants',
            ],

            'user_permission_override' => [
                'permission_ids.required' => 'Permissions should be selected',
                'permission_ids.array' => 'Invalid permission',
                'permission_ids.min' => 'Please atleast select 1 permission',
                'permission_ids.exists' => 'Some of the permissions are not valid',

                'entity_id.exists' => 'Please select a valid entity',
                'include_descendants.required_if' => 'You should specify whether descendants are included or not',
                'include_descendants.boolean' => 'Choose a valid choice for include descendants',
                'allow.required' => 'You should specify whether to allow the permission or not',
                'allow.boolean' => 'Choose a valid choice for allowing permission or not',

                'ends_at.date' => 'Ends at should be a date',
                'ends_at.after' => 'Ends at should be now or a future date.',
                'starts_at.date' => 'Please chose a proper date value.',
                'starts_at.after' => 'Excercising this permission should start right now or in a future date.',
                'starts_at.required' => 'You should specify when to apply this permission to the user',

                'descendants.array' => 'Invalid descendants',
                'descendants.min' => 'You should atleast choose one descendant',
                'descendants.*.entity_id.required' => 'Please select descendant',
                'descendants.*.entity_id.exists' => 'Invalid entity provided',
                'descendants.*.include_descendants.required' => 'Please select whether the descendant have its own descendants',
                'descendants.*.include_descendants.boolean' => 'Choose a valid choice for include descendants',
            ],
            'currency' => 'Currency',

            'status_cannot_be_pending' => 'Status cannot be pending',

            // Payment Method Messages

            // Payment Provider Messages

            // Accepted Payment (Entity Payment Method) Messages
            'role_not_found' => 'Role not found',
            'permission_not_found' => 'Permission not found',
            'user_successfully_deleted' => 'User successfully deleted',
            'action_not_found' => 'Action not found',
            'users_activated_successfully' => 'Users activated successfully',
            'users_deactivated_successfully' => 'Users deactivated successfully',
            'users_deleted_successfully' => 'Users deleted successfully',
            'bulk_action_failed' => 'Bulk action failed',
            'unautheticated' => 'Unautheticated',
            'unautheticated_login_please_try_again' => 'Unauthenticated login. Please login again.',
            'validation_error' => 'Validation error',
            'action_not_found_or_unauthorized' => 'Action not found or unauthorized',
            'duplicate_lookup_value_name' => 'Duplicate lookup value name',
            'lookup_value' => 'Lookup value',
            'duplicate_lookup_type_name' => 'Duplicate lookup type name',
            'unable_to_create_lookup_type' => 'Unable to create lookup type',
            'lookup_type_not_found' => 'Lookup type not found',
            'unable_to_update_lookup_type' => 'Unable to update lookup type',
            'lookup_type_is_system_cannot_delete' => 'Lookup type is system cannot delete',
            'lookup_type_successfully_deleted' => 'Lookup type successfully deleted',
            'lookup_value_does_not_belong_to_type' => 'Lookup value does not belong to type',
            'lookup_type_status_updated' => 'Lookup type status updated',
            'unable_to_create_lookup_value' => 'Unable to create lookup value',
            'lookup_value_not_found' => 'Lookup value not found',
            'unable_to_update_lookup_value' => 'Unable to update lookup value',
            'lookup_value_successfully_deleted' => 'Lookup value successfully deleted',
            'unable_to_reorder_lookup_values' => 'Unable to reorder lookup values',
            'lookup_values_reordered' => 'Lookup values reordered',
            'lookup_transition_values_must_belong_to_same_type' => 'Lookup transition values must belong to same type',
            'lookup_transition_already_exists' => 'Lookup transition already exists',
            'unable_to_create_lookup_transition' => 'Unable to create lookup transition',
            'lookup_transition_successfully_created' => 'Lookup transition successfully created',
            'lookup_transition_not_found' => 'Lookup transition not found',
            'lookup_transition_is_system_cannot_delete' => 'Lookup transition is system cannot delete',
            'lookup_transition_successfully_deleted' => 'Lookup transition successfully deleted',
            'lookup_type_successfully_activated' => 'Lookup type {{name}} successfully activated',
            'lookup_type_successfully_deactivated' => 'Lookup type {{name}} successfully deactivated',
            'lookup_type_successfully_updated' => 'Lookup type {{name}} successfully updated',
            'lookup_type_successfully_created' => 'Lookup type {{name}} successfully created',
            'lookup_value_successfully_created' => 'Lookup value {{name}} successfully created',
            'lookup_value_successfully_updated' => 'Lookup value {{name}} successfully updated',
            'lookup_value_successfully_activated' => 'Lookup value {{name}} successfully activated',
            'lookup_value_successfully_deactivated' => 'Lookup value {{name}} successfully deactivated',
            'parent_must_be_higher_level' => 'Parent must be higher level',

            'unable_to_delete_measureemnt_conversion' => 'Unable to delete measurement conversion',
            'supplier' => 'Supplier',
            'employee' => 'Employee',




            'lookup_type_not_accessible' => 'Lookup type not accessible',
            'lookup_value_status_updated' => 'Lookup value status updated',
            'parent_id_required' => 'Parent id required',
            'updateDecimalSupport' => 'Update Decimal Support',
            'operation_lookup_value_could_not_be_found' => 'Operation lookup value could not be found',
            'invalid_sequence_ids' => 'Invalid sequence ids',
            'invalid_operation_ids' => 'Invalid operation ids',
            'provide_request_data' => 'Provide request data',

            'reassign_target_required' => 'Reassign target group is required because this group contains icons',
            'invalid_reassign_target' => 'Invalid reassign target group',
            'invalid_svg_payload' => 'Invalid SVG payload',
            'svg_too_large' => 'SVG file exceeds the 64KB limit',
            'invalid_image_payload' => 'Invalid image payload',
            'image_too_large_or_invalid' => 'Image too large or invalid',
            'duplicate_content_hash' => 'Duplicate of an existing icon in this group',

            'icon' => [
                'state.required' => 'Please choose an icon state',
                'state.in' => 'State must be active or inactive',
            ],

            // Custom Field Engine
            'model_list_created_successfully' => 'Record type registered successfully',
            'model_list_updated_successfully' => 'Record type updated successfully',
            'model_list_deleted_successfully' => 'Record type removed successfully',
            'model_list_state_changed' => 'Record type state changed successfully',
            'model_list_not_found' => 'Record type not found',
            'model_list_has_fields' => 'Cannot delete: this record type still has custom fields',
            'unable_to_create_model_list' => 'Unable to register record type',
            'model_list_already_exists' => 'This record type is already registered for custom fields',

            'invalid_status_transition' => 'Invalid status transition',




            'override_requires_exactly_one_target' => 'An override must target exactly one of a module or a feature',





            'no_next_step_found' => 'No next step found',
            'you_are_not_eligible_for_next_step' => 'You are not eligible for next step',
            'current_step_not_found' => 'Current step not found',
            'user_cache_not_found' => 'User cache not found',
            'no_first_step_found' => 'No first step found',


            'max_quantity' => 'Maximum Quantity',
            'min_quantity' => 'Minimum Quantity',
            'status_lookup_value_not_found' => 'Status lookup value not found',
        ];
    }
}