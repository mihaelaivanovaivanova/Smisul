<?php

namespace App\Support;

/**
 * Generates safe, dependency-free placeholder media content for the
 * development seed data — no binary assets are committed to the repo, no
 * image/PDF libraries are required, and no copyrighted material is ever
 * touched. The seeder calls these at seed time and writes the result to
 * the public disk, so `php artisan migrate:fresh --seed` reproduces the
 * whole media set from scratch every time.
 */
class PlaceholderMedia
{
    /**
     * A simple branded placeholder "photo": a soft card in the storefront's
     * palette with a leaf glyph and a text label. Pure SVG — no rendering
     * tooling needed, and trivially easy to swap for a real photo later
     * (same path, same dimensions).
     */
    public static function productImageSvg(string $label, string $background = '#F1E8D8', string $accent = '#33402E'): string
    {
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_XML1);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
          <rect width="800" height="800" fill="{$background}" />
          <circle cx="400" cy="330" r="120" fill="none" stroke="{$accent}" stroke-width="4" opacity="0.3" />
          <path d="M400 260c-66 0-115 46-115 115s49 115 115 115a132 132 0 0 0 26-2.6c-40-14-68-58-68-112.4C358 318 386 274 426 260a132 132 0 0 0-26-2.6Z" fill="{$accent}" opacity="0.55" />
          <text x="400" y="520" font-family="Georgia, serif" font-size="34" fill="{$accent}" text-anchor="middle">{$safeLabel}</text>
          <text x="400" y="558" font-family="Arial, sans-serif" font-size="15" letter-spacing="3" fill="{$accent}" text-anchor="middle" opacity="0.6">SMISUL PLACEHOLDER</text>
        </svg>
        SVG;
    }

    /**
     * A minimal, spec-valid single-page PDF built by hand (no PDF library
     * available in this project) — a title plus a handful of body lines.
     * Text is kept to Latin characters deliberately: the PDF base-14
     * fonts (Helvetica here) don't cover Cyrillic without embedding a
     * real font, which is out of scope for a placeholder. Replace with a
     * properly authored PDF once real product documentation exists.
     */
    public static function minimalPdf(string $title, array $lines): string
    {
        $contentStream = self::buildContentStream($title, $lines);
        $contentLength = strlen($contentStream);

        $objects = [
            1 => "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            2 => "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            3 => "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
                ."/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            4 => "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            5 => "5 0 obj\n<< /Length {$contentLength} >>\nstream\n{$contentStream}\nendstream\nendobj\n",
        ];

        $body = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $objectString) {
            $offsets[$number] = strlen($body);
            $body .= $objectString;
        }

        $xrefOffset = strlen($body);
        $xref = 'xref'."\n".'0 '.(count($objects) + 1)."\n";
        $xref .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $xref .= sprintf("%010d 00000 n \n", $offset);
        }

        $trailer = 'trailer'."\n".'<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\n"
            ."startxref\n{$xrefOffset}\n%%EOF";

        return $body.$xref.$trailer;
    }

    /**
     * Plain-text stand-in for a product demo video. Not a playable video
     * file — generating real video without any encoding tooling available
     * isn't feasible, and faking the binary would be worse than being
     * explicit about the placeholder. It still exercises the "product has
     * a video" UI path (the <video> element renders; the file just won't
     * decode) until a real clip is dropped in at the same path.
     */
    public static function videoPlaceholderText(string $productName): string
    {
        return <<<TXT
        This is a placeholder for a product demo video for "{$productName}".

        It is intentionally not a real, playable video file — no video
        encoding tooling was available when this development dataset was
        generated. It exists purely so the storefront's "product has a
        video" UI path (see ProductVideos.tsx) can be exercised during
        development.

        Replace the file at this path with a real video (same filename,
        or update the Media row's `path`) once one exists.
        TXT;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function buildContentStream(string $title, array $lines): string
    {
        $parts = ['BT', '/F1 18 Tf', '72 740 Td', '('.self::escapePdfText($title).') Tj', '/F1 11 Tf'];

        foreach ($lines as $index => $line) {
            $dy = $index === 0 ? -30 : -18;
            $parts[] = "0 {$dy} Td";
            $parts[] = '('.self::escapePdfText($line).') Tj';
        }

        $parts[] = 'ET';

        return implode("\n", $parts);
    }

    private static function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
