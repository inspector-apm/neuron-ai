<?php

namespace NeuronAI\Chat\Messages;

use NeuronAI\Chat\Attachments\Attachment;

class Message implements \JsonSerializable
{
    const ROLE_USER = 'user';
    const ROLE_ASSISTANT = 'assistant';
    const ROLE_MODEL = 'model';
    const ROLE_TOOL = 'tool';
    const ROLE_SYSTEM = 'system';

    protected ?Usage $usage = null;
    protected array $attachments = [];

    protected array $meta = [];

    public function __construct(
        protected string $role,
        protected array|string|int|float|null $content = null
    ) {}

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): Message
    {
        $this->role = $role;
        return $this;
    }

    public function getContent(): mixed
    {
        return $this->content;
    }

    public function setContent(mixed $content): Message
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return array<Attachment>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): Message
    {
        $this->attachments[] = $attachment;
        return $this;
    }

    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    public function setUsage(Usage $usage): static
    {
        $this->usage = $usage;
        return $this;
    }

    public function addMetadata(string $key, string|array|null $value): Message
    {
        $this->meta[$key] = $value;
        return $this;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'role' => $this->getRole(),
            'content' => $this->getContent()
        ];

        if ($this->getUsage()) {
            $data['usage'] = $this->getUsage()->jsonSerialize();
        }

        if (!empty($this->getAttachments())) {
            $data['attachments'] = \array_map(fn (Attachment $attachment) => $attachment->jsonSerialize(), $this->getAttachments());
        }

        return \array_merge($this->meta, $data);
    }
}
