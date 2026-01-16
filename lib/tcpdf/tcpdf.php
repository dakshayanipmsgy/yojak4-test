<?php
declare(strict_types=1);

class TCPDF
{
    private array $pages = [];
    private int $currentPage = 0;
    private float $pageWidth = 595.28;
    private float $pageHeight = 841.89;
    private float $marginTop = 54.0;
    private float $marginBottom = 54.0;
    private float $marginLeft = 40.0;
    private float $marginRight = 40.0;
    private float $lineHeight = 14.0;
    private float $currentY = 0.0;
    private string $headerText = '';
    private string $footerText = '';

    public function __construct(string $orientation = 'P', string $unit = 'mm', string $format = 'A4', bool $unicode = true, string $encoding = 'UTF-8')
    {
        $this->setPageFormat($format, $orientation);
    }

    public function SetCreator(string $creator): void
    {
    }

    public function SetAuthor(string $author): void
    {
    }

    public function SetTitle(string $title): void
    {
    }

    public function SetSubject(string $subject): void
    {
    }

    public function SetMargins(float $left, float $top, float $right): void
    {
        $this->marginLeft = $left;
        $this->marginTop = $top;
        $this->marginRight = $right;
    }

    public function SetAutoPageBreak(bool $auto, float $margin = 0.0): void
    {
        $this->marginBottom = $margin > 0 ? $margin : $this->marginBottom;
    }

    public function setPrintHeader(bool $print): void
    {
    }

    public function setPrintFooter(bool $print): void
    {
    }

    public function setHeaderText(string $text): void
    {
        $this->headerText = $text;
    }

    public function setFooterText(string $text): void
    {
        $this->footerText = $text;
    }

    public function AddPage(): void
    {
        $this->currentPage++;
        $this->pages[$this->currentPage] = [];
        $this->currentY = $this->marginTop;
        if ($this->headerText !== '') {
            $this->addLine($this->headerText, $this->marginTop - $this->lineHeight);
        }
    }

    public function getPage(): int
    {
        return $this->currentPage;
    }

    public function setPage(int $pageNumber): void
    {
        if (!isset($this->pages[$pageNumber])) {
            return;
        }
        $this->currentPage = $pageNumber;
        $this->currentY = $this->marginTop;
    }

    public function clearPage(int $pageNumber): void
    {
        if (!isset($this->pages[$pageNumber])) {
            return;
        }
        $this->pages[$pageNumber] = [];
        $this->currentPage = $pageNumber;
        $this->currentY = $this->marginTop;
        if ($this->headerText !== '') {
            $this->addLine($this->headerText, $this->marginTop - $this->lineHeight);
        }
    }

    public function SetFont(string $family, string $style = '', float $size = 10.0): void
    {
        $this->lineHeight = max(10.0, $size + 4.0);
    }

    public function writeHTML(string $html, bool $ln = true, bool $fill = false, bool $reseth = true, bool $cell = false, string $align = ''): void
    {
        $text = $this->htmlToText($html);
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        foreach ($lines as $line) {
            $this->writeLine(trim($line));
        }
    }

    public function Output(string $name = 'doc.pdf', string $dest = 'I'): void
    {
        $pdf = $this->buildPdf();
        if ($dest === 'S') {
            echo $pdf;
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $name . '"');
        echo $pdf;
    }

    private function setPageFormat(string $format, string $orientation): void
    {
        $formats = [
            'A4' => [595.28, 841.89],
            'Letter' => [612.0, 792.0],
            'Legal' => [612.0, 1008.0],
        ];
        [$width, $height] = $formats[$format] ?? $formats['A4'];
        if (strtoupper($orientation) === 'L') {
            $this->pageWidth = $height;
            $this->pageHeight = $width;
        } else {
            $this->pageWidth = $width;
            $this->pageHeight = $height;
        }
    }

    private function writeLine(string $line): void
    {
        if ($this->currentPage === 0) {
            $this->AddPage();
        }
        if ($line === '') {
            $this->currentY += $this->lineHeight;
            return;
        }
        $maxY = $this->pageHeight - $this->marginBottom - ($this->lineHeight * 2);
        if ($this->currentY > $maxY) {
            $this->AddPage();
        }
        $this->addLine($line, $this->currentY);
        $this->currentY += $this->lineHeight;
    }

    private function addLine(string $line, float $y): void
    {
        $this->pages[$this->currentPage][] = [
            'text' => $line,
            'x' => $this->marginLeft,
            'y' => $y,
        ];
    }

    private function htmlToText(string $html): string
    {
        $replacements = [
            '<br>' => "\n",
            '<br/>' => "\n",
            '<br />' => "\n",
            '</p>' => "\n\n",
            '</div>' => "\n",
            '</h1>' => "\n\n",
            '</h2>' => "\n\n",
            '</h3>' => "\n\n",
            '</h4>' => "\n\n",
            '</li>' => "\n",
            '</tr>' => "\n",
            '</td>' => ' | ',
            '</th>' => ' | ',
            '<li>' => '- ',
        ];
        $html = str_ireplace(array_keys($replacements), array_values($replacements), $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return $text;
    }

    private function buildPdf(): string
    {
        $objects = [];
        $offsets = [];
        $pages = $this->pages ?: [1 => []];
        $pageCount = count($pages);

        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [" . $this->buildPageRefs($pageCount) . "] /Count {$pageCount} >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $pageObjects = [];
        foreach ($pages as $pageNumber => $lines) {
            $content = $this->buildPageContent($pageNumber, $pageCount, $lines);
            $contentObj = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $objects[] = $contentObj;
            $contentRef = count($objects) . " 0 R";
            $pageObj = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentRef} >>";
            $objects[] = $pageObj;
            $pageObjects[] = count($objects) . " 0 R";
        }

        $objects[1] = "<< /Type /Pages /Kids [" . implode(' ', $pageObjects) . "] /Count {$pageCount} >>";

        $pdf = "%PDF-1.4\n";
        foreach ($objects as $index => $object) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xrefPosition = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string)$offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPosition . "\n%%EOF";
        return $pdf;
    }

    private function buildPageRefs(int $count): string
    {
        $refs = [];
        $start = 5;
        for ($i = 0; $i < $count; $i++) {
            $refs[] = ($start + ($i * 2)) . " 0 R";
        }
        return implode(' ', $refs);
    }

    private function buildPageContent(int $pageNumber, int $pageCount, array $lines): string
    {
        $content = "BT\n/F1 10 Tf\n";
        foreach ($lines as $line) {
            $text = $this->escapeText($line['text']);
            $x = $line['x'];
            $y = $this->pageHeight - $line['y'];
            $content .= sprintf("1 0 0 1 %.2f %.2f Tm (%s) Tj\n", $x, $y, $text);
        }
        if ($this->footerText !== '') {
            $footer = str_replace(['{page}', '{total}'], [(string)$pageNumber, (string)$pageCount], $this->footerText);
            $footer = $this->escapeText($footer);
            $x = $this->marginLeft;
            $y = $this->marginBottom;
            $content .= sprintf("1 0 0 1 %.2f %.2f Tm (%s) Tj\n", $x, $y, $footer);
        }
        $content .= "ET";
        return $content;
    }

    private function escapeText(string $text): string
    {
        $clean = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        if ($clean === false) {
            $clean = $text;
        }
        $clean = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);
        return $clean;
    }
}
