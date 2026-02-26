<?php

namespace App\Service\AI;

use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class FileTextExtractor
{
    private PdfParser $pdfParser;

    public function __construct()
    {
        $this->pdfParser = new PdfParser();
    }

    /**
     * Extract text from a file based on its extension
     * 
     * @param string $filePath Full path to the file
     * @param string|null $originalFilename Original filename to determine extension (use for temp files)
     * @return string Extracted text content
     * @throws \Exception If file type is not supported or extraction fails
     */
    public function extractText(string $filePath, ?string $originalFilename = null): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        // Use original filename if provided, otherwise use file path
        $filenameForExtension = $originalFilename ?? $filePath;
        $extension = strtolower(pathinfo($filenameForExtension, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => $this->extractFromPdf($filePath),
            'docx' => $this->extractFromDocx($filePath),
            'doc' => $this->extractFromDoc($filePath),
            'txt' => $this->extractFromTxt($filePath),
            default => throw new \Exception("Unsupported file type: {$extension}"),
        };
    }

    /**
     * Extract text from PDF file
     */
    private function extractFromPdf(string $filePath): string
    {
        try {
            $pdf = $this->pdfParser->parseFile($filePath);
            $text = $pdf->getText();
            
            // Clean up extracted text
            return $this->cleanText($text);
        } catch (\Exception $e) {
            throw new \Exception("Failed to parse PDF: " . $e->getMessage());
        }
    }

    /**
     * Extract text from DOCX file
     */
    private function extractFromDocx(string $filePath): string
    {
        try {
            $phpWord = WordIOFactory::load($filePath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= $this->extractTextFromElement($element) . "\n";
                }
            }

            return $this->cleanText($text);
        } catch (\Exception $e) {
            throw new \Exception("Failed to parse DOCX: " . $e->getMessage());
        }
    }

    /**
     * Extract text from DOC file (limited support)
     */
    private function extractFromDoc(string $filePath): string
    {
        // DOC files are more complex, try basic extraction
        $content = file_get_contents($filePath);
        
        // Remove binary data and extract readable text
        $text = '';
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            // Filter out binary content
            if (preg_match('/[\x20-\x7E]{10,}/', $line, $matches)) {
                $text .= $matches[0] . "\n";
            }
        }

        if (empty(trim($text))) {
            throw new \Exception("Could not extract text from DOC file. Please convert to DOCX format.");
        }

        return $this->cleanText($text);
    }

    /**
     * Extract text from TXT file
     */
    private function extractFromTxt(string $filePath): string
    {
        $text = file_get_contents($filePath);
        
        if ($text === false) {
            throw new \Exception("Failed to read TXT file");
        }

        return $this->cleanText($text);
    }

    /**
     * Extract text from PhpWord elements recursively
     */
    private function extractTextFromElement($element): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $elementText = $element->getText();
            if (is_string($elementText)) {
                $text .= $elementText;
            } elseif (is_object($elementText) && method_exists($elementText, 'getText')) {
                $text .= $elementText->getText();
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $childElement) {
                $text .= $this->extractTextFromElement($childElement);
            }
        }

        return $text;
    }

    /**
     * Clean up extracted text
     */
    private function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove non-printable characters (except newlines)
        $text = preg_replace('/[^\x20-\x7E\n\r]/', '', $text);
        
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Remove excessive newlines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        return trim($text);
    }

    /**
     * Get supported file extensions
     */
    public function getSupportedExtensions(): array
    {
        return ['pdf', 'docx', 'doc', 'txt'];
    }

    /**
     * Check if file type is supported
     */
    public function isSupported(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->getSupportedExtensions());
    }
}
