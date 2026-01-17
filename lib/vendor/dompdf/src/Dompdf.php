<?php
declare(strict_types=1);

namespace Dompdf;

final class Dompdf
{
    private string $html = '';
    private string $paper = 'A4';
    private string $orientation = 'portrait';
    private Options $options;
    private string $output = '';

    public function __construct(?Options $options = null)
    {
        $this->options = $options ?? new Options();
    }

    public function setPaper(string $paper, string $orientation = 'portrait'): void
    {
        $this->paper = $paper;
        $this->orientation = $orientation;
    }

    public function loadHtml(string $html): void
    {
        $this->html = $html;
    }

    public function render(): void
    {
        $text = $this->htmlToText($this->html);
        $lines = $this->wrapText($text, 90);
        $pageSize = $this->pageSizePoints($this->paper, $this->orientation);
        $margin = 48;
        $lineHeight = 14;
        $linesPerPage = max(1, (int)floor(($pageSize['height'] - ($margin * 2) - 20) / $lineHeight));
        $pages = array_chunk($lines, $linesPerPage);
        if (!$pages) {
            $pages = [[]];
        }
        $totalPages = count($pages);
        $this->output = $this->buildPdf($pages, $pageSize, $margin, $lineHeight, $totalPages);
    }

    public function output(): string
    {
        return $this->output;
    }

    private function htmlToText(string $html): string
    {
        $withoutScripts = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $withoutStyles = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $withoutScripts) ?? $withoutScripts;
        $blockTags = [
            '</p>' => "\n\n",
            '</div>' => "\n",
            '</section>' => "\n\n",
            '</article>' => "\n\n",
            '</header>' => "\n\n",
            '</footer>' => "\n\n",
            '</tr>' => "\n",
            '</li>' => "\n",
            '<br>' => "\n",
            '<br/>' => "\n",
            '<br />' => "\n",
            '</h1>' => "\n\n",
            '</h2>' => "\n\n",
            '</h3>' => "\n\n",
            '</h4>' => "\n\n",
        ];
        $normalized = str_ireplace(array_keys($blockTags), array_values($blockTags), $withoutStyles);
        $stripped = strip_tags($normalized);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/[ \t]+/', ' ', $decoded) ?? $decoded;
        $decoded = preg_replace('/\n{3,}/', "\n\n", $decoded) ?? $decoded;
        return trim($decoded);
    }

    private function wrapText(string $text, int $width): array
    {
        $lines = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                $lines[] = '';
                continue;
            }
            $words = preg_split('/\s+/', $line) ?: [];
            $current = '';
            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if (mb_strlen($candidate) > $width) {
                    if ($current !== '') {
                        $lines[] = $current;
                    }
                    $current = $word;
                } else {
                    $current = $candidate;
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }
        return $lines;
    }

    private function pageSizePoints(string $paper, string $orientation): array
    {
        $sizes = [
            'A4' => ['width' => 595.28, 'height' => 841.89],
            'Letter' => ['width' => 612, 'height' => 792],
            'Legal' => ['width' => 612, 'height' => 1008],
        ];
        $size = $sizes[$paper] ?? $sizes['A4'];
        if ($orientation === 'landscape') {
            return ['width' => $size['height'], 'height' => $size['width']];
        }
        return $size;
    }

    private function buildPdf(array $pages, array $pageSize, int $margin, int $lineHeight, int $totalPages): string
    {
        $objects = [];
        $offsets = [];
        $addObject = static function (string $content) use (&$objects): int {
            $objects[] = $content;
            return count($objects);
        };

        $fontObjectId = $addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
        $pagesObjectId = $addObject('');
        $pageIds = [];
        foreach ($pages as $index => $lines) {
            $stream = $this->buildPageStream($lines, $pageSize, $margin, $lineHeight, $index + 1, $totalPages);
            $contentId = $addObject("<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream");
            $pageId = $addObject("<< /Type /Page /Parent {$pagesObjectId} 0 R /MediaBox [0 0 {$pageSize['width']} {$pageSize['height']}] /Contents {$contentId} 0 R /Resources << /Font << /F1 {$fontObjectId} 0 R >> >> >>");
            $pageIds[] = $pageId;
        }
        $kids = implode(' ', array_map(static fn(int $id): string => "{$id} 0 R", $pageIds));
        $objects[$pagesObjectId - 1] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageIds) . " >>";
        $catalogId = $addObject("<< /Type /Catalog /Pages {$pagesObjectId} 0 R >>");

        $pdf = "%PDF-1.4\n";
        foreach ($objects as $id => $object) {
            $offsets[$id + 1] = strlen($pdf);
            $pdf .= ($id + 1) . " 0 obj\n{$object}\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogId} 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";
        return $pdf;
    }

    private function buildPageStream(array $lines, array $pageSize, int $margin, int $lineHeight, int $pageNumber, int $totalPages): string
    {
        $y = $pageSize['height'] - $margin;
        $stream = "BT\n/F1 11 Tf\n";
        foreach ($lines as $line) {
            $escaped = $this->escapePdfText($line);
            $stream .= sprintf("1 0 0 1 %d %d Tm (%s) Tj\n", $margin, (int)$y, $escaped);
            $y -= $lineHeight;
        }
        $footer = 'Page ' . $pageNumber . ' of ' . $totalPages;
        $stream .= sprintf("1 0 0 1 %d %d Tm (%s) Tj\n", $margin, (int)($margin - 10), $this->escapePdfText($footer));
        $stream .= "ET";
        return $stream;
    }

    private function escapePdfText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        return str_replace(')', '\\)', $text);
    }
}
