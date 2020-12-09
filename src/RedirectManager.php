<?php

namespace App;

use App\Exception\ImportException;
use App\Post;

class RedirectManager
{
    public function getRedirect(Post $post, $force = false): ?array
    {
        $oldSlug = $post->slug;
        $shouldRedirect = $this->improveSlug($post);

        $newUrl = sprintf('/%s', $post->slug);

        if ($post->type === 'page') {
            // Redirect if old slug had a date or if any transformation has been made
            if (preg_match('|^\d{4}/\d{2}|', $oldSlug)) {
                // Slug had a date but pages should not have a date
                $shouldRedirect = true;
            }
        } else {
            if (!preg_match('|^\d{4}/\d{2}|', $oldSlug)) {
                // Slug didn't have a date. We need to redirect to the page with a date
                $publishedAt = new \DateTime($post->published_at);

                $newUrl = sprintf(
                    '/%s/%s',
                    $publishedAt->format('Y/m'),
                    $post->slug
                );
                $shouldRedirect = true;
            }
        }

        if (!$shouldRedirect && !$force) {
            return null;
        }

        return [
            'old-slug' => $oldSlug,
            'new-slug' => $newUrl,
        ];
    }

    public function addRedirect(string $oldUrl, $newUrl)
    {
        if (!class_exists('Red_Item')) {
            throw new ImportException('Redirection plugin is not installed');
        }

        $redirectData = [
            'status' => 'enabled',
            'url' => $oldUrl,
            'action_code' => 301,
            'action_data' => ['url' => $newUrl],
            'action_type' => 'url',
            'match_type' => 'url',
            'regex' => false,
            'group_id' => 1,
        ];

        \Red_Item::create($redirectData);
    }

    protected function improveSlug(Post $post): bool
    {
        $shouldRedirect = false;
        $newSlug = $post->slug;

        // Remove date from slug
        $newSlug = preg_replace('|^\d{4}/\d{2}/|', '', $newSlug);

        // Remove .html from slug. No need for redirect because a general rule is added
        // to redirect all .html URLs to non .html URLs
        $newSlug = preg_replace('/\.html$/', '', $newSlug);

        if (preg_match('/^article-/', $newSlug)) {
            // Use post slug instead of ugly article ID in the URL
            // eg. "article-1234" will become "my-page-title"
            $newSlug = sanitize_title($post->title);
            $shouldRedirect = true;
        }

        $post->slug = $newSlug;

        return $shouldRedirect;
    }
}
