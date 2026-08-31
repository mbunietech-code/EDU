<?php

namespace App\Services;

use App\Models\Research;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Turns an uploaded document (or pasted text) into chapters + sections.
 *
 *   # Heading 1  ......  a new chapter
 *   ## / ### ...  ......  a new section within the current chapter
 *   everything else  ...  body text of the current section (Markdown)
 *
 * .docx files are first flattened to that same Markdown shape.
 */
class ResearchImporter
{
    private const MAX_BODY = 200000;

    /**
     * @return array{chapters:int, sections:int}
     */
    public function import(Research $research, ?UploadedFile $file, ?string $pastedText): array
    {
        $markdown = $file
            ? $this->fileToMarkdown($file)
            : (string) $pastedText;

        $blocks = $this->parse($markdown);

        if (empty($blocks)) {
            return ['chapters' => 0, 'sections' => 0];
        }

        $chapterPos = (int) $research->chapters()->max('position');
        $chaptersAdded = 0;
        $sectionsAdded = 0;

        foreach ($blocks as $block) {
            $chapter = $research->chapters()->create([
                'title' => Str::limit($block['title'], 250, ''),
                'position' => ++$chapterPos,
            ]);
            $chaptersAdded++;

            $sectionPos = 0;
            foreach ($block['sections'] as $section) {
                $chapter->sections()->create([
                    'heading' => Str::limit($section['heading'], 250, ''),
                    'body' => Str::limit(trim($section['body']), self::MAX_BODY, ''),
                    'position' => ++$sectionPos,
                ]);
                $sectionsAdded++;
            }
        }

        return ['chapters' => $chaptersAdded, 'sections' => $sectionsAdded];
    }

    // ----------------------------------------------------------------- parsing
    /**
     * @return list<array{title:string, sections:list<array{heading:string, body:string}>}>
     */
    private function parse(string $markdown): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $markdown) ?: [];

        $chapters = [];        // list of ['title' => .., 'sections' => list of ['heading','body']]
        $ci = -1;              // current chapter index
        $si = -1;              // current section index

        $newChapter = function (string $title) use (&$chapters, &$ci, &$si) {
            $chapters[] = ['title' => $title, 'sections' => []];
            $ci = count($chapters) - 1;
            $si = -1;
        };
        $newSection = function (string $heading) use (&$chapters, &$ci, &$si, $newChapter) {
            if ($ci < 0) {
                $newChapter('Chapter 1');
            }
            $chapters[$ci]['sections'][] = ['heading' => $heading, 'body' => ''];
            $si = count($chapters[$ci]['sections']) - 1;
        };

        foreach ($lines as $raw) {
            $line = rtrim((string) $raw);

            if (preg_match('/^#\s+(.+)$/', $line, $m)) {
                $newChapter(trim($m[1]) ?: 'Untitled chapter');
                continue;
            }

            if (preg_match('/^#{2,6}\s+(.+)$/', $line, $m)) {
                $newSection(trim($m[1]) ?: 'Untitled section');
                continue;
            }

            // Body line
            if ($ci < 0) {
                $newChapter('Chapter 1');
            }
            if ($si < 0) {
                $newSection('Introduction');
            }
            $chapters[$ci]['sections'][$si]['body'] .= $line."\n";
        }

        // Tidy: drop placeholder-only sections; ensure each chapter has >=1 section.
        foreach ($chapters as $i => $ch) {
            $kept = [];
            foreach ($ch['sections'] as $s) {
                $hasBody = trim((string) $s['body']) !== '';
                $isPlaceholder = in_array($s['heading'], ['Introduction', 'Overview'], true);
                if ($hasBody || ! $isPlaceholder) {
                    $kept[] = $s;
                }
            }
            if (empty($kept)) {
                $kept[] = ['heading' => 'Overview', 'body' => ''];
            }
            $chapters[$i]['sections'] = $kept;
        }

        return array_values(array_filter($chapters, fn ($c) => ! empty($c['title'])));
    }

    // ---------------------------------------------------------- file → markdown
    private function fileToMarkdown(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['md', 'markdown', 'txt', 'text'], true)) {
            return (string) file_get_contents($file->getRealPath());
        }

        if ($ext === 'docx') {
            return $this->docxToMarkdown($file->getRealPath());
        }

        throw new \RuntimeException('Unsupported file type. Upload a .docx, .md or .txt file, or paste the text.');
    }

    /**
     * Read word/document.xml straight from the .docx zip. A paragraph whose
     * pStyle is "Heading 1" (or "Title") becomes `#`, "Heading 2/3…" become
     * `##`/`###`, list items become `- `, bold/italic runs are marked up.
     */
    private function docxToMarkdown(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open the Word file.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('The Word file looks empty or corrupt.');
        }

        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            throw new \RuntimeException('Could not read the Word file.');
        }

        $doc->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $lines = [];
        foreach ($doc->xpath('//w:body/w:p') ?: [] as $p) {
            $lines[] = $this->paragraphToMarkdown($p);
        }

        return implode("\n", $lines);
    }

    private function paragraphToMarkdown(\SimpleXMLElement $p): string
    {
        $p->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Gather run text with basic bold/italic.
        $text = '';
        foreach ($p->xpath('.//w:r') ?: [] as $r) {
            $r->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $chunk = '';
            foreach ($r->xpath('.//w:t') ?: [] as $t) {
                $chunk .= (string) $t;
            }
            foreach ($r->xpath('.//w:tab') ?: [] as $ignored) {
                $chunk .= ' ';
            }
            if ($chunk === '') {
                continue;
            }
            $bold = (bool) ($r->xpath('.//w:rPr/w:b') ?: []);
            $italic = (bool) ($r->xpath('.//w:rPr/w:i') ?: []);
            if ($bold) {
                $chunk = '**'.$chunk.'**';
            } elseif ($italic) {
                $chunk = '*'.$chunk.'*';
            }
            $text .= $chunk;
        }
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // Style name → heading level.
        $styleNodes = $p->xpath('./w:pPr/w:pStyle/@w:val') ?: [];
        $style = $styleNodes ? strtolower((string) $styleNodes[0]) : '';

        if ($style === 'title' || preg_match('/heading\s*1|^h1$/', $style)) {
            return '# '.strip_tags($text);
        }
        if (preg_match('/heading\s*([2-6])|^h([2-6])$/', $style, $m)) {
            $lvl = (int) ($m[1] ?: $m[2]);

            return str_repeat('#', min(3, $lvl)).' '.strip_tags($text);
        }

        // List item?
        if ($p->xpath('./w:pPr/w:numPr')) {
            return '- '.$text;
        }

        return $text;
    }
}
