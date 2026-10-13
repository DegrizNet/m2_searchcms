<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Api\Data;

/**
 * One search result: a CMS page or (optionally) a Magelan Blog post.
 */
interface HitInterface
{
    public const TYPE_CMS_PAGE = 'cms_page';
    public const TYPE_BLOG_POST = 'blog_post';

    /**
     * cms_page or blog_post
     */
    public function getType(): string;

    /**
     * CMS page ID or blog post ID
     */
    public function getEntityId(): int;

    public function getIdentifier(): string;

    public function getTitle(): string;

    public function getUrl(): string;

    /**
     * Excerpt as HTML: escaped text, the search terms wrapped in <mark>. Empty when snippets are off.
     */
    public function getSnippetHtml(): string;

    public function getScore(): int;

    public function isBlogPost(): bool;
}
