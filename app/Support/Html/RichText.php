<?php

namespace App\Support\Html;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Server-side sanitiser for editor-produced rich text.
 *
 * The browser editor is a convenience, never a security boundary: every stored
 * HTML fragment is filtered against this allowlist before it is persisted, so a
 * compromised or scripted client cannot inject scripts, event handlers, iframes
 * or `javascript:` URLs into a product page.
 */
final class RichText
{
    private static ?HTMLPurifier $purifier = null;

    /** Elements and attributes the editor is allowed to produce. */
    private const ALLOWED = [
        'p[style]',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'h2[style]',
        'h3[style]',
        'h4[style]',
        'ul',
        'ol',
        'li',
        'blockquote',
        'pre',
        'code',
        'a[href|title|target|rel]',
        'img[src|alt|title|width|height]',
        'span[style]',
        'mark[style]',
        'div[style]',
        'hr',
    ];

    /**
     * Formatting the editor exposes. Anything else (positioning, javascript
     * URLs, expressions) is dropped by the CSS filter.
     */
    private const CSS_PROPERTIES = 'color,background-color,text-align,font-weight,font-style,text-decoration';

    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);

        if ($trimmed === '') {
            return null;
        }

        $purified = self::purifier()->purify($trimmed);

        return trim($purified) === '' ? null : $purified;
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier instanceof HTMLPurifier) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.DefinitionID', 'htashop-rich-text');
        $config->set('HTML.DefinitionRev', 1);
        $config->set('HTML.Allowed', implode(',', self::ALLOWED));
        $config->set('CSS.AllowedProperties', self::CSS_PROPERTIES);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', true);
        $config->set('Attr.EnableID', false);
        $config->set('AutoFormat.RemoveEmpty', true);
        // Rich text is written rarely; skip the filesystem definition cache so
        // the sanitiser works on a read-only release image.
        $config->set('Cache.DefinitionImpl', null);

        // `<mark>` is HTML5 and unknown to the HTML 4.01 definition set; the
        // editor uses it for highlights, so register it with the Common
        // attribute collection (which keeps `style` behind the CSS filter).
        $definition = $config->maybeGetRawHTMLDefinition();

        if ($definition !== null) {
            $definition->addElement('mark', 'Inline', 'Inline', 'Common');
        }

        return self::$purifier = new HTMLPurifier($config);
    }
}
