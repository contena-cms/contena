<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Content\Blog\Events\BlogListingCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogListingResultEvent;
use Contena\Core\Content\Blog\Events\BlogSearchCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogSearchResultEvent;
use Contena\Core\Content\Blog\Events\BlogSuggestCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogSuggestResultEvent;

class BlogEvents
{
    final public const string BLOG_LISTING_CRITERIA = BlogListingCriteriaEvent::class;
    final public const string BLOG_SUGGEST_CRITERIA = BlogSuggestCriteriaEvent::class;
    final public const string BLOG_SEARCH_CRITERIA = BlogSearchCriteriaEvent::class;
    final public const string BLOG_LISTING_RESULT = BlogListingResultEvent::class;
    final public const string BLOG_SUGGEST_RESULT = BlogSuggestResultEvent::class;
    final public const string BLOG_SEARCH_RESULT = BlogSearchResultEvent::class;
    final public const string BLOG_INDEXER_EVENT = BlogIndexerEvent::class;

    final public const string BLOG_WRITTEN_EVENT = 'blog.written';
    final public const string BLOG_DELETED_EVENT = 'blog.deleted';
    final public const string BLOG_LOADED_EVENT = 'blog.loaded';
    final public const string BLOG_SEARCH_RESULT_LOADED_EVENT = 'blog.search.result.loaded';
    final public const string BLOG_AGGREGATION_LOADED_EVENT = 'blog.aggregation.result.loaded';
    final public const string BLOG_ID_SEARCH_RESULT_LOADED_EVENT = 'blog.id.search.result.loaded';

    final public const string BLOG_CATEGORY_WRITTEN_EVENT = 'blog_category.written';
    final public const string BLOG_CATEGORY_DELETED_EVENT = 'blog_category.deleted';
    final public const string BLOG_CATEGORY_LOADED_EVENT = 'blog_category.loaded';
    final public const string BLOG_CATEGORY_SEARCH_RESULT_LOADED_EVENT = 'blog_category.search.result.loaded';
    final public const string BLOG_CATEGORY_AGGREGATION_LOADED_EVENT = 'blog_category.aggregation.result.loaded';
    final public const string BLOG_CATEGORY_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_category.id.search.result.loaded';

    final public const string BLOG_CATEGORY_TREE_WRITTEN_EVENT = 'blog_category_tree.written';
    final public const string BLOG_CATEGORY_TREE_DELETED_EVENT = 'blog_category_tree.deleted';
    final public const string BLOG_CATEGORY_TREE_LOADED_EVENT = 'blog_category_tree.loaded';
    final public const string BLOG_CATEGORY_TREE_SEARCH_RESULT_LOADED_EVENT = 'blog_category_tree.search.result.loaded';
    final public const string BLOG_CATEGORY_TREE_AGGREGATION_LOADED_EVENT = 'blog_category_tree.aggregation.result.loaded';
    final public const string BLOG_CATEGORY_TREE_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_category_tree.id.search.result.loaded';

    final public const string BLOG_MEDIA_WRITTEN_EVENT = 'blog_media.written';
    final public const string BLOG_MEDIA_DELETED_EVENT = 'blog_media.deleted';
    final public const string BLOG_MEDIA_LOADED_EVENT = 'blog_media.loaded';
    final public const string BLOG_MEDIA_SEARCH_RESULT_LOADED_EVENT = 'blog_media.search.result.loaded';
    final public const string BLOG_MEDIA_AGGREGATION_LOADED_EVENT = 'blog_media.aggregation.result.loaded';
    final public const string BLOG_MEDIA_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_media.id.search.result.loaded';

    final public const string BLOG_TRANSLATION_WRITTEN_EVENT = 'blog_translation.written';
    final public const string BLOG_TRANSLATION_DELETED_EVENT = 'blog_translation.deleted';
    final public const string BLOG_TRANSLATION_LOADED_EVENT = 'blog_translation.loaded';
    final public const string BLOG_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'blog_translation.search.result.loaded';
    final public const string BLOG_TRANSLATION_AGGREGATION_LOADED_EVENT = 'blog_translation.aggregation.result.loaded';
    final public const string BLOG_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_translation.id.search.result.loaded';

    final public const string BLOG_VISIBILITY_WRITTEN_EVENT = 'blog_visibility.written';
    final public const string BLOG_VISIBILITY_DELETED_EVENT = 'blog_visibility.deleted';
    final public const string BLOG_VISIBILITY_LOADED_EVENT = 'blog_visibility.loaded';
    final public const string BLOG_VISIBILITY_SEARCH_RESULT_LOADED_EVENT = 'blog_visibility.search.result.loaded';
    final public const string BLOG_VISIBILITY_AGGREGATION_LOADED_EVENT = 'blog_visibility.aggregation.result.loaded';
    final public const string BLOG_VISIBILITY_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_visibility.id.search.result.loaded';

    final public const string BLOG_MAIN_CATEGORY_WRITTEN_EVENT = 'blog_main_category.written';
    final public const string BLOG_MAIN_CATEGORY_DELETED_EVENT = 'blog_main_category.deleted';
    final public const string BLOG_MAIN_CATEGORY_LOADED_EVENT = 'blog_main_category.loaded';
    final public const string BLOG_MAIN_CATEGORY_SEARCH_RESULT_LOADED_EVENT = 'blog_main_category.search.result.loaded';
    final public const string BLOG_MAIN_CATEGORY_AGGREGATION_LOADED_EVENT = 'blog_main_category.aggregation.result.loaded';
    final public const string BLOG_MAIN_CATEGORY_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_main_category.id.search.result.loaded';
}
