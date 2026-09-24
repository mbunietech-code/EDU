<?php

namespace App\Support;

/**
 * Tiny Swahili-vs-English detector for replying in the language someone wrote
 * in. It only counts common function words, and deliberately ignores words
 * both languages borrow here ("order", "payment", "users"…), so a mixed
 * sentence like "ni orders ngapi leo" still reads as Swahili. Free, offline.
 */
class Language
{
    public const SWAHILI = 'sw';
    public const ENGLISH = 'en';

    private const SWAHILI_WORDS = [
        'na', 'ya', 'wa', 'ni', 'kwa', 'la', 'za', 'hii', 'huu', 'hizi', 'hiyo', 'hilo', 'kuna', 'ngapi', 'gani',
        'yako', 'yangu', 'wangu', 'mimi', 'sisi', 'wewe', 'habari', 'asante', 'tafadhali', 'naomba', 'nataka',
        'sijui', 'mbona', 'bado', 'sasa', 'leo', 'jana', 'kesho', 'mwezi', 'wiki', 'siku', 'malipo', 'agizo',
        'mtumiaji', 'watumiaji', 'akaunti', 'hitilafu', 'ujumbe', 'afya', 'ukubwa', 'imeisha', 'zimeisha',
        'yamekubaliwa', 'muda', 'tuma', 'onyesha', 'nionyeshe', 'unaweza', 'kwamba', 'pia', 'sana', 'vipi',
        'hali', 'lini', 'wapi', 'nani', 'ndio', 'ndiyo', 'hapana', 'kuhusu', 'kila', 'zote', 'wote', 'yote',
        'ambazo', 'ambao', 'wakati', 'kama', 'au', 'lakini', 'huduma', 'mteja', 'wateja', 'mfumo', 'msaada',
        'pesa', 'bei', 'samahani', 'pole', 'karibu', 'mambo', 'jibu', 'swali', 'ombi', 'nimeagiza', 'nimelipa',
        'mkuu', 'shikamoo', 'nipe', 'niambie', 'kwanini', 'nini', 'kiasi', 'jumla',
    ];

    private const ENGLISH_WORDS = [
        'the', 'is', 'are', 'was', 'were', 'what', 'which', 'who', 'how', 'many', 'much', 'do', 'does', 'did',
        'show', 'me', 'my', 'please', 'i', 'you', 'we', 'this', 'that', 'these', 'those', 'have', 'has', 'had',
        'of', 'in', 'for', 'to', 'and', 'or', 'but', 'with', 'today', 'yesterday', 'tomorrow', 'month', 'week',
        'day', 'can', 'could', 'would', 'should', 'will', 'there', 'any', 'all', 'about', 'from', 'on', 'at',
        'it', 'its', 'not', 'no', 'yes', 'hello', 'hi', 'thanks', 'thank', 'need', 'want', 'when', 'where',
        'why', 'give', 'tell', 'get', 'list', 'total', 'help', 'still', 'waiting', 'reply', 'answer', 'your',
        'our', 'been', 'be', 'am', 'im', 'ive', 'hey', 'morning', 'afternoon', 'evening',
    ];

    /**
     * @return 'sw'|'en'|null null when there isn't enough to tell
     */
    public static function detect(string $text): ?string
    {
        preg_match_all("/[\\p{L}']+/u", mb_strtolower($text), $matches);

        $sw = 0;
        $en = 0;
        foreach ($matches[0] as $word) {
            $word = str_replace("'", '', $word);
            if (in_array($word, self::SWAHILI_WORDS, true)) {
                $sw++;
            }
            if (in_array($word, self::ENGLISH_WORDS, true)) {
                $en++;
            }
        }

        return match (true) {
            $sw > $en => self::SWAHILI,
            $en > $sw => self::ENGLISH,
            default => null,
        };
    }
}
