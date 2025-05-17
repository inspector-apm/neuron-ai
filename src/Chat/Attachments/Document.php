<?php

namespace NeuronAI\Chat\Attachments;

class Document extends Attachment
{
    public function __construct(
        string $document,
        string $type = self::TYPE_URL,
        $mediaType = null
    ) {
        parent::__construct(
            Attachment::DOCUMENT,
            $document,
            $type,
            $mediaType
        );
    }
}
