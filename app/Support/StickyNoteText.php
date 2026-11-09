<?php

namespace App\Support;

/** Turns a note's plain text into safe HTML: everything escaped, web links clickable. */
final class StickyNoteText
{
    public static function render(string $text): string
    {
        $safe = e($text);

        return (string) preg_replace_callback(
            // The text is already escaped, so stop before an escaped quote or bracket.
            '~\bhttps?://(?:(?!&quot;|&#039;|&lt;|&gt;)[^\s<>"\'])+~i',
            function (array $m) {
                $url = rtrim($m[0], '.,);:!?');
                $tail = substr($m[0], strlen($url));

                return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer nofollow">'.$url.'</a>'.$tail;
            },
            $safe
        );
    }
}
