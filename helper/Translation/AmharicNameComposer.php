<?php

namespace Helper\Translation;

use Translation\Back\Amharic;

/**
 * Composes the Amharic label for a seeded permission / permission-group / role
 * name out of its English counterpart.
 *
 */
class AmharicNameComposer {

    /** Per-process memo of autoTranslate() fallbacks, keyed by English name. */
    private static array $autoTranslateCache = [];

    /** Trailing word that marks a permission-group label. */
    private const MANAGEMENT = 'አስተዳደር';

    /** Action verbs that open a "<Verb> <Noun>" permission name. */
    private const VERBS = [
        'See' => 'ማየት',
        'Create' => 'መፍጠር',
        'Update' => 'ማስተካከል',
        'Delete' => 'መሰረዝ',
        'Add' => 'መጨመር',
        'Edit' => 'ማረም',
        'Import' => 'ማስገባት',
        'Approve' => 'ማጽደቅ',
        'Cancel' => 'ማቋረጥ',
        'Complete' => 'ማጠናቀቅ',
        'Remove' => 'ማስወገድ',
        'Change' => 'መቀየር',
    ];

    /** Trailing aspect word of a "Change <Noun> <Aspect>" toggle. */
    private const ASPECTS = [
        'State' => 'ሁኔታ',
        'Status' => 'ሁኔታ',
        'Type' => 'ዓይነት',
    ];

    /** Noun phrases (lower-cased key) shared across core + modules. */
    private const NOUNS = [
        // ---- core ----
        'dashboard' => 'ዳሽቦርድ',
        'user' => 'ተጠቃሚ',
        'role' => 'ሚና',
        'permission' => 'ፍቃድ',
        'permission group' => 'የፍቃድ ቡድን',
        'role permission' => 'የሚና ፍቃድ',
        'module' => 'ሞዱል',
        'module group' => 'የሞዱል ቡድን',
        'module subscription' => 'የሞዱል ምዝገባ',
        'feature' => 'ባህሪ',
        'feature subscription' => 'የባህሪ ምዝገባ',
        'sector' => 'ዘርፍ',
        'entity' => 'አካል',
        'entity type' => 'የአካል ዓይነት',
        'entity sector relationship' => 'የአካል ዘርፍ ግንኙነት',
        'entity costing rule' => 'የአካል የወጪ ስሌት ደንብ',
        'costing rule' => 'የወጪ ስሌት ደንብ',
        'currency' => 'ምንዛሬ',
        'base currency' => 'መሰረታዊ ምንዛሬ',
        'tax' => 'ግብር',
        'tax center' => 'የግብር ማዕከል',
        'tax type' => 'የግብር ዓይነት',
        'tax policy' => 'የግብር ፖሊሲ',
        'tax rate' => 'የግብር ምጣኔ',
        'tax rate policy' => 'የግብር ምጣኔ ፖሊሲ',
        'tax hold rate' => 'የተቀናሽ ግብር ምጣኔ',
        'withhold rule' => 'የተቀናሽ ግብር ደንብ',
        'withhold policy' => 'የተቀናሽ ግብር ፖሊሲ',
        'country' => 'አገር',
        'admin level' => 'የአስተዳደር ደረጃ',
        'admin type' => 'የአስተዳደር ዓይነት',
        'admin unit' => 'የአስተዳደር ክፍል',
        'profile' => 'መገለጫ',
        'location' => 'አካባቢ',
        'location type' => 'የአካባቢ ዓይነት',
        'location type property' => 'የአካባቢ ዓይነት ባህሪ',
        'outlet' => 'መሸጫ',
        'custom field' => 'ብጁ መስክ',
        'dynamic value' => 'ተለዋዋጭ እሴት',
        'measurement' => 'መለኪያ',
        'measurement category' => 'የመለኪያ ምድብ',
        'measurement conversion' => 'የመለኪያ ልወጣ',
        'custom measurement' => 'ብጁ መለኪያ',
        'custom measurement conversion' => 'ብጁ የመለኪያ ልወጣ',
        'document' => 'ሰነድ',
        'document type' => 'የሰነድ ዓይነት',
        'workflow' => 'የስራ ፍሰት',
        'workflow step' => 'የስራ ፍሰት ደረጃ',
        'customer' => 'ደንበኛ',
        'customer group' => 'የደንበኛ ቡድን',
        'supplier' => 'አቅራቢ',
        'employee' => 'ሰራተኛ',
        'icon' => 'አዶ',
        'icon group' => 'የአዶ ቡድን',
        'billing cycle' => 'የክፍያ ዑደት',
        'subscription discount' => 'የምዝገባ ቅናሽ',
        'subscription discount type' => 'የምዝገባ ቅናሽ ዓይነት',
        'pricing category' => 'የዋጋ ምድብ',
        'pricing plan' => 'የዋጋ እቅድ',
        'pricing plan version' => 'የዋጋ እቅድ ስሪት',
        'pricing plan line' => 'የዋጋ እቅድ መስመር',
        'geographic' => 'መልክዓ ምድር',
        'system' => 'ስርዓት',

        // ---- inventory ----
        'item' => 'እቃ',
        'item category' => 'የእቃ ምድብ',
        'brand' => 'ብራንድ',
        'batch' => 'ባች',
        'stock' => 'ክምችት',
        'stock in' => 'የክምችት ግቤት',
        'stock operation' => 'የክምችት ክወና',
        'stock operation batch' => 'የክምችት ክወና ባች',
        'stock operation price' => 'የክምችት ክወና ዋጋ',
        'stock transfer' => 'የክምችት ዝውውር',
        'stock reservation' => 'የክምችት ቦታ ማስያዣ',
        'stock taking session' => 'የክምችት ቆጠራ ክፍለ ጊዜ',
        'stock ledger' => 'የክምችት መዝገብ',
        'stock summary' => 'የክምችት ማጠቃለያ',
        'stock session' => 'የክምችት ክፍለ ጊዜ',
        'inventory valuation layer' => 'የክምችት ግምት ንብርብር',
        'product' => 'ምርት',
        'product group' => 'የምርት ቡድን',
        'product attribute' => 'የምርት መለያ ባህሪ',
        'product attribute value' => 'የምርት መለያ ባህሪ እሴት',
        'product batch' => 'የምርት ባች',
        'product inventory policy' => 'የምርት ክምችት ፖሊሲ',
        'product modifier' => 'የምርት ማሻሻያ',
        'product modifier item' => 'የምርት ማሻሻያ እቃ',
        'product tax rate' => 'የምርት የግብር ምጣኔ',
        'product warranty' => 'የምርት ዋስትና',
        'product warranty period' => 'የምርት የዋስትና ጊዜ',
        'entity product' => 'የአካል ምርት',
        'entity product group' => 'የአካል ምርት ቡድን',
        'warranty' => 'ዋስትና',
        'warranty type' => 'የዋስትና ዓይነት',
        'gate pass' => 'የበር ፍቃድ',
        'gate pass document' => 'የበር ፍቃድ ሰነድ',

        // ---- sales ----
        'combo bundle' => 'ጥቅል',
        'combo bundle config' => 'የጥቅል ውቅር',
        'combo bundle group' => 'የጥቅል ቡድን',
        'combo bundle item' => 'የጥቅል እቃ',
        'pricelist' => 'የዋጋ ዝርዝር',
        'pricelist item' => 'የዋጋ ዝርዝር እቃ',
        'promotion' => 'ማስተዋወቂያ',
        'promotion condition' => 'የማስተዋወቂያ ሁኔታ',
        'promotion reward' => 'የማስተዋወቂያ ሽልማት',
        'loyalty' => 'ታማኝነት',
        'loyalty account' => 'የታማኝነት ሒሳብ',
        'customer loyalty account' => 'የደንበኛ ታማኝነት ሒሳብ',
        'loyalty conversion rule' => 'የታማኝነት ልወጣ ደንብ',
        'loyalty rule' => 'የታማኝነት ደንብ',
        'loyalty tier' => 'የታማኝነት ደረጃ',
        'loyalty point ledger' => 'የታማኝነት ነጥብ መዝገብ',
        'loyalty redemption' => 'የታማኝነት ቤዛ',
        'tier' => 'ደረጃ',
    ];

    /** Irregular names whose Amharic does not follow the verb/noun grammar. */
    private const OVERRIDES = [
        'Assign Role To User' => 'ሚናን ለተጠቃሚ መመደብ',
        'Assign Permission To User' => 'ፍቃድን ለተጠቃሚ መመደብ',
        'Edit User Role Binding' => 'የተጠቃሚ ሚና ቁርኝት ማረም',
        'Chnage Role Status' => 'የሚና ሁኔታ መቀየር',

        'Link Modules To Document Type' => 'ሞዱሎችን ከሰነድ ዓይነት ጋር ማገናኘት',
        'Link Operations To Document Type' => 'ክዋኔዎችን ከሰነድ ዓይነት ጋር ማገናኘት',
        'Link Sequences To Document Type' => 'ቅደም ተከተሎችን ከሰነድ ዓይነት ጋር ማገናኘት',
        'Remove Modules From Document Type' => 'ሞዱሎችን ከሰነድ ዓይነት ማስወገድ',
        'Remove Operations From Document Type' => 'ክዋኔዎችን ከሰነድ ዓይነት ማስወገድ',
        'Remove Sequences From Document Type' => 'ቅደም ተከተሎችን ከሰነድ ዓይነት ማስወገድ',
        'Change Document Type Modules Status' => 'የሰነድ ዓይነት ሞዱሎች ሁኔታ መቀየር',
        'Change Document Type Operations Status' => 'የሰነድ ዓይነት ክዋኔዎች ሁኔታ መቀየር',

        'Entity Add Product Attribute' => 'አካል ላይ የምርት መለያ ባህሪ መጨመር',
        'Entity Add Product Attribute Value' => 'አካል ላይ የምርት መለያ ባህሪ እሴት መጨመር',

        'Change State of product' => 'የምርት ሁኔታ መቀየር',
        'Change State of product alternative' => 'የምርት አማራጭ ሁኔታ መቀየር',
        'Change State of product attribute value' => 'የምርት መለያ ባህሪ እሴት ሁኔታ መቀየር',
        'Change State of product ingredient' => 'የምርት ግብዓት ሁኔታ መቀየር',
        'Change State of product ingredient alternative' => 'የምርት ግብዓት አማራጭ ሁኔታ መቀየር',
        'Change State of product inventory policy' => 'የምርት ክምችት ፖሊሲ ሁኔታ መቀየር',
        'Change State of product variant option' => 'የምርት ልዩነት አማራጭ ሁኔታ መቀየር',
        'Change State Product Attribute' => 'የምርት መለያ ባህሪ ሁኔታ መቀየር',
        'Change Status Product Attribute' => 'የምርት መለያ ባህሪ ሁኔታ መቀየር',
        'Change Status Product Attribute Value' => 'የምርት መለያ ባህሪ እሴት ሁኔታ መቀየር',

        'Role & Permission Management' => 'የሚና እና ፍቃድ አስተዳደር',

        // role labels
        'Super Admin' => 'ሱፐር አስተዳዳሪ',
        'Branch Admin' => 'የቅርንጫፍ አስተዳዳሪ',
        'Branch Shop Admin' => 'የቅርንጫፍ ሱቅ አስተዳዳሪ',
        'Organization Admin' => 'የድርጅት አስተዳዳሪ',
        'Business Group Admin' => 'የንግድ ቡድን አስተዳዳሪ',
    ];

    /**
     * Compose the Amharic label for an English permission / group / role name.
     *
     * Resolves through the curated verb/noun rules first. If the result still
     * carries Latin letters (an unmapped verb or noun), it falls back to
     * autoTranslate() unless that is disabled.
     *
     * @param string $english
     * @param bool $useAutoTranslateFallback
     *
     * @return string the Amharic label
     */
    public static function compose(string $english, bool $useAutoTranslateFallback = false): string {
        $name = trim($english);
        $composed = self::composeFromRules($name);

        if ($useAutoTranslateFallback && self::hasUntranslated($composed)) {
            return self::$autoTranslateCache[$name] ??= autoTranslate($name, Amharic::getKey());
        }

        return $composed;
    }

    /**
     * Apply the curated verb/noun grammar, leaving Latin text where a mapping
     * is missing (the signal the hybrid fallback keys off).
     *
     * @param string $name
     * @return string
     */
    private static function composeFromRules(string $name): string {
        if (isset(self::OVERRIDES[$name])) {
            return self::OVERRIDES[$name];
        }

        $words = preg_split('/\s+/', $name);
        $first = $words[0];
        $last = $words[count($words) - 1];

        // "<Noun> Management" -> "የ<Noun> አስተዳደር"
        if ($last === 'Management') {
            $noun = implode(' ', array_slice($words, 0, -1));

            return self::genitive(self::noun($noun)) . ' ' . self::MANAGEMENT;
        }

        // "Change <Noun> <Aspect>" -> "የ<Noun> <Aspect> መቀየር"
        if ($first === 'Change' && isset(self::ASPECTS[$last])) {
            $noun = implode(' ', array_slice($words, 1, -1));

            return self::genitive(self::noun($noun)) . ' ' . self::ASPECTS[$last] . ' ' . self::VERBS['Change'];
        }

        // "<Verb> <Noun>" -> "<Noun> <verb>"
        if (isset(self::VERBS[$first]) && $first !== 'Change') {
            $noun = implode(' ', array_slice($words, 1));

            return self::noun($noun) . ' ' . self::VERBS[$first];
        }

        return $name;
    }

    /**
     * Look up the Amharic form of a noun phrase (case-insensitive).
     *
     * @param string $english
     * @return string
     */
    private static function noun(string $english): string {
        $key = mb_strtolower(trim($english));

        return self::NOUNS[$key] ?? $english;
    }

    /**
     * Prefix the genitive የ- marker, unless the noun already carries one.
     *
     * @param string $amharic
     * @return string
     */
    private static function genitive(string $amharic): string {
        return str_starts_with($amharic, 'የ') ? $amharic : 'የ' . $amharic;
    }

    /**
     * Whether a rule-composed label still carries untranslated English -- the
     * Latin letters left behind by an unmapped verb or noun.
     *
     * @param string $composed
     * @return bool
     */
    private static function hasUntranslated(string $composed): bool {
        return (bool) preg_match('/[A-Za-z]/', $composed);
    }
}
