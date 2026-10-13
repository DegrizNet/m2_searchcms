<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Search;

use Magelan\SearchCms\Api\Data\HitInterface;

class Hit implements HitInterface
{
    public function __construct(
        private readonly string $type,
        private readonly int $entityId,
        private readonly string $identifier,
        private readonly string $title,
        private readonly string $url,
        private readonly string $snippetHtml,
        private readonly int $score
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getSnippetHtml(): string
    {
        return $this->snippetHtml;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function isBlogPost(): bool
    {
        return $this->type === self::TYPE_BLOG_POST;
    }
}
