<?php

namespace App\Faker;

class AmharicTranslator {

    public const RETURN_EMPTY = 0;
    public const RETURN_SELF = 1;
    private $amharicLetters = [
        'ሀ' => 'Ha', 'ሁ' => 'HU', 'ሂ' => 'HI', 'ሃ' => 'ha', 'ሄ' => 'he', 'ህ' => 'h', 'ሆ' => 'ho',
        'ለ' => 'le', 'ሉ' => 'lu', 'ሊ' => 'li',  'ላ' => 'la', 'ሌ' => 'le', 'ል' => 'l',  'ሎ' => 'lo',
        'ሐ' => 'Ha', 'ሑ' => 'Hu', 'ሒ' => 'Hi',  'ሓ' => 'Ha', 'ሔ' => 'He', 'ሕ' => 'H',  'ሖ' => 'Ho',
        'መ' => 'Me', 'ሙ' => 'Mu', 'ሚ' => 'Mi',  'ማ' => 'Ma', 'ሜ' => 'Me', 'ም' => 'M',  'ሞ' => 'Mo',
        'ሠ' => 'Se', 'ሡ' => 'Su', 'ሢ' => 'Si',  'ሣ' => 'Sa', 'ሤ' => 'Se', 'ሥ' => 'S',  'ሦ' => 'So',
        'ረ' => 'Re', 'ሩ' => 'Ru', 'ሪ' => 'Ri',  'ራ' => 'Ra', 'ሬ' => 'Re', 'ር' => 'R',  'ሮ' => 'Ro',
        'ሰ' => 'Se', 'ሱ' => 'Su', 'ሲ' => 'Si',  'ሳ' => 'Sa', 'ሴ' => 'Se', 'ስ' => 'S',  'ሶ' => 'So',
        'ሸ' => 'She', 'ሹ' => 'Shu', 'ሺ' => 'Shi',  'ሻ' => 'Sha', 'ሼ' => 'She', 'ሽ' => 'Sh',  'ሾ' => 'Sho',
        'ቀ' => 'qe', 'ቁ' => 'qU', 'ቂ' => 'qi',  'ቃ' => 'qa', 'ቄ' => 'qe', 'ቅ' => 'q',  'ቆ' => 'qo',
        'በ' => 'be', 'ቡ' => 'bu', 'ቢ' => 'bi',  'ባ' => 'ba', 'ቤ' => 'be', 'ብ' => 'b',  'ቦ' => 'bo',
        'ተ' => 'Te', 'ቱ' => 'Tu', 'ቲ' => 'Ti',  'ታ' => 'Ta', 'ቴ' => 'Te', 'ት' => 'T',  'ቶ' => 'To',
        'ቸ' => 'Che', 'ቹ' => 'Chu', 'ቺ' => 'Chi',  'ቻ' => 'Cha', 'ቼ' => 'Che', 'ች' => 'Ch',  'ቾ' => 'Cho',
        'ኀ' => 'Ha', 'ኁ' => 'Hu', 'ኂ' => 'Hi',  'ኃ' => 'Ha', 'ኄ' => 'He', 'ኅ' => 'H',  'ኆ' => 'Ho',
        'ነ' => 'Ne', 'ኑ' => 'Nu', 'ኒ' => 'Ni',  'ና' => 'Na', 'ኔ' => 'Ne', 'ን' => 'N',  'ኖ' => 'No',
        'ኘ' => 'Gne', 'ኙ' => 'Gnu', 'ኚ' => 'Gni',  'ኛ' => 'Gna', 'ኜ' => 'Gne', 'ኝ' => 'Gn',  'ኞ' => 'Gno',
        'አ' => 'A', 'ኡ' => 'u', 'ኢ' => 'i',  'ኣ' => 'a', 'ኤ' => 'e', 'እ' => 'l',  'ኦ' => 'lo',
        'ከ' => 'ke', 'ኩ' => 'ku', 'ኪ' => 'ki',  'ካ' => 'ka', 'ኬ' => 'ke', 'ክ' => 'k',  'ኮ' => 'ko',
        'ኸ' => 'He', 'ኹ' => 'Hu', 'ኺ' => 'Hi',  'ኻ' => 'Ha', 'ኼ' => 'He', 'ኽ' => 'H',  'ኾ' => 'Ho',
        'ወ' => 'We', 'ዉ' => 'Wu', 'ዊ' => 'Wi',  'ዋ' => 'Wa', 'ዌ' => 'We', 'ው' => 'W',  'ዎ' => 'Wo',
        'ዐ' => 'A', 'ዑ' => 'u', 'ዒ' => 'i',  'ዓ' => 'A', 'ዔ' => 'E', 'ዕ' => 'E',  'ዖ' => 'O',
        'ዘ' => 'Ze', 'ዙ' => 'Zu', 'ዚ' => 'Zi',  'ዛ' => 'Za', 'ዜ' => 'Ze', 'ዝ' => 'Z',  'ዞ' => 'Zo',
        'ዠ' => 'Zhe', 'ዡ' => 'Zhu', 'ዢ' => 'Zhi',  'ዣ' => 'Zha', 'ዤ' => 'Zhe', 'ዥ' => 'Zh',  'ዦ' => 'Zho',
        'የ' => 'Ye', 'ዩ' => 'Yu', 'ዪ' => 'Yi',  'ያ' => 'Ya', 'ዬ' => 'Ye', 'ይ' => 'Y',  'ዮ' => 'Yo',
        'ደ' => 'De', 'ዱ' => 'Du', 'ዲ' => 'Di',  'ዳ' => 'Da', 'ዴ' => 'De', 'ድ' => 'D',  'ዶ' => 'Do',
        'ጀ' => 'Je', 'ጁ' => 'Ju', 'ጂ' => 'Ji',  'ጃ' => 'Ja', 'ጄ' => 'Je', 'ጅ' => 'J',  'ጆ' => 'Jo',
        'ገ' => 'Ge', 'ጉ' => 'Gu', 'ጊ' => 'Gi',  'ጋ' => 'Ga', 'ጌ' => 'Ge', 'ግ' => 'G',  'ጎ' => 'Go',
        'ጠ' => 'Te', 'ጡ' => 'Tu', 'ጢ' => 'Ti',  'ጣ' => 'Ta', 'ጤ' => 'Te', 'ጥ' => 'T',  'ጦ' => 'To',
        'ጨ' => 'Che', 'ጩ' => 'Chu', 'ጪ' => 'Chi',  'ጫ' => 'Cha', 'ጬ' => 'Che', 'ጭ' => 'Ch',  'ጮ' => 'Cho',
        'ጰ' => 'Pe', 'ጱ' => 'Pu', 'ጲ' => 'Pi',  'ጳ' => 'Pa', 'ጴ' => 'Pe', 'ጵ' => 'P',  'ጶ' => 'Po',
        'ጸ' => 'Tse', 'ጹ' => 'Tsu', 'ጺ' => 'Tsi',  'ጻ' => 'Tsa', 'ጼ' => 'Tse', 'ጽ' => 'Ts',  'ጾ' => 'Tso',
        'ፀ' => 'Tse', 'ፁ' => 'Tsu', 'ፂ' => 'Tsi',  'ፃ' => 'Tsa', 'ፄ' => 'Tse', 'ፅ' => 'Ts',  'ፆ' => 'Tso',
        'ፈ' => 'Fe', 'ፉ' => 'Fu', 'ፊ' => 'Fi',  'ፋ' => 'Fa', 'ፌ' => 'Fe', 'ፍ' => 'F',  'ፎ' => 'Fo',
        'ፐ' => 'Pe', 'ፑ' => 'Pu', 'ፒ' => 'Pi',  'ፓ' => 'Pa', 'ፔ' => 'Pe', 'ፕ' => 'P',  'ፖ' => 'Po',
        'ቨ' => 'Ve', 'ቩ' => 'Vu', 'ቪ' => 'Vi',  'ቫ' => 'Va', 'ቬ' => 'Ve', 'ቭ' => 'V',  'ቮ' => 'Vo',

        'ሏ' => 'lua', 'ሗ' => 'hua', 'ሟ' => 'mua', 'ሷ' => 'sua', 'ሧ' => 'sua', 'ሯ' => 'rua', 'ሿ' => 'shua',
        'ቈ' => 'qie', 'ቍ' => 'que', 'ቊ' => 'que', 'ቋ' => 'qua', 'ቌ' => 'que', 'ቧ' => 'bua', 'ቯ' => 'vua',
        'ቷ' => 'tua', 'ቿ' => 'chua', 'ኈ' => 'hie', 'ኍ' => 'hu', 'ኊ' => 'hu', 'ኌ' => 'hie', 'ኋ' => 'hua',
        'ኗ' => 'nua', 'ኟ' => 'gnua', 'ኰ' => 'kuie', 'ኵ' => 'kue', 'ኲ' => 'kue', 'ኳ' => 'kua', 'ኴ' => 'kua',
        'ዀ' => 'ho', 'ዅ' => 'huo', 'ዂ' => 'hi', 'ዃ' => 'hua', 'ዄ' => 'hua', 'ዟ' => 'zua', 'ዧ' => 'zhua',
        'ዷ' => 'dua', 'ጇ' => 'dua', 'ጐ' => 'go', 'ጕ' => 'gua', 'ጒ' => 'gu', 'ጓ' => 'gua', 'ጔ' => 'gue',
        'ጧ' => 'tua', 'ጯ' => 'chua', 'ጷ' => 'pua', 'ጿ' => 'tsua', 'ፏ' => 'fua', 'ፗ' => 'pua',
    ];

    private $englishLetters = [
        'ha' => 'ሀ' , 'hu' => 'ሁ' , 'hi' => 'ሂ' , 'ha' => 'ሃ' , 'hie' => 'ሄ' , 'h' => 'ህ' , 'ho' => 'ሆ' ,
        'le' => 'ለ' , 'lu' => 'ሉ' , 'li' => 'ሊ' ,  'la' => 'ላ' , 'lie' => 'ሌ' , 'l' => 'ል' ,  'lo' => 'ሎ' ,
        // 'Ha' => 'ሐ' , 'Hu' => 'ሑ' , 'Hi' => 'ሒ' ,  'Ha' => 'ሓ' , 'He' => 'ሔ' , 'H' => 'ሕ' ,  'Ho' => 'ሖ' ,
        'me' => 'መ' , 'mu' => 'ሙ' , 'mi' => 'ሚ' ,  'ma' => 'ማ' , 'mie' => 'ሜ' , 'm' => 'ም' ,  'mo' => 'ሞ' ,
        // 'Se' => 'ሠ' , 'Su' => 'ሡ' , 'Si' => 'ሢ' ,  'Sa' => 'ሣ' , 'Se' => 'ሤ' , 'S' => 'ሥ' ,  'So' => 'ሦ' ,
        're' => 'ረ' , 'ru' => 'ሩ' , 'ri' => 'ሪ' ,  'ra' => 'ራ' , 'rie' => 'ሬ' , 'r' => 'ር' ,  'ro' => 'ሮ' ,
        'se' => 'ሰ' , 'su' => 'ሱ' , 'si' => 'ሲ' ,  'sa' => 'ሳ' , 'sie' => 'ሴ' , 's' => 'ስ' ,  'so' => 'ሶ' ,
        'she' => 'ሸ' , 'shu' => 'ሹ' , 'shi' => 'ሺ' ,  'sha' => 'ሻ' , 'she' => 'ሼ' , 'sh' => 'ሽ' ,  'sho' => 'ሾ' ,
        'qe' => 'ቀ' , 'qu' => 'ቁ' , 'qi' => 'ቂ' ,  'qa' => 'ቃ' , 'qie' => 'ቄ' , 'q' => 'ቅ' ,  'qo' => 'ቆ' ,
        'be' => 'በ' , 'bu' => 'ቡ' , 'bi' => 'ቢ' ,  'ba' => 'ባ' , 'bie' => 'ቤ' , 'b' => 'ብ' ,  'bo' => 'ቦ' ,
        'te' => 'ተ' , 'tu' => 'ቱ' , 'ti' => 'ቲ' ,  'ta' => 'ታ' , 'tie' => 'ቴ' , 't' => 'ት' ,  'to' => 'ቶ' ,
        'che' => 'ቸ' , 'chu' => 'ቹ' , 'chi' => 'ቺ' ,  'cha' => 'ቻ' , 'chie' => 'ቼ' , 'ch' => 'ች' ,  'cho' => 'ቾ' ,
        // 'Ha' => 'ኀ' , 'Hu' => 'ኁ' , 'Hi' => 'ኂ' ,  'Ha' => 'ኃ' , 'He' => 'ኄ' , 'H' => 'ኅ' ,  'Ho' => 'ኆ' ,
        'ne' => 'ነ' , 'nu' => 'ኑ' , 'ni' => 'ኒ' ,  'na' => 'ና' , 'nie' => 'ኔ' , 'n' => 'ን' ,  'no' => 'ኖ' ,
        'gne' => 'ኘ' , 'gnu' => 'ኙ' , 'gni' => 'ኚ' ,  'gna' => 'ኛ' , 'gne' => 'ኜ' , 'gn' => 'ኝ' ,  'gno' => 'ኞ' ,
        'aa' => 'አ' , 'u' => 'ኡ' , 'i' => 'ኢ' ,  'a' => 'ኣ' , 'ie' => 'ኤ', 'ae' => 'ኤ' , 'e' => 'እ' ,  'o' => 'ኦ' ,
        'ke' => 'ከ' , 'ku' => 'ኩ' , 'ki' => 'ኪ' ,  'ka' => 'ካ' , 'kie' => 'ኬ' , 'k' => 'ክ' ,  'ko' => 'ኮ' ,
        // 'He' => 'ኸ' , 'Hu' => 'ኹ' , 'Hi' => 'ኺ' ,  'Ha' => 'ኻ' , 'He' => 'ኼ' , 'H' => 'ኽ' ,  'Ho' => 'ኾ' ,
        'we' => 'ወ' , 'wu' => 'ዉ' , 'wi' => 'ዊ' ,  'wa' => 'ዋ' , 'wie' => 'ዌ' , 'w' => 'ው' ,  'wo' => 'ዎ' ,
        // 'a' => 'ዐ' , 'u' => 'ዑ' , 'i' => 'ዒ' ,  'a' => 'ዓ' , 'e' => 'ዔ' , 'e' => 'ዕ' ,  'o' => 'ዖ' ,
        'ze' => 'ዘ' , 'zu' => 'ዙ' , 'zi' => 'ዚ' ,  'za' => 'ዛ' , 'zie' => 'ዜ' , 'z' => 'ዝ' ,  'zo' => 'ዞ' ,
        'zhe' => 'ዠ' , 'zhu' => 'ዡ' , 'zhi' => 'ዢ' ,  'zha' => 'ዣ' , 'zhie' => 'ዤ' , 'zh' => 'ዥ' ,  'zho' => 'ዦ' ,
        'ye' => 'የ' , 'yu' => 'ዩ' , 'yi' => 'ዪ' ,  'ya' => 'ያ' , 'yie' => 'ዬ' , 'y' => 'ይ' ,  'yo' => 'ዮ' ,
        'de' => 'ደ' , 'du' => 'ዱ' , 'di' => 'ዲ' ,  'da' => 'ዳ' , 'die' => 'ዴ' , 'd' => 'ድ' ,  'do' => 'ዶ' ,
        'je' => 'ጀ' , 'ju' => 'ጁ' , 'ji' => 'ጂ' ,  'ja' => 'ጃ' , 'jie' => 'ጄ' , 'j' => 'ጅ' ,  'jo' => 'ጆ' ,
        'ge' => 'ገ' , 'gu' => 'ጉ' , 'gi' => 'ጊ' ,  'ga' => 'ጋ' , 'gie' => 'ጌ' , 'g' => 'ግ' ,  'go' => 'ጎ' ,
        // 'te' => 'ጠ' , 'tu' => 'ጡ' , 'ti' => 'ጢ' ,  'ta' => 'ጣ' , 'te' => 'ጤ' , 't' => 'ጥ' ,  'to' => 'ጦ' ,
        'che' => 'ጨ' , 'chu' => 'ጩ' , 'chi' => 'ጪ' ,  'cha' => 'ጫ' , 'che' => 'ጬ' , 'ch' => 'ጭ' ,  'cho' => 'ጮ' ,
        'pe' => 'ጰ' , 'pu' => 'ጱ' , 'pi' => 'ጲ' ,  'pa' => 'ጳ' , 'pie' => 'ጴ' , 'p' => 'ጵ' ,  'po' => 'ጶ' ,
        // 'The' => 'ጸ' , 'Thu' => 'ጹ' , 'Thi' => 'ጺ' ,  'Tha' => 'ጻ' , 'The' => 'ጼ' , 'Th' => 'ጽ' ,  'Tho' => 'ጾ' ,
        'the' => 'ፀ' , 'thu' => 'ፁ' , 'thi' => 'ፂ' ,  'tha' => 'ፃ' , 'the' => 'ፄ' , 'th' => 'ፅ' ,  'tho' => 'ፆ' ,
        'fe' => 'ፈ' , 'fu' => 'ፉ' , 'fi' => 'ፊ' ,  'fa' => 'ፋ' , 'fie' => 'ፌ' , 'f' => 'ፍ' ,  'fo' => 'ፎ' ,
        'pe' => 'ፐ' , 'pu' => 'ፑ' , 'pi' => 'ፒ' ,  'pa' => 'ፓ' , 'pie' => 'ፔ' , 'p' => 'ፕ' ,  'po' => 'ፖ' ,
        've' => 'ቨ' , 'vu' => 'ቩ' , 'vi' => 'ቪ' ,  'va' => 'ቫ' , 'vie' => 'ቬ' , 'v' => 'ቭ' ,  'vo' => 'ቮ' ,
    ];
    private $skips = [
        ' ',
    ];

    // english equivalences for amharic symbols
    private $replaces = [
        // 'ጠ' => 'M',
    ];

    private $englishSkips = [
        ' ',
    ];

    private $englishReplaces = [];

    private $defaultEquivalence = self::RETURN_SELF;

    public function translate($word = '') {
        return $this->toEnglish($word);
    }

    private function getEnglishEquivalence($character = '') {

        if (isset($this->replaces[$character])) {
            return $this->replaces[$character];
        }

        if (in_array($character, $this->skips)) {
            return $character;
        }

        if (isset($this->amharicLetters[$character])) {
            return strtolower($this->amharicLetters[$character]);
        }

        return $this->defaultEquivalence == self::RETURN_EMPTY ? '' : $character;
    }

    public function toEnglish($word = '') {
        $translated = '';

        $eachCharacter = mb_str_split($word, 1, 'UTF-8');
        $length = count($eachCharacter);

        for ($i = 0; $i < $length; $i++) {
            $equivalence = $this->getEnglishEquivalence($eachCharacter[$i]);

            // check if sadis -> only one letter and not at the end of the word
            if (strlen($equivalence) == 1 && $i == 0 && !in_array($equivalence, ['a', 'i'])) {
                $equivalence .= 'i';
            }

            $translated .= $equivalence;
        }

        return $translated;
    }

    public function toAmharic($word = '') {
        $translated = '';

        $eachCharacter = $this->splitEnglish($word);
        foreach ($eachCharacter as $character) {
            $translated .= $this->getAmharicEquivalence($character);
        }

        return $translated;
    }


    private function getAmharicEquivalence($character = '') {

        $character = strtolower($character);
        if (isset($this->englishReplaces[$character])) {
            return $this->englishReplaces[$character];
        }

        if (in_array($character, $this->englishSkips)) {
            return $character;
        }

        if (isset($this->englishLetters[$character])) {
            return strtolower($this->englishLetters[$character]);
        }

        return $this->defaultEquivalence == self::RETURN_EMPTY ? '' : $character;
    }

    private function isVowel($character) {

        switch (strtolower($character)) {
            case 'a':
            case 'i':
            case 'o':
            case 'u':
            case 'e':
                return true;
                break;
            default:
                return false;
                break;
        }
    }

    // @return array
    private function splitEnglish($word) {

        $splitted = [];
        $temp = mb_str_split($word, 1, 'UTF-8');
        $length = count($temp);
        for ($i = 0; $i < $length; $i++) {
            $char = $temp[$i];

            if ($i === 0 && strtolower($char) === 'a') {
                $splitted[] = 'aa';
                continue;
            }

            if ($i > 0 && strtolower($temp[$i - 1]) === 'i' && ! $this->isVowel($char)) {
                $splitted[count($splitted) - 1] = rtrim($splitted[count($splitted) - 1], 'i');
            }

            if ($this->isVowel($char)) {
                if (strtolower($char) === 'e' && count($splitted) > 0) {
                    $lastIndex = count($splitted) - 1;
                    if (str_ends_with(strtolower($splitted[$lastIndex]), 'a')) {
                        $splitted[$lastIndex] = strtolower($splitted[$lastIndex]);
                        $splitted[$lastIndex + 1] = 'ae';
                        continue;
                    }

                    if (str_ends_with(strtolower($splitted[$lastIndex]), 'i')) {
                        $splitted[$lastIndex] = strtolower($splitted[$lastIndex]);
                        $splitted[$lastIndex + 1] = 'ie';
                        continue;
                    }
                }

                $splitted[] = $char;
                continue;
            }

            $nextIndex = $i + 1;
            if ($nextIndex < $length && $this->isVowel($temp[$nextIndex])) {
                $splitted[] = $char . $temp[$nextIndex];
                $i++;
                continue;
            }

            $splitted[] = $char;
        }
        return $splitted;
    }
}