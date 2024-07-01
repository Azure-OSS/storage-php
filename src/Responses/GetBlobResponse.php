<?php

declare(strict_types=1);

namespace Brecht\FlysystemAzureBlobStorage\Responses;

 use Psr\Http\Message\StreamInterface;

 class GetBlobResponse
{
    public function __construct(
        public StreamInterface $content,
        public \DateTimeInterface $lastModified,
        public int $contentLength,
        public string $contentType,
        public string $contentMD5,
    ) {
    }
}
