<?php

namespace Translation\Message;

use Common\Lang\Lang;
use Helper\Type\Gender\Gender;
use Helper\Type\State\State;

class Amharic extends Lang {

    protected static $key = 'am';
    protected static $name = 'amharic';
    protected static $icon = 'et.png';

    public static function translations(): array {
        return [
            'not_found' => 'መረጃው አልተገኘም',
            'unauthorized' => 'ይህንን ገጽ ለመጎብኘት ፈቃድ የለዎትም።',
            'forbidden' => 'የተከለከለ',
            'bad_request' => 'መጥፎ ጥያቄ',
            'too_many_requests' => 'በጣም ብዙ ጥያቄዎች',
            'payment_required' => 'ይህን መዳረሻ ለመጠቀም ምዝገባ ያስፈልጋል።',

            'unable_to_create_user' => 'ተጠቃሚውን መፍጠር አልተቻለም',
            'user_successfully_created' => 'የ{{name}} አካውንት በተሳካ ሁኔታ ተፈጥሯል።',
            'invalid_credentials' => 'የተሳሳተ መለያ።',
            'logout_successfully' => 'በተሳካ ሁኔታ ወጥተዋል',
            'too_many_login_attempts' => 'ከመጠን በላይ ሙከራዎች ተደርገዋል',
            'unable_to_login_please_contact_administrator' => 'መግባት አልተቻለም፣ እባክዎ የአስተዳዳሪውን ያነጋግሩ',
            'logout_completed_but_logging_failed' => 'መውጣት ተጠናቋል ነገር ግን ሎግ ማድረግ አልተቻለም',
            'successfully_loggedout' => 'በተሳካ ሁኔታ ወጥተዋል',
            'user_not_found' => 'ተጠቃሚው አልተገኘም',
            'user_detail_not_found' => 'የተጠቃሚ ዝርዝር አልተገኘም',
            'user_logged_out_successfully' => 'ተጠቃሚው በተሳካ ሁኔታ ወጥቷል',
            'unable_to_logout_user' => 'ተጠቃሚውን ማስወጣት አልተቻለም',
            'auto_created_on_logout' => 'ሲወጡ በራስ-ሰር የተፈጠረ',
            'internal_server_error' => 'የሲስተም ስህተት',
            'invalid_disk' => 'የተሳሳተ ዲስክ',
            'file_not_found' => 'ፊይሉ አልተገኘም',
            'profile_fetched_successfully' => 'መገለጫ በተሳካ ሁኔታ ቀርቧል',
            'profile_updated_successfully' => 'መገለጫ በተሳካ ሁኔታ ተዘምኗል',
            'unable_to_update_profile' => 'መገለጫ ማዘመን አልተቻለም',
            'session_terminated_successfully' => 'ክፍለ ጊዜ በተሳካ ሁኔታ ተቋርጧል',
            'all_sessions_terminated_successfully' => 'ሁሉም ክፍለ ጊዜዎች በተሳካ ሁኔታ ተቋርጠዋል',
            'nothing_is_changed' => 'ምንም አልተለወጠም።',

            // class / exam schedule (sample feature)
            'class_schedule_not_found' => 'የመርሃ ግብር አልተገኘም',
            'class_schedule_created_successfully' => '"{{name}}" መርሃ ግብር በተሳካ ሁኔታ ተፈጥሯል',
            'class_schedule_updated_successfully' => '"{{name}}" መርሃ ግብር በተሳካ ሁኔታ ተሻሽሏል',
            'class_schedule_deleted_successfully' => '"{{name}}" መርሃ ግብር በተሳካ ሁኔታ ተሰርዟል',
            'class_schedule_activated' => '"{{name}}" መርሃ ግብር ነቅቷል',
            'class_schedule_deactivated' => '"{{name}}" መርሃ ግብር ተቦዝኗል',
            'unable_to_create_class_schedule' => 'መርሃ ግብር መፍጠር አልተቻለም',
            'unable_to_update_class_schedule' => 'መርሃ ግብር ማሻሻል አልተቻለም',
            'schedule_time_conflict' => 'ሌላ ንቁ መርሃ ግብር በተመረጠው ሰዓት ይህን ክፍል እየተጠቀመ ነው',



            'permission_group_has_child' => 'የፍቃድ ቡድን ተከታይ አለው',
            'permission_group_has_related_permissions' => 'የፍቃድ ቡድን ከሌሎች ፍቃዶች ጋር የተያያዘ ነው',
            'permission_group_has_related_users' => 'የፍቃድ ቡድን ከተጠቃሚዎች ጋር የተያያዘ ነው',
            'invalid_permission_group_parent' => 'የፍቃድ ቡድን ወላጅ አይትክክልም',
            'unable_to_update_permission_group' => 'የፍቃድ ቡድን ማዘመን አልተቻለም',
            'unable_to_create_permission' => 'ፍቃድ መፍጠር አልተቻለም',
            'unable_to_update_permission' => 'ፍቃድ ማዘመን አልተቻለም',
            'permission_group_successfully_created' => 'የፍቃድ ቡድን በተሳካ ሁኔታ ተፈጥሯል',
            'permission_group_successfully_updated' => 'የፍቃድ ቡድን በተሳካ ሁኔታ ተዘምኗል',
            'permission_group_successfully_deleted' => 'የፍቃድ ቡድን በተሳካ ሁኔታ ተሰርዟል',
            'permission_group_can_not_be_added_to_its_own_child' => 'የፍቃድ ቡድን ወደ ራሱ ተከታይ መጨመር አይቻልም',
            'permission_group_not_found' => 'የፍቃድ ቡድን አልተገኘም',
            'failed_to_get_permission_group' => 'የፍቃድ ቡድን ማግኘት አልተሳካም',

            'permission_successfully_created' => '{{name}} ፍቃድ በተሳካ ሁኔታ ተፈጥሯል',
            'permission_successfully_updated' => '{{name}} ፍቃድ በተሳካ ሁኔታ ተዘምኗል',
            'permission_successfully_deleted' => '{{name}} ፍቃድ በተሳካ ሁኔታ ተሰርዟል',
            'permissions_activated_successfully' => 'ፍቃዶች በተሳካ ሁኔታ ነቅተዋል',
            'permissions_deactivated_successfully' => 'ፍቃዶች በተሳካ ሁኔታ ጠፍተዋል',
            'permissions_deleted_successfully' => 'ፍቃዶች በተሳካ ሁኔታ ተሰርዘዋል',
            'some_permissions_deleted_others_in_use' => 'አንዳንድ ፍቃዶች ተሰርዘዋል፤ ሌሎች ጥቅም ላይ ስለሆኑ ተዘለዋል',
            'permission_duplicated_successfully' => '{{name}} እንደ ቅጂ ተፈጥሯል',
            'unable_to_duplicate_permission' => 'ፍቃድ ማባዛት አልተቻለም',

            'permission_successfully_activated' => '{{name}} ፍቃድ በተሳካ ሁኔታ ነቃ',
            'permission_successfully_deactivated' => '{{name}} ፍቃድ በተሳካ ሁኔታ አጥፍቷል',
            'permission_has_been_assigned_to_a_role' => 'ፍቃድ ለአንድ ሚና ተመድቧል',
            'permission_has_been_assigned_to_a_user' => 'ፍቃድ ለአንድ ተጠቃሚ ተመድቧል',
            'unable_to_fetch_user_permission_overrides' => 'የተጠቃሚ የፍቃድ ማሻሻያዎችን ማምጣት አልተቻለም',

            'role_successfully_created' => '{{name}} ሚና በተሳካ ሁኔታ ተፈጥሯል',
            'role_successfully_updated' => '{{name}} ሚና በተሳካ ሁኔታ ተዘምኗል',
            'unable_to_create_role' => 'ሚና መፍጠር አልተቻለም',
            'unable_to_update_role' => 'ሚና ማዘመን አልተቻለም',
            'role_status_changed_successfully' => '{{name}} ሚና ሁኔታ በተሳካ ሁኔታ ተቀይሯል',
            'unable_to_change_role_status' => 'የሚና ሁኔታን መቀየር አልተቻለም',
            'role_successfully_deleted' => '{{name}} ሚና በተሳካ ሁኔታ ተሰርዟል',

            'role_has_permissions' => 'ሚናው ፍቃዶች አሉት',
            'role_has_permissions_with_count' => 'ሚናው {{count}} ፍቃድ(ዎች) አሉት',
            'role_has_bindings_with_count' => 'ሚናው {{count}} ንቁ ግንኙነት(ዎች) አሉት',
            'role_has_dependencies' => 'ሚናው ንቁ መመደቦች ስላሉት መሰረዝ አይቻልም',
            'role_successfully_activated' => '{{name}} ሚና በተሳካ ሁኔታ ነቃ',
            'role_successfully_deactivated' => '{{name}} ሚና በተሳካ ሁኔታ አጥፍቷል',
            'roles_activated_successfully' => 'ሚናዎች በተሳካ ሁኔታ ነቅተዋል',
            'roles_deactivated_successfully' => 'ሚናዎች በተሳካ ሁኔታ ጠፍተዋል',
            'role_has_been_assigned_to_a_user' => 'ሚና ለአንድ ተጠቃሚ ተመድቧል',
            'role_successfully_changed_to_system' => '{{name}} አይነት በተሳካ ሁኔታ ወደ ሲስተም ተቀይሯል',
            'role_successfully_changed_to_non_system' => '{{name}} አይነት በተሳካ ሁኔታ ወደ ኖን-ሲስተም ተቀይሯል',
            'role_permissions_updated' => 'የሚና ፍቃዶች ተዘምነዋል',
            'role_binding_revoked' => 'የሚና ግንኙነት በተሳካ ሁኔታ ተሰርዟል',
            'unable_to_revoke_role_binding' => 'የሚና ግንኙነት ማስወገድ አልተቻለም',
            'role_binding_not_found' => 'የሚና ግንኙነት አልተገኘም',
            'can_not_find_role' => 'ሚና ማግኘት አልተቻለም',

            'some_permissions_could_not_be_found' => 'አንዳንድ ፍቃዶች አልተገኙም',
            'permissions_successfully_added_to_role' => 'ፍቃዶች በተሳካ ሁኔታ ወደ {{name}} ተጨምረዋል',
            'permissions_successfully_removed_from_role' => 'ፍቃዶች በተሳካ ሁኔታ ከ {{name}} ተወግደዋል',

            'someone_has_already_been_assigned_as_role' => 'አንድ ሰው ቀድሞ እንደ {{role}} ተመድቧል',
            'you_can_not_assign_this_role_multiple_times' => '{{user}} ቀድሞ እንደ {{role}} ተመድቧል',
            'role_can_only_be_assigned_to_one_user' => '{{role}} ለአንድ ተጠቃሚ ብቻ ሊመደብ ይችላል፣ ቀድሞም ተመድቧል',
            'cannot_assign_role_to_inactive_user' => 'ንቁ ላልሆነ ተጠቃሚ ሚና መመደብ አይቻልም',
            'cannot_assign_permission_to_inactive_user' => 'ንቁ ላልሆነ ተጠቃሚ ፈቃድ መመደብ አይቻልም',
            'cannot_assign_role_to_unapproved_user' => 'ሚና በመጠባበቅ ላይ ላለ ወይም ውድቅ ለተደረገ ተጠቃሚ መመደብ አይቻልም',
            'someone_has_already_been_assigned_permission' => 'አንድ ሰው ቀድሞ {{permission}} ተመድቧል',
            'you_can_not_assign_this_permission_multiple_times' => '{{user}} ቀድሞ {{permission}} ተመድቧል',

            'unable_to_assign_role_to_user' => 'ለተጠቃሚው ሚና መመደብ አልተቻለም',
            'role_assigned_to_user' => '{{user}} ለ {{role}} ሚና ተመድቧል',
            'role_is_already_assigned_to_user' => '{{user}} ቀድሞ ለ {{role}} ሚና ተመድቧል',

            'unable_to_assign_permission_to_user' => 'ለተጠቃሚው ፍቃድ መመደብ አልተቻለም',
            'permissions_assigned_to_user' => '{{permissions}} ፍቃድ ለ {{user}} ተመድቧል',
            'permission_is_already_assigned_to_user' => '{{user}} ቀድሞ ለ {{permission}} ፍቃድ ተመድቧል',

            'role_could_not_be_found' => 'ሚና ሊገኝ አልቻለም',
            'user_could_not_be_found' => 'ተጠቃሚ ሊገኝ አልቻለም',
            'permissions_could_not_be_found' => 'ፍቃዶች ሊገኙ አልቻሉም',
            'assigned_role_could_not_be_found' => 'የተመደበ ሚና ሊገኝ አልቻለም',





            'duplicate_permission_name' => 'ይህ የፍቃድ ስም ቀደም ብሎ ተመዝግቧል',
            'duplicate_permission_group_name' => 'ይህ የፍቃድ ቡድን ስም ቀደም ብሎ ተመዝግቧል',
            'duplicate_role_name' => 'ይህ የሚና ስም ቀደም ብሎ ተመዝግቧል',
            'model_has_related_relations' => 'ይህን {{modelName}} መሰረዝ አይቻልም፤ ምክንያቱም ከእሱ ጋር የተያያዙ ነገሮች አሉ፦ {{relations}}',


            'email_not_found' => 'ኢሜይል አልተገኘም።',
            'otp_sent_failed' => 'የተላከው Otp አልተሳካም።',
            'otp_sent_successfully' => 'ኢሜይሉ በሲስተማችን ውስጥ የተመዘገበ ከሆነ መልእክት ይላካል',
            'otp_verified_successfully' => 'Otp በተሳካ ሁኔታ ተረጋግጧል',
            'invalid_or_expired_reset_token' => 'ልክ ያልሆነ ወይም ጊዜው ያለፈበት ዳግም ማስጀመሪያ ማስመሰያ',
            'unable_to_reset_password' => 'የይለፍ ቃል ዳግም ማስጀመር አልተቻለም',
            'password_reset_successfully' => 'የይለፍ ቃል በተሳካ ሁኔታ ዳግም ማስጀመር',
            'user_already_verified' => 'ተጠቃሚው አስቀድሞ ተረጋግጧል',
            'invalid_otp' => 'ልክ ያልሆነ otp',
            'otp_expired' => 'Otp ጊዜው አልፎበታል።',
            'otp_verification_failed' => 'የኦቲፒ ማረጋገጫ አልተሳካም።',
            'identifier_token_not_found' => 'መለያ ማስመሰያ አልተገኘም።',
            'too_many_attempts' => 'ሙከራ አብዝተዋል። እባክዎ ከ {{minutes}} ደቂቃ በኋላ እንደገና ይሞክሩ',
            'two_fa_already_enabled' => 'ሁለት FA አስቀድሞ ነቅቷል።',
            'unable_to_enable_two_fa' => 'ሁለት FA ማንቃት አልተቻለም',
            'two_fa_enabled_successfully' => 'ሁለት FA በተሳካ ሁኔታ ነቅቷል',
            'invalid_password' => 'የተሳሳተ የይለፍ ቃል',
            'unable_to_disable_two_fa' => 'ሁለት FA ማሰናከል አልተቻለም',
            'two_fa_disabled_successfully' => 'ሁለት FA በተሳካ ሁኔታ ተሰናክሏል',
            'two_fa_not_enabled' => 'ሁለት FA አልነቃም',
            'unable_to_regenerate_backup_codes' => 'የምትኬ ኮዶችን እንደገና ማመንጨት አልተቻለም',
            'backup_codes_regenerated' => 'የመጠባበቂያ ኮዶች እንደገና ተፈጥረዋል።',
            'invalid_or_expired_mfa_token' => 'ልክ ያልሆነ ወይም ጊዜው ያለፈበት mfa token',
            'invalid_backup_code' => 'ልክ ያልሆነ የመጠባበቂያ ኮድ',

            'otp_message_register' => 'እባክዎ ምዝገባዎን ያረጋግጡ።',
            'otp_message_login' => 'መግቢያዎን ለማጠናቀቅ ይህንን ኮድ ይጠቀሙ።',
            'otp_message_reset' => 'የይለፍ ቃልዎን ለመቀየር ይህንን ኮድ ይጠቀሙ።',
            'otp_message_two_fa_enable' => 'ሁለት-ደረጃ ማረጋገጫን ለማንቃት ይህንን ኮድ ይጠቀሙ።',

            'role' => 'ሚና',
            'email_will_be_sent' => 'ኢሜይሉ በሲስተማችን ውስጥ የተመዘገበ ከሆነ መልእክት ይላካል',
            'unknown_device' => 'ያልታወቀ ማሽን',
            'cannot_terminate_current_device' => 'አሁን እየተጠቀሙበት ያለውን ማሽን መዝጋት አይቻልም',
            'session_terminated' => 'ቆይታው (Session) ተቋርጧል',
            'all_sessions_terminated' => 'ሁሉም ቆይታዎች (Sessions) ተቋርጠዋል',

            'user' => [
                'phone.required' => 'እባክዎ የስልክ ቁጥር ያስገቡ',
                'phone.unique' => 'ይህ የስልክ ቁጥር ቀደም ብሎ ተወስዷል',

                'national_id.required' => 'ብሔራዊ መታወቂያ (National ID) ግዴታ ነው',
                'national_id.digits' => 'ብሔራዊ መታወቂያው በትክክል ' . NATIONAL_ID_LENGTH . ' አሃዞች መሆን አለበት',
                'national_id.unique' => 'ይህ ብሔራዊ መታወቂያ ቀደም ብሎ ተወስዷል',
                'national_id.string' => 'ልክ ያልሆነ ብሔራዊ መታወቂያ',
                'birth_date.required' => 'እባክዎ የልደት ቀን ያስገቡ',
                'birth_date.date' => 'ልክ ያልሆነ የልደት ቀን',
                'birth_date.before' => 'የልደት ቀን ከዛሬ በፊት መሆን አለበት',
                'birth_date.date_format' => 'ልክ ያልሆነ የልደት ቀን ፎርማት',

                'photo.required' => 'እባክዎ ፎቶ ይምረጡ',
                'photo.image' => 'እባክዎ ትክክለኛ የምስል (Image) ፋይል ይምረጡ',
                'email.required' => 'እባክዎ የኢሜይል አድራሻ ያስገቡ',
                'email.email' => 'እባክዎ ትክክለኛ የኢሜይል አድራሻ ያስገቡ',
                'email.unique' => 'ይህ ኢሜይል አድራሻ ቀደም ብሎ ተወስዷል',

                'first_name' => 'እባክዎ መጀመሪያ ስም ያስገቡ',
                'first_name.min' => 'መጀመሪያ ስም ከ ' . MIN_NAME_LENGTH . ' ፊደላት ማነስ የለበትም',
                'first_name.max' => 'መጀመሪያ ስም ከ ' . MAX_NAME_LENGTH . ' ፊደላት መብለጥ የለበትም',
                'middle_name' => 'እባክዎ የአባት ስም ያስገቡ',
                'middle_name.min' => 'የአባት ስም ከ ' . MIN_NAME_LENGTH . ' ፊደላት ማነስ የለበትም',
                'middle_name.max' => 'የአባት ስም ከ ' . MAX_NAME_LENGTH . ' ፊደላት መብለጥ የለበትም',
                'last_name' => 'እባክዎ የአያት ስም ያስገቡ',
                'last_name.min' => 'የአያት ስም ከ ' . MIN_NAME_LENGTH . ' ፊደላት ማነስ የለበትም',
                'last_name.max' => 'የአያት ስም ከ ' . MAX_NAME_LENGTH . ' ፊደላት መብለጥ የለበትም',

                'gender' => 'እባክዎ ጾታ ይምረጡ',
                'gender.in' => 'እባክዎ ትክክለኛ ጾታ ይምረጡ፤ የጾታ ስሞች መሆን ያለባቸው፦ ' . implode(', ', Gender::typeNames()),

                'entity_id.required' => 'እባክዎ ለተጠቃሚው አካል ይምረጡ',
                'entity_id.exists' => 'እባክዎ ትክክለኛ አካል ይምረጡ',
            ],

            'permission' => [
                'key.required' => 'እባክዎ የፈቃድ መለያ ቁልፍ (Permission key) ያስገቡ',
                'key.unique' => 'በዚህ መለያ ቁልፍ የተመዘገበ ፈቃድ ቀድሞ አለ',
                'name.required' => 'እባክዎ የፈቃድ ስም ያስገቡ',
                'name.unique' => 'በዚህ ስም የተመዘገበ ፈቃድ ቀድሞ አለ',
                'module_id.required' => 'ሞጁል (Module) መምረጥ ግዴታ ነው',
                'module_id.exists' => 'የተመረጠው ሞጁል አልተገኘም',
                'permission_group_id.required' => 'የፈቃድ ቡድን (Permission group) ግዴታ ነው',
                'permission_group_id.exists' => 'የተመረጠው የፈቃድ ቡድን አልተገኘም',
                'state.required' => 'እባክዎ ሁኔታውን (State) ይግለጹ',
                'state.in' => 'የፈቃድ ሁኔታ መሆን ያለበት፦ ' . implode(', ', State::typeNames()),

                'unique_per_user.required' => 'እባክዎ ሚናው ለእያንዳንዱ ተጠቃሚ ልዩ (Unique) መሆን አለመሆኑን ይግለጹ',
                'unique_per_user.boolean' => 'ልክ ያልሆነ የልዩነት አይነት፤ እሴቱ true ወይም false መሆን አለበት',
                'unique_per_entity.required' => 'እባክዎ ሚናው ለእያንዳንዱ ተቋም/ቅርንጫፍ ልዩ (Unique) መሆን አለመሆኑን ይግለጹ',
                'unique_per_entity.boolean' => 'ልክ ያልሆነ የልዩነት አይነት፤ እሴቱ true ወይም false መሆን አለበት',
            ],

            'roles' => [
                'name.required' => 'እባክዎ የሚና ስም (Role name) ያስገቡ',
                'name.unique' => 'በዚህ ስም የተመዘገበ ሚና ቀድሞ አለ',
                'is_system.required' => 'እባክዎ ሚናው የስርዓቱ (System role) መሆን አለመሆኑን ይግለጹ',
                'is_system.boolean' => 'ልክ ያልሆነ የሚና አይነት፤ እሴቱ true ወይም false መሆን አለበት',
                'state.required' => 'እባክዎ ሁኔታውን (State) ይግለጹ',
                'state.in' => 'የሚና ሁኔታ መሆን ያለበት፦ ' . implode(', ', State::typeNames()),

                'permissions.array' => 'ልክ ያልሆኑ ፈቃዶች',
                'permissions.required' => 'እባክዎ ፈቃዶችን ያቅርቡ',
                'permissions.min' => 'እባክዎ ቢያንስ 1 ፈቃድ ይምረጡ',
                'permissions.*.integer' => 'ልክ ያልሆነ የፈቃድ አይነት',
                'permissions.*.exists' => 'የተወሰኑ ፈቃዶች አልተገኙም',
                'permissions.*.distinct' => 'እባክዎ አንድን ፈቃድ ከአንድ ጊዜ በላይ አይምረጡ',

                'unique_per_user.required' => 'እባክዎ ሚናው ለእያንዳንዱ ተጠቃሚ ልዩ መሆን አለመሆኑን ይግለጹ',
                'unique_per_user.boolean' => 'ልክ ያልሆነ የልዩነት አይነት፤ እሴቱ true ወይም false መሆን አለበት',
                'unique_per_entity.required' => 'እባክዎ ሚናው ለእያንዳንዱ ተቋም/ቅርንጫፍ ልዩ መሆን አለመሆኑን ይግለጹ',
                'unique_per_entity.boolean' => 'ልክ ያልሆነ የልዩነት አይነት፤ እሴቱ true ወይም false መሆን አለበት',
            ],

            'permission_group' => [
                'name.required' => 'እባክዎ የቡድን ስም ያስገቡ',
                'name.unique' => 'ይህ የፈቃድ ቡድን ቀደም ብሎ ተመዝግቧል',
                'permission_group_id.exists' => 'የፈቃድ ቡድኑ አልተገኘም',
            ],

            'user_role_binding' => [
                'role_id.required' => 'ሚና (Role) መመረጥ አለበት',
                'role_id.exists' => 'እባክዎ ትክክለኛ ሚና ይምረጡ',

                'entity_id.exists' => 'እባክዎ ትክክለኛ ተቋም/ቅርንጫፍ ይምረጡ',
                'include_descendants.required' => 'የበታች ቅርንጫፎች መካተት አለመካተታቸውን መግለጽ አለብዎት',
                'include_descendants.boolean' => 'የበታች ቅርንጫፎችን ለማካተት ትክክለኛ ምርጫ ይምረጡ',

                'ends_at.date' => 'የማቂያ ቀን በትክክለኛ የቀን ፎርማት መሆን አለበት',
                'ends_at.after' => 'የማቂያው ቀን ከዛሬ ወይም ከወደፊት ቀን መሆን አለበት።',
                'starts_at.date' => 'እባክዎ ትክክለኛ የቀን እሴት ይምረጡ።',
                'starts_at.after' => 'ይህንን ሚና መተግበር መጀመር ያለበት ከዛሬ ወይም ከወደፊት ቀን መሆን አለበት።',
                'starts_at.required' => 'ይህ ሚና ለተጠቃሚው ከማንኛው ቀን ጀምሮ እንደሚተገበር መግለጽ አለብዎት',

                'descendants.array' => 'ልክ ያልሆኑ የበታች ቅርንጫፎች',
                'descendants.min' => 'ቢያንስ አንድ የበታች ቅርንጫፍ መምረጥ አለብዎት',
                'descendants.*.entity_id.required' => 'እባክዎ የበታች ቅርንጫፍ ይምረጡ',
                'descendants.*.entity_id.exists' => 'የቀረበው ተቋም/ቅርንጫፍ ልክ አይደለም',
                'descendants.*.include_descendants.required' => 'እባክዎ የበታች ቅርንጫፉ የራሱ የሆኑ ተጨማሪ የበታች ቅርንጫፎች እንዳሉት ወይም እንደሌሉት ይምረጡ',
                'descendants.*.include_descendants.boolean' => 'የበታች ቅርንጫፎችን ለማካተት ትክክለኛ ምርጫ ይምረጡ',
            ],

            'user_permission_override' => [
                'permission_ids.required' => 'ፈቃዶች መመረጥ አለባቸው',
                'permission_ids.array' => 'ልክ ያልሆነ ፈቃድ',
                'permission_ids.min' => 'እባክዎ ቢያንስ 1 ፈቃድ ይምረጡ',
                'permission_ids.exists' => 'የተወሰኑት ፈቃዶች ትክክል አይደሉም',

                'entity_id.exists' => 'እባክዎ ትክክለኛ ተቋም/ቅርንጫፍ ይምረጡ',
                'include_descendants.required_if' => 'የበታች ቅርንጫፎች መካተት አለመካተታቸውን መግለጽ አለብዎት',
                'include_descendants.boolean' => 'የበታች ቅርንጫፎችን ለማካተት ትክክለኛ ምርጫ ይምረጡ',
                'allow.required' => 'ፈቃዱ መፍቀድ (Allow) ወይም መከልከል እንዳለበት መግለጽ አለብዎት',
                'allow.boolean' => 'ፈቃዱን ለመፍቀድ/ለመከልከል ትክክለኛ ምርጫ ይምረጡ',

                'ends_at.date' => 'የማቂያ ቀን በትክክለኛ የቀን ፎርማት መሆን አለበት',
                'ends_at.after' => 'የማቂያው ቀን ከዛሬ ወይም ከወደፊት ቀን መሆን አለበት።',
                'starts_at.date' => 'እባክዎ ትክክለኛ የቀን እሴት ይምረጡ።',
                'starts_at.after' => 'ይህንን ፈቃድ መተግበር መጀመር ያለበት ከዛሬ ወይም ከወደፊት ቀን መሆን አለበት።',
                'starts_at.required' => 'ይህ ፈቃድ ለተጠቃሚው ከማንኛው ቀን ጀምሮ እንደሚተገበር መግለጽ አለብዎት',

                'descendants.array' => 'ልክ ያልሆኑ የበታች ቅርንጫፎች',
                'descendants.min' => 'ቢያንስ አንድ የበታች ቅርንጫፍ መምረጥ አለብዎት',
                'descendants.*.entity_id.required' => 'እባክዎ የበታች ቅርንጫፍ ይምረጡ',
                'descendants.*.entity_id.exists' => 'የቀረበው ተቋም/ቅርንጫፍ ልክ አይደለም',
                'descendants.*.include_descendants.required' => 'እባክዎ የበታች ቅርንጫፉ የራሱ የሆኑ ተጨማሪ የበታች ቅርንጫፎች እንዳሉት ወይም እንደሌሉት ይምረጡ',
                'descendants.*.include_descendants.boolean' => 'የበታች ቅርንጫፎችን ለማካተት ትክክለኛ ምርጫ ይምረጡ',
            ],

            'currency' => 'ምንዛሪ',

            // Payment Method Messages

            // Payment Provider Messages

            // Accepted Payment (Entity Payment Method) Messages
            'user_successfully_activated' => '{{name}} የሚባል ተጠቃሚ በተሳካ ሁኔታ ነቅቷል',
            'user_successfully_deactivated' => '{{name}} የሚባል ተጠቃሚ በትክክል ዲአክቲቬት ሆኗል',
            'role_not_found' => 'ሚና አልተገኘም።',
            'permission_not_found' => 'ፍቃድ አልተገኘም።',
            'user_successfully_deleted' => 'ተጠቃሚው በተሳካ ሁኔታ ተሰርዟል',
            'action_not_found' => 'እርምጃው አልተገኘም',
            'users_activated_successfully' => 'ተጠቃሚዎች በተሳካ ሁኔታ ነቅተዋል',
            'users_deactivated_successfully' => 'ተጠቃሚዎች በተሳካ ሁኔታ ተዝግተዋል',
            'users_deleted_successfully' => 'ተጠቃሚዎች በተሳካ ሁኔታ ተሰርዘዋል',
            'bulk_action_failed' => 'የጅምላ እርምጃ አልተሳካም',
            'unautheticated' => 'ያልተረጋገጠ',
            'unautheticated_login_please_try_again' => 'ያልተረጋገጠ መግቢያ. እባክዎ እንደገና ይሞክሩ.',
            'validation_error' => 'የማረጋገጫ ስህተት',
            'action_not_found_or_unauthorized' => 'እርምጃው አልተገኘም ወይም ፍቃድ የለዎትም',
            'duplicate_lookup_value_name' => 'የተባዛ ፍለጋ ዋጋ ስም',
            'lookup_value' => 'የፍለጋ ዋጋ',
            'duplicate_lookup_type_name' => 'የተባዛ ፍለጋ አይነት ስም',
            'unable_to_create_lookup_type' => 'የመፈለጊያ አይነት መፍጠር አልተቻለም',
            'lookup_type_not_found' => 'የፍለጋ አይነት አልተገኘም።',
            'unable_to_update_lookup_type' => 'የፍለጋ አይነት ማዘመን አልተቻለም',
            'lookup_type_is_system_cannot_delete' => 'የፍለጋ አይነት ስርዓት መሰረዝ አይችልም።',
            'lookup_type_successfully_deleted' => 'የፍለጋ አይነት በተሳካ ሁኔታ ተሰርዟል።',
            'lookup_value_does_not_belong_to_type' => 'የፍለጋ ዋጋ የአይነት አይደለም።',
            'lookup_type_status_updated' => 'የፍለጋ አይነት ሁኔታ ተዘምኗል',
            'unable_to_create_lookup_value' => 'የመፈለጊያ እሴት መፍጠር አልተቻለም',
            'lookup_value_not_found' => 'የመፈለጊያ ዋጋ አልተገኘም።',
            'unable_to_update_lookup_value' => 'የመፈለጊያ ዋጋን ማዘመን አልተቻለም',
            'lookup_value_successfully_deleted' => 'የመፈለጊያ ዋጋ በተሳካ ሁኔታ ተሰርዟል።',
            'unable_to_reorder_lookup_values' => 'የመፈለጊያ ዋጋዎችን እንደገና መደርደር አልተቻለም',
            'lookup_values_reordered' => 'የፍለጋ ዋጋዎች እንደገና ተደርገዋል።',
            'lookup_transition_values_must_belong_to_same_type' => 'የፍተሻ ሽግግር ዋጋዎች አንድ አይነት መሆን አለባቸው',
            'lookup_transition_already_exists' => 'የፍለጋ ሽግግር አስቀድሞ አለ።',
            'unable_to_create_lookup_transition' => 'የመፈለጊያ ሽግግር መፍጠር አልተቻለም',
            'lookup_transition_successfully_created' => 'የፍለጋ ሽግግር በተሳካ ሁኔታ ተፈጥሯል።',
            'lookup_transition_not_found' => 'የፍለጋ ሽግግር አልተገኘም።',
            'lookup_transition_is_system_cannot_delete' => 'የፍለጋ ሽግግር ስርዓቱ መሰረዝ አይችልም',
            'lookup_transition_successfully_deleted' => 'የፍለጋ ሽግግር በተሳካ ሁኔታ ተሰርዟል።',
            'lookup_type_successfully_activated' => 'የፍለጋ አይነት {{name}} በትክክል ነቁ ተደርጓል',
            'lookup_type_successfully_deactivated' => 'የፍለጋ አይነት {{name}} በትክክል እንዲጠፋ (Inactive) ተደርጓል',
            'lookup_type_successfully_updated' => 'የፍለጋ አይነት {{name}} በትክክል ተስተካክሏል',
            'lookup_type_successfully_created' => 'የፍለጋ አይነት {{name}} በትክክል ተፈጥሯል',
            'lookup_value_successfully_created' => 'የፍለጋ እሴት {{name}} በትክክል ተፈጥሯል',
            'lookup_value_successfully_updated' => 'የፍለጋ እሴት {{name}} በትክክል ተስተካክሏል',
            'lookup_value_successfully_activated' => 'የፍለጋ እሴት {{name}} በትክክል ነቁ ተደርጓል',
            'lookup_value_successfully_deactivated' => 'የፍለጋ እሴት {{name}} በትክክል እንዲጠፋ (Inactive) ተደርጓል',
            'parent_must_be_higher_level' => 'ወላጅ ከአሁኑ ደረጃ የበለጠ ከፍ ያለ መሆን አለበት',

            'unable_to_delete_measureemnt_conversion' => 'የመለኪያ መቀየሪያ መሰረዝ አልተቻለም',
            'supplier' => 'አቅራቢ',
            'employee' => 'ሰራተኛ',







            'lookup_type_not_accessible' => 'የማጣቀሻ አይነቱ ሊገኝ አልቻለም',
            'lookup_value_status_updated' => 'የማጣቀሻ እሴቱ ሁኔታ ተስተካክሏል',
            'parent_id_required' => 'የወላጅ መታወቂያ ያስፈልጋል',
            'updateDecimalSupport' => 'የአስርዮሽ ድጋፍ ያዘምኑ',
            'operation_lookup_value_could_not_be_found' => 'የክዋኔ ፍለጋ ዋጋ ሊገኝ አልቻለም',
            'invalid_sequence_ids' => 'ልክ ያልሆኑ ተከታታይ መታወቂያዎች',
            'invalid_operation_ids' => 'ልክ ያልሆኑ የክወና መታወቂያዎች',
            'provide_request_data' => 'የጥያቄ ውሂብ ያቅርቡ',

            'reassign_target_required' => 'ይህ ቡድን አይከኖችን ስለያዘ የመመደብ ቡድን ያስፈልጋል',
            'invalid_reassign_target' => 'ልክ ያልሆነ የመመደብ ቡድን',
            'invalid_svg_payload' => 'ልክ ያልሆነ SVG ይዘት',
            'svg_too_large' => 'SVG ፋይል ከ64KB በላይ ነው',
            'invalid_image_payload' => 'ልክ ያልሆነ የምስል ይዘት',
            'image_too_large_or_invalid' => 'ምስሉ ትልቅ ወይም ልክ ያልሆነ ነው',
            'duplicate_content_hash' => 'በዚህ ቡድን ውስጥ ቀደም ብሎ ያለ አዶ ቅጂ',

            'icon' => [
                'state.required' => 'እባክዎ የአይከን ሁኔታ ይምረጡ',
                'state.in' => 'ሁኔታ ንቁ ወይም ያልነቁ መሆን አለበት',
            ],

            // Custom Field Engine
            'model_list_created_successfully' => 'የመዝገብ አይነት በተሳካ ሁኔታ ተመዝግቧል',
            'model_list_updated_successfully' => 'የመዝገብ አይነት በተሳካ ሁኔታ ተዘምኗል',
            'model_list_deleted_successfully' => 'የመዝገብ አይነት በተሳካ ሁኔታ ተወግዷል',
            'model_list_state_changed' => 'የመዝገብ አይነት ሁኔታ በተሳካ ሁኔታ ተቀይሯል',
            'model_list_not_found' => 'የመዝገብ አይነት አልተገኘም',
            'model_list_has_fields' => 'መሰረዝ አይቻልም፦ ይህ የመዝገብ አይነት አሁንም ብጁ መስኮች አሉት',
            'unable_to_create_model_list' => 'የመዝገብ አይነት መመዝገብ አልተቻለም',
            'model_list_already_exists' => 'ይህ የመዝገብ አይነት ለብጁ መስኮች ቀደም ብሎ ተመዝግቧል',

            'invalid_status_transition' => 'ልክ ያልሆነ የሁኔታ ሽግግር',




            'override_requires_exactly_one_target' => 'ሽፋን ከሞጁል ወይም ከባህሪ አንዱን ብቻ መምረጥ አለበት',




            'no_next_step_found' => 'ምንም ቀጣይ እርምጃ አልተገኘም።',
            'you_are_not_eligible_for_next_step' => 'ለሚቀጥለው ደረጃ ብቁ አይደሉም',
            'current_step_not_found' => 'የአሁኑ እርምጃ አልተገኘም።',
            'user_cache_not_found' => 'የተጠቃሚ መሸጎጫ አልተገኘም።',
            'no_first_step_found' => 'ምንም የመጀመሪያ እርምጃ አልተገኘም።',




            'max_quantity' => 'ከፍተኛ መጠን',
            'min_quantity' => 'ዝቅተኛ መጠን',
            'status_lookup_value_not_found' => 'የሁኔታ ፍለጋ ዋጋ አልተገኘም።',
        ];
    }
}