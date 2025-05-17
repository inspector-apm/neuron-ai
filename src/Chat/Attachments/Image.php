<?php

namespace NeuronAI\Chat\Attachments;

class Image extends Attachment
{
    public function __construct(
        string $image,
        string $type = self::TYPE_URL,
        ?string $mediaType = null
    ) {
        parent::__construct(
            Attachment::IMAGE,
            $image,
            $type,
            $mediaType
        );
    }
}
