<?php

namespace App\Service\JobOffer;

use Smalot\PdfParser\Parser;
use Psr\Log\LoggerInterface;

/**
 * Service to extract text content from PDF CV files.
 */
class CVParserService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Extract text from a PDF file.
     *
     * @param string $filePath Absolute path to the PDF file
     * @return string|null Extracted text or null on failure
     */
    public function extractTextFromPdf(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            $this->logger->error('CV file not found', ['path' => $filePath]);
            return null;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if ($extension !== 'pdf') {
            $this->logger->warning('Non-PDF file provided, skipping extraction', [
                'path' => $filePath,
                'extension' => $extension,
            ]);
            return null;
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            
            // Clean up the text
            $text = $this->cleanText($text);
            
            $this->logger->info('Successfully extracted text from CV', [
                'path' => $filePath,
                'textLength' => strlen($text),
            ]);
            
            return $text;
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract text from PDF', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Clean up extracted text.
     */
    private function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove special characters that might cause issues
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Trim
        $text = trim($text);
        
        // Limit length to avoid sending too much to AI
        if (strlen($text) > 15000) {
            $text = substr($text, 0, 15000);
        }
        
        return $text;
    }

}
