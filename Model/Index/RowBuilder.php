<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Index;

use Magelan\SearchCms\Api\Data\HitInterface;
use Magelan\SearchCms\Model\Text\Folder;
use Magelan\SearchCms\Model\Text\HtmlToText;

/**
 * Turns a CMS page or a blog post translation into rows of magelan_searchcms_index.
 */
class RowBuilder
{
    public function __construct(
        private readonly HtmlToText $htmlToText,
        private readonly Folder $folder
    ) {
    }

    /**
     * One row per store view the page is assigned to (0 = all store views). Inactive pages: no rows.
     *
     * @param array<string, mixed> $page cms_page columns
     * @param array<int, int|string> $storeIds
     * @return array<int, array<string, mixed>>
     */
    public function fromCmsPage(array $page, array $storeIds): array
    {
        if (!(int) ($page['is_active'] ?? 0) || empty($page['page_id'])) {
            return [];
        }

        $body = $this->htmlToText->convert((string) ($page['content'] ?? ''));
        $meta = trim(implode(' ', array_filter([
            (string) ($page['meta_title'] ?? ''),
            (string) ($page['meta_keywords'] ?? ''),
            (string) ($page['meta_description'] ?? ''),
        ])));

        $base = $this->row(
            HitInterface::TYPE_CMS_PAGE,
            (int) $page['page_id'],
            (string) ($page['identifier'] ?? ''),
            (string) ($page['title'] ?? ''),
            (string) ($page['content_heading'] ?? ''),
            $meta,
            $body,
            null
        );

        $rows = [];
        foreach (array_unique(array_map('intval', $storeIds)) as $storeId) {
            $rows[] = ['store_id' => $storeId] + $base;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $post magelan_blog_post_translation columns plus publish_at
     * @return array<string, mixed>|null
     */
    public function fromBlogPost(array $post): ?array
    {
        if ((int) ($post['status'] ?? 0) !== 1 || empty($post['post_id'])) {
            return null;
        }
        $meta = trim(implode(' ', array_filter([
            (string) ($post['meta_title'] ?? ''),
            (string) ($post['meta_description'] ?? ''),
        ])));
        // content_text is already plain text (derived by the blog); normalise whitespace and length.
        $body = $this->htmlToText->convert(htmlspecialchars((string) ($post['content_text'] ?? ''), ENT_NOQUOTES));

        return ['store_id' => (int) ($post['store_id'] ?? 0)] + $this->row(
            HitInterface::TYPE_BLOG_POST,
            (int) $post['post_id'],
            (string) ($post['url_key'] ?? ''),
            (string) ($post['title'] ?? ''),
            '',
            $meta,
            $body,
            !empty($post['publish_at']) ? (string) $post['publish_at'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $type,
        int $entityId,
        string $identifier,
        string $title,
        string $heading,
        string $meta,
        string $body,
        ?string $availableFrom
    ): array {
        $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return [
            'entity_type' => $type,
            'entity_id' => $entityId,
            'identifier' => mb_substr($identifier, 0, 255, 'UTF-8'),
            'title' => mb_substr($title, 0, 255, 'UTF-8'),
            'title_f' => $this->folder->fold($title),
            'heading_f' => $this->folder->fold(trim(strip_tags($heading))),
            'meta_f' => $this->folder->fold(strip_tags($meta)),
            'body' => $body,
            'body_f' => $this->folder->fold($body),
            'body_length' => mb_strlen($body, 'UTF-8'),
            'available_from' => $availableFrom,
        ];
    }
}
