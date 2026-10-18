<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

// Cleans user HTML (TinyMCE content, comments, slogans) before printing it unescaped:
// keeps formatting, links and tables, removes scripts, event handlers and javascript: links
class SafeHtml
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowSafeElements()
                ->allowAttribute('style', '*')
                ->allowAttribute('class', '*')
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowRelativeLinks()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(-1)
        );

        return self::$sanitizer->sanitize($html);
    }
}
