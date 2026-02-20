<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class AvatarGeneratorClient
{
    private string $serviceUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $avatarServiceUrl
    ) {
        $this->serviceUrl = rtrim($avatarServiceUrl, '/');
    }

    /**
     * Send the profile photo to the Python avatar microservice and get back PNG bytes.
     *
     * @param string $photoPath Absolute path to the original photo file
     * @return string|null PNG image bytes on success, null on failure
     */
    public function generateAvatar(string $photoPath): ?string
    {
        if (!file_exists($photoPath)) {
            $this->logger->error('Avatar generation failed: photo file not found at {path}', ['path' => $photoPath]);
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', $this->serviceUrl . '/generate-avatar', [
                'timeout' => 120, // model inference can take time on CPU
                'headers' => [
                    'Accept' => 'image/png',
                ],
                'body' => [
                    'file' => fopen($photoPath, 'r'),
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->error('Avatar service returned HTTP {code}: {body}', [
                    'code' => $statusCode,
                    'body' => $response->getContent(false),
                ]);
                return null;
            }

            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            if (!str_contains($contentType, 'image/png')) {
                $this->logger->error('Avatar service returned unexpected content type: {type}', ['type' => $contentType]);
                return null;
            }

            return $response->getContent();
        } catch (\Exception $e) {
            $this->logger->error('Avatar generation service error: {message}', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
