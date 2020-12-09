<?php

namespace App;

use App\Event\PostImportedEvent;
use App\Exception\ImportException;
use App\Loader\LoaderInterface;
use App\Transformer\EmptyParagraphCleanup;
use App\Transformer\FontFamilyRemover;
use App\Transformer\InternalLinksRewriter;
use App\Transformer\LinksToImagesRemover;
use App\Transformer\SommelierVinsArticleCleanup;
use App\Transformer\TransformerInterface;
use App\Transformer\TypoCleanup;
use App\Writer\WriterInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

// See https://wordpress.org/support/topic/red_itemcreate-throws-error/
include_once WP_PLUGIN_DIR . '/redirection/models/group.php';

class Importer
{
    protected EventDispatcherInterface $dispatcher;
    protected LoaderInterface $loader;
    protected WriterInterface $writer;

    /**
     * @var TransformerInterface[]
     */
    protected $transformers;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        LoaderInterface $loader,
        WriterInterface $writer
    ) {
        $this->dispatcher = $dispatcher;
        $this->loader = $loader;
        $this->writer = $writer;

        $this->transformers = [
            new EmptyParagraphCleanup(),
            new FontFamilyRemover(),
            new LinksToImagesRemover(),
            new SommelierVinsArticleCleanup(),
            new TypoCleanup(),
            new InternalLinksRewriter($loader, new RedirectManager()),
        ];
    }

    public function import(array $options = [])
    {
        $this->importPosts($options);
    }

    public function importPosts(array $options)
    {
        $this->importEntries($this->loader->getPosts(), 'post', $options);
    }

    public function importPages(array $options)
    {
        $this->importEntries($this->loader->getPages(), 'page', $options);
    }

    protected function importEntries($posts, $postType, $options)
    {
        $nbImported = 0;

        $this->preImportActions();

        foreach ($posts as $post) {
            $post = $this->loader->mapToPostObject($post, $postType);

            if (empty($post->slug)) {
                // Do not import posts with no slug
                continue;
            }

            if (!empty($options['slug']) && !preg_match(sprintf('|%s|', $options['slug']), $post->slug)) {
                $this->dispatcher->dispatch(new PostImportedEvent());
                $nbImported++;
                continue;
            }

            $this->applyTransformers($post);

            if (empty($options['ignore-images'])) {
                $post = $this->writer->importImages($post);
                $post = $this->writer->importUploadedFiles($post);
            }

            $this->writer->savePost($post);

            if (empty($options['ignore-comments']) && $post->comments) {
                $this->importComments($post, $post->comments);
            }

            $this->dispatcher->dispatch(new PostImportedEvent());
            $nbImported++;

            if ($options['limit'] !== null && $nbImported >= $options['limit']) {
                break;
            }
        }
    }

    private function importComments($post, $comments, $parentComment = null)
    {
        foreach ($comments as $comment) {
            $this->writer->saveComment($post, $comment, $parentComment);

            if (!empty($comment->replies->comment)) {
                $this->importComments($post, $comment->replies->comment, $comment);
            }
        }
    }

    private function applyTransformers($post)
    {
        foreach ($this->transformers as $transformer) {
            $post->content = $transformer->transform($post->content);
        }
    }

    protected function preImportActions()
    {
        if (!class_exists('Red_Item')) {
            throw new ImportException('Redirection plugin is not installed');
        }

        // Redirect .html pages to non .html ones (eg. /test.html to /test)
        $redirectData = [
            'status'      => 'enabled',
            'url'         => '^/(.+)\.html',
            'action_code' => 301,
            'action_data' => ['url' => '/$1'],
            'action_type' => 'url',
            'match_type'  => 'url',
            'regex'       => true,
            'group_id'    => 1,
            'position'    => 20000,
        ];

        \Red_Item::create($redirectData);
    }
}
