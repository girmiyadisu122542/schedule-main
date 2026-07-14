<?php


return getAllModuleTranslations(
    basename(__DIR__),
    basename(__FILE__),
    [

        /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

        'accepted' => 'የ :attribute መስክ መቀበል አለበት።',
        'accepted_if' => 'የ :attribute መስክ መቀበል አለበት ሲሆን :other :value ነው።',
        'active_url' => 'የ :attribute መስክ ትክክለኛ URL መሆኑ አለበት።',
        'after' => 'የ :attribute መስክ ከ:date ቀን በኋላ መሆኑ አለበት።',
        'after_or_equal' => 'የ :attribute መስክ ከ:date ቀን በኋላ ወይም እኩል መሆኑ አለበት።',
        'alpha' => 'የ :attribute መስክ ፊደላት ብቻ መያዝ አለበት።',
        'alpha_dash' => 'የ :attribute መስክ ፊደላት፣ ቁጥሮች፣ ያልተለመዱ ምልክቶች እና አንደርስኮሮች ብቻ መያዝ አለበት።',
        'alpha_num' => 'የ :attribute መስክ ፊደላት እና ቁጥሮች ብቻ መያዝ አለበት።',
        'any_of' => 'የ :attribute መስክ ልክ አይደለም።',
        'array' => 'የ :attribute መስክ የአራይ መሆኑ አለበት።',
        'ascii' => 'የ :attribute መስክ ነጠላ ባይት አልፋኑሜሪክ ባህሪያት እና ምልክቶች ብቻ መያዝ አለበት።',
        'before' => 'የ :attribute መስክ ከ:date ቀን ቀድሞ መሆኑ አለበት።',
        'before_or_equal' => 'የ :attribute መስክ ከ:date ቀን ቀድሞ ወይም እኩል መሆኑ አለበት።',
        'between' => [
            'array' => 'የ :attribute መስክ ከ:min እና :max እንጂያት መሆኑ አለበት።',
            'file' => 'የ :attribute መስክ ከ:min እና :max ኪሎባይቶች መሆኑ አለበት።',
            'numeric' => 'የ :attribute መስክ ከ:min እና :max መሆኑ አለበት።',
            'string' => 'የ :attribute መስክ ከ:min እና :max ባህሪያት መሆኑ አለበት።',
        ],
        'boolean' => 'የ :attribute መስክ እውነት ወይም ሐሰት መሆኑ አለበት።',
        'can' => 'የ :attribute መስክ ያልተለመደ እሴት ያያዘበታል።',
        'confirmed' => 'የ :attribute መስክ ማረጋገጫ አይዛመድም።',
        'contains' => 'የ :attribute መስክ የሚያስፈልገው እሴት ያልተለመደ ነው።',
        'current_password' => 'የይለፍ ቃል ልክ አይደለም።',
        'date' => 'የ :attribute መስክ ትክክለኛ ቀን መሆኑ አለበት።',
        'date_equals' => 'የ :attribute መስክ ከ:date ቀን እኩል መሆኑ አለበት።',
        'date_format' => 'የ :attribute መስክ ከ:format ቅርጸት መዛመድ አለበት።',
        'decimal' => 'የ :attribute መስክ :decimal የዲሲማል ቦታዎች መሆኑ አለበት።',
        'declined' => 'የ :attribute መስክ መተው አለበት።',
        'declined_if' => 'የ :attribute መስክ መተው አለበት ሲሆን :other :value ነው።',
        'different' => 'የ :attribute መስክ እና :other የተለያዩ መሆናቸው አለበት።',
        'digits' => 'የ :attribute መስክ :digits አሃዞች መሆኑ አለበት።',
        'digits_between' => 'የ :attribute መስክ ከ:min እና :max አሃዞች መሆኑ አለበት።',
        'dimensions' => 'የ :attribute መስክ ልክ ያልሆኑ የምስል መጠኖች አሉት።',
        'distinct' => 'የ :attribute መስክ የተባዛ እሴት አለው።',
        'doesnt_end_with' => 'የ :attribute መስክ ከሚከተሉት አንዱ መጨረሻ አይሆንም፡ :values።',
        'doesnt_start_with' => 'የ :attribute መስክ ከሚከተሉት አንዱ መጀመር አይሆንም፡ :values።',
        'email' => 'የ :attribute መስክ ትክክለኛ የኢሜይል አድራሻ መሆኑ አለበት።',
        'ends_with' => 'የ :attribute መስክ ከሚከተሉት አንዱ መጨረሻ መሆኑ አለበት፡ :values።',
        'enum' => 'የተለመደው :attribute ልክ አይደለም።',
        'exists' => 'የተለመደው :attribute ልክ አይደለም።',
        'extensions' => 'የ :attribute መስክ ከሚከተሉት አንዱ ቅጥያዎች መሆኑ አለበት፡ :values።',
        'file' => 'የ :attribute መስክ ፋይል መሆኑ አለበት።',
        'filled' => 'የ :attribute መስክ እሴት መሆኑ አለበት።',
        'gt' => [
            'array' => 'የ :attribute መስክ ከ:value እንጂያት በላይ መሆኑ አለበት።',
            'file' => 'የ :attribute መስክ ከ:value ኪሎባይቶች በላይ መሆኑ አለበት።',
            'numeric' => 'የ :attribute መስክ ከ:value በላይ መሆኑ አለበት።',
            'string' => 'የ :attribute መስክ ከ:value ባህሪያት በላይ መሆኑ አለበት።',
        ],
        'gte' => [
            'array' => 'የ :attribute መስክ :value እንጂያት ወይም በላይ መሆኑ አለበት።',
            'file' => 'የ :attribute መስክ :value ኪሎባይቶች ወይም በላይ መሆኑ አለበት።',
            'numeric' => 'የ :attribute መስክ :value ወይም በላይ መሆኑ አለበት።',
            'string' => 'የ :attribute መስክ :value ባህሪያት ወይም በላይ መሆኑ አለበት።',
        ],
        'hex_color' => 'የ :attribute መስክ ትክክለኛ የሄክሳዲሲማል ቀለም መሆኑ አለበት።',
        'image' => 'የ :attribute መስክ ምስል መሆኑ አለበት።',
        'in' => 'የተለመደው :attribute ልክ አይደለም።',
        'in_array' => 'የ :attribute መስክ በ:other መሆኑ አለበት።',
        'in_array_keys' => 'የ :attribute መስክ ከሚከተሉት ቢያንስ አንዱ ቁልፍ መያዝ አለበት፡ :values።',
        'integer' => 'የ :attribute መስክ ኢንቲጀር መሆኑ አለበት።',
        'ip' => 'የ :attribute መስክ ትክክለኛ የIP አድራሻ መሆኑ አለበት።',
        'ipv4' => 'የ :attribute መስክ ትክክለኛ የIPv4 አድራሻ መሆኑ አለበት።',
        'ipv6' => 'የ :attribute መስክ ትክክለኛ የIPv6 አድራሻ መሆኑ አለበት።',
        'json' => 'የ :attribute መስክ ትክክለኛ የJSON ሕብረቁምፊ መሆኑ አለበት።',
        'list' => 'የ :attribute መስክ ዝርዝር መሆኑ አለበት።',
        'lowercase' => 'የ :attribute መስክ ትንሽ ፊደላት መሆኑ አለበት።',
        'lt' => [
            'array' => 'የ :attribute መስክ ከ:value እንጂያት በታች መሆኑ አለበት።',
            'file' => 'የ :attribute መስክ ከ:value ኪሎባይቶች በታች መሆኑ አለበት።',
            'numeric' => 'የ :attribute መስክ ከ:value በታች መሆኑ አለበት።',
            'string' => 'የ :attribute መስክ ከ:value ባህሪያት በታች መሆኑ አለበት።',
        ],
        'lte' => [
            'array' => 'የ :attribute መስክ ከ:value እንጂያት በላይ አይሆንም።',
            'file' => 'የ :attribute መስክ ከ:value ኪሎባይቶች በታች ወይም እኩል መሆኑ አለበት።',
            'numeric' => 'የ :attribute መስክ ከ:value በታች ወይም እኩል መሆኑ አለበት።',
            'string' => 'የ :attribute መስክ ከ:value ባህሪያት በታች ወይም እኩል መሆኑ አለበት።',
        ],
        'mac_address' => 'የ :attribute መስክ ትክክለኛ የMAC አድራሻ መሆኑ አለበት።',
        'max' => [
            'array' => 'የ :attribute መስክ ከ:max እንጂያት በላይ አይሆንም።',
            'file' => 'የ :attribute መስክ ከ:max ኪሎባይቶች በላይ አይሆንም።',
            'numeric' => 'የ :attribute መስክ ከ:max በላይ አይሆንም።',
            'string' => 'የ :attribute መስክ ከ:max ባህሪያት በላይ አይሆንም።',
        ],
        'max_digits' => 'የ :attribute መስክ ከ:max አሃዞች በላይ አይሆንም።',
        'mimes' => 'የ :attribute መስክ ከሚከተሉት የፋይል አይነቶች አንዱ መሆኑ አለበት፡ :values።',
        'mimetypes' => 'የ :attribute መስክ ከሚከተሉት የፋይል አይነቶች አንዱ መሆኑ አለበት፡ :values።',
        'min' => [
            'array' => 'የ :attribute መስክ ቢያንስ :min እንጂያት መሆኑ አለበት።',
            'file' => 'የ :attribute መስክ ቢያንስ :min ኪሎባይቶች መሆኑ አለበት።',
            'numeric' => 'የ :attribute መስክ ቢያንስ :min መሆኑ አለበት።',
            'string' => 'የ :attribute መስክ ቢያንስ :min ባህሪያት መሆኑ አለበት።',
        ],
        'min_digits' => 'የ :attribute መስክ ቢያንስ :min አሃዞች መሆኑ አለበት።',
        'missing' => 'የ :attribute መስክ የማይኖር መሆኑ አለበት።',
        'missing_if' => 'የ :attribute መስክ የማይኖር መሆኑ አለበት ሲሆን :other :value ነው።',
        'missing_unless' => 'የ :attribute መስክ የማይኖር መሆኑ አለበት እንጂ :other በ:values አይሆንም።',
        'missing_with' => 'የ :attribute መስክ የማይኖር መሆኑ አለበት ሲሆን :values ተያዘ።',
        'missing_with_all' => 'የ :attribute መስክ የማይኖር መሆኑ አለበት ሲሆን :values ተያዙ።',
        'multiple_of' => 'የ :attribute መስክ የ :value ብዜት መሆኑ አለበት።',
        'not_in' => 'የተለመደው :attribute ልክ አይደለም።',
        'not_regex' => 'የ :attribute መስክ ቅርጸት ልክ አይደለም።',
        'numeric' => 'የ :attribute መስክ ቁጥር መሆኑ አለበት።',
        'password' => [
            'letters' => 'የ :attribute መስክ ቢያንስ አንድ ፊደል መያዝ አለበት።',
            'mixed' => 'የ :attribute መስክ ቢያንስ አንድ አቢይ እና አንድ ትንሽ ፊደል መያዝ አለበት።',
            'numbers' => 'የ :attribute መስክ ቢያንስ አንድ ቁጥር መያዝ አለበት።',
            'symbols' => 'የ :attribute መስክ ቢያንስ አንድ ምልክት መያዝ አለበት።',
            'uncompromised' => 'የተሰጠው :attribute በውሂብ ማጣት ውስጥ ተቀራረበ። እባክዎ የተለየ :attribute ይምረጡ።',
        ],
        'present' => 'የ :attribute መስክ መሆኑ አለበት።',
        'present_if' => 'የ :attribute መስክ መሆኑ አለበት ሲሆን :other :value ነው።',
        'present_unless' => 'የ :attribute መስክ መሆኑ አለበት እንጂ :other በ:values አይሆንም።',
        'present_with' => 'የ :attribute መስክ መሆኑ አለበት ሲሆን :values ተያዘ።',
        'present_with_all' => 'የ :attribute መስክ መሆኑ አለበት ሲሆን :values ተያዙ።',
        'prohibited' => 'የ :attribute መስክ የተከለከለ ነው።',
        'prohibited_if' => 'የ :attribute መስክ የተከለከለ ነው ሲሆን :other :value ነው።',
        'prohibited_if_accepted' => 'የ :attribute መስክ የተከለከለ ነው ሲሆን :other ተቀበለ።',
        'prohibited_if_declined' => 'የ :attribute መስክ የተከለከለ ነው ሲሆን :other ተተወ።',
        'prohibited_unless' => 'የ :attribute መስክ የተከለከለ ነው እንጂ :other በ:values አይሆንም።',
        'prohibits' => 'የ :attribute መስክ :other ከመሆኑ ያግዳል።',
        'regex' => 'የ :attribute መስክ ቅርጸት ልክ አይደለም።',
        'required' => 'የ :attribute መስክ ያስፈልጋል።',
        'required_array_keys' => 'የ :attribute መስክ ለሚከተሉት ምዝገባዎች መያዝ አለበት፡ :values።',
        'required_if' => 'የ :attribute መስክ ያስፈልጋል ሲሆን :other :value ነው።',
        'required_if_accepted' => 'የ :attribute መስክ ያስፈልጋል ሲሆን :other ተቀበለ።',
        'required_if_declined' => 'የ :attribute መስክ ያስፈልጋል ሲሆን :other ተተወ።',
        'required_unless' => 'የ :attribute መስክ ያስፈልጋል እንጂ :other በ:values አይሆንም።',
        'required_with' => 'የ :attribute መስክ ያስፈልጋል ሲሆን :values ተያዘ።',
        'required_with_all' => 'የ :attribute መስክ ያስፈልጋል ሲሆን :values ተያዙ።',
        'required_without' => 'የ :attribute መስክ ያስፈልጋል ሲሆን :values አልተያዘም።',
        'required_without_all' => 'የ :attribute መስክ ያስፈልጋል ሲሆን ከ:values ምንም አልተያዘም።',
        'same' => 'የ :attribute መስክ ከ:other መዛመድ አለበት።',
        'size' => [
            'array' => 'የ :attribute መስክ :size እንጂያት መያዝ አለበት።',
            'file' => 'የ :attribute መስክ :size ኪሎባይቶች መሆኑ አለበት።',
            'numeric' => 'የ :attribute መስክ :size መሆኑ አለበት።',
            'string' => 'የ :attribute መስክ :size ባህሪያት መሆኑ አለበት።',
        ],
        'starts_with' => 'የ :attribute መስክ ከሚከተሉት አንዱ መጀመር መሆኑ አለበት፡ :values።',
        'string' => 'የ :attribute መስክ ሕብረቁምፊ መሆኑ አለበት።',
        'timezone' => 'የ :attribute መስክ ትክክለኛ የጊዜ ክልል መሆኑ አለበት።',
        'unique' => 'የ :attribute አስቀድሞ ተወስዷል።',
        'uploaded' => 'የ :attribute መስክ መስቀል አልተሳካም።',
        'uppercase' => 'የ :attribute መስክ አቢይ ፊደላት መሆኑ አለበት።',
        'url' => 'የ :attribute መስክ ትክክለኛ URL መሆኑ አለበት።',
        'ulid' => 'የ :attribute መስክ ትክክለኛ ULID መሆኑ አለበት።',
        'uuid' => 'የ :attribute መስክ ትክክለኛ UUID መሆኑ አለበት።',
        'unique_lang' => 'የ :attribute አስቀድሞ ተወስዷል።',
        'invalid_entity' => 'የ :attribute መስክ ለመጠቀም ፈቃድ የለዎትም።',
        'rich_text_length' => 'የ :attribute መስክ ከ:max ባህሪያት በላይ አይሆንም።',
        'unique_in_type' => 'የ :attribute አስቀድሞ ተወስዷል።',
        'lookup_value_exist' => 'የተመረጠው :attribute ትክክል አይደለም ወይም ሊገኝ አልቻለም።',
        'lookup_value_status_exist' => 'የተመረጠው :attribute ትክክል አይደለም ወይም ሊገኝ አልቻለም።',
        'belongs_to_lookup_value_status_type' => 'የ :attribute ሁኔታ ከማጣቀሻ እሴት ሁኔታ አይነት ጋር አይዛመድም።',
        'invalid_lookup_value' => 'የ :attribute መስክ የተለመደ እሴት አይደለም።',
        'greater_than' => ':attribute ከ :value በላይ መሆን አለበት።',
        'less_than' => ':attribute ከ :value በታች መሆን አለበት።',

        /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

        'custom' => [
            'attribute-name' => [
                'rule-name' => 'custom-message',
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

        'attributes' => require __DIR__ . '/attributes.php',
    ]
);
