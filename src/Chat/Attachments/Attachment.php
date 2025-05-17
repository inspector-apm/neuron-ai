<?php

namespace NeuronAI\Chat\Attachments;

use NeuronAI\StaticConstructor;

class Attachment implements \JsonSerializable
{
    use StaticConstructor;

    const DOCUMENT = 'document';
    const IMAGE = 'image';
    const TYPE_URL = 'url';
    const TYPE_BASE64 = 'base64';

    public function __construct(
        public string $type,
        public string $content,
        public string $contentType,
        public ?string $mediaType
    ) {
        //
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            $this->type => $this->content,
            'type' => $this->contentType,
            'media_type' => $this->mediaType,
        ]);
    }
}
