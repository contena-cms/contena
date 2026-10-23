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
    final public const BLOG_LISTING_CRITERIA = BlogListingCriteriaEvent::class;

    final public const BLOG_SUGGEST_CRITERIA = BlogSuggestCriteriaEvent::class;

    final public const BLOG_SEARCH_CRITERIA = BlogSearchCriteriaEvent::class;

    final public const BLOG_LISTING_RESULT = BlogListingResultEvent::class;

    final public const BLOG_SUGGEST_RESULT = BlogSuggestResultEvent::class;

    final public const BLOG_SEARCH_RESULT = BlogSearchResultEvent::class;

    final public const BLOG_INDEXER_EVENT = BlogIndexerEvent::class;

    final public const BLOG_WRITTEN_EVENT = 'blog.written';

    final public const BLOG_DELETED_EVENT = 'blog.deleted';

    final public const BLOG_LOADED_EVENT = 'blog.loaded';

    final public const BLOG_SEARCH_RESULT_LOADED_EVENT = 'blog.search.result.loaded';

    final public const BLOG_AGGREGATION_LOADED_EVENT = 'blog.aggregation.result.loaded';

    final public const BLOG_ID_SEARCH_RESULT_LOADED_EVENT = 'blog.id.search.result.loaded';

    final public const BLOG_CATEGORY_WRITTEN_EVENT = 'blog_category.written';

    final public const BLOG_CATEGORY_DELETED_EVENT = 'blog_category.deleted';

    final public const BLOG_CATEGORY_LOADED_EVENT = 'blog_category.loaded';

    final public const BLOG_CATEGORY_SEARCH_RESULT_LOADED_EVENT = 'blog_category.search.result.loaded';

    final public const BLOG_CATEGORY_AGGREGATION_LOADED_EVENT = 'blog_category.aggregation.result.loaded';

    final public const BLOG_CATEGORY_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_category.id.search.result.loaded';

    final public const BLOG_CONFIGURATOR_SETTING_WRITTEN_EVENT = 'blog_configurator_setting.written';

    final public const BLOG_CONFIGURATOR_SETTING_DELETED_EVENT = 'blog_configurator_setting.deleted';

    final public const BLOG_CONFIGURATOR_SETTING_LOADED_EVENT = 'blog_configurator_setting.loaded';

    final public const BLOG_CONFIGURATOR_SETTING_SEARCH_RESULT_LOADED_EVENT = 'blog_configurator_setting.search.result.loaded';

    final public const BLOG_CONFIGURATOR_SETTING_AGGREGATION_LOADED_EVENT = 'blog_configurator_setting.aggregation.result.loaded';

    final public const BLOG_CONFIGURATOR_SETTING_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_configurator_setting.id.search.result.loaded';

    final public const BLOG_PRICE_WRITTEN_EVENT = 'blog_price.written';

    final public const BLOG_PRICE_DELETED_EVENT = 'blog_price.deleted';

    final public const BLOG_PRICE_LOADED_EVENT = 'blog_price.loaded';

    final public const BLOG_PRICE_SEARCH_RESULT_LOADED_EVENT = 'blog_price.search.result.loaded';

    final public const BLOG_PRICE_AGGREGATION_LOADED_EVENT = 'blog_price.aggregation.result.loaded';

    final public const BLOG_PRICE_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_price.id.search.result.loaded';

    final public const BLOG_PROPERTY_WRITTEN_EVENT = 'blog_property.written';

    final public const BLOG_PROPERTY_DELETED_EVENT = 'blog_property.deleted';

    final public const BLOG_PROPERTY_LOADED_EVENT = 'blog_property.loaded';

    final public const BLOG_PROPERTY_SEARCH_RESULT_LOADED_EVENT = 'blog_property.search.result.loaded';

    final public const BLOG_PROPERTY_AGGREGATION_LOADED_EVENT = 'blog_property.aggregation.result.loaded';

    final public const BLOG_PROPERTY_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_property.id.search.result.loaded';

    final public const BLOG_MANUFACTURER_WRITTEN_EVENT = 'blog_manufacturer.written';

    final public const BLOG_MANUFACTURER_DELETED_EVENT = 'blog_manufacturer.deleted';

    final public const BLOG_MANUFACTURER_LOADED_EVENT = 'blog_manufacturer.loaded';

    final public const BLOG_MANUFACTURER_SEARCH_RESULT_LOADED_EVENT = 'blog_manufacturer.search.result.loaded';

    final public const BLOG_MANUFACTURER_AGGREGATION_LOADED_EVENT = 'blog_manufacturer.aggregation.result.loaded';

    final public const BLOG_MANUFACTURER_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_manufacturer.id.search.result.loaded';

    final public const BLOG_MANUFACTURER_TRANSLATION_WRITTEN_EVENT = 'blog_manufacturer_translation.written';

    final public const BLOG_MANUFACTURER_TRANSLATION_DELETED_EVENT = 'blog_manufacturer_translation.deleted';

    final public const BLOG_MANUFACTURER_TRANSLATION_LOADED_EVENT = 'blog_manufacturer_translation.loaded';

    final public const BLOG_MANUFACTURER_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'blog_manufacturer_translation.search.result.loaded';

    final public const BLOG_MANUFACTURER_TRANSLATION_AGGREGATION_LOADED_EVENT = 'blog_manufacturer_translation.aggregation.result.loaded';

    final public const BLOG_MANUFACTURER_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_manufacturer_translation.id.search.result.loaded';

    final public const BLOG_MEDIA_WRITTEN_EVENT = 'blog_media.written';

    final public const BLOG_MEDIA_DELETED_EVENT = 'blog_media.deleted';

    final public const BLOG_MEDIA_LOADED_EVENT = 'blog_media.loaded';

    final public const BLOG_MEDIA_SEARCH_RESULT_LOADED_EVENT = 'blog_media.search.result.loaded';

    final public const BLOG_MEDIA_AGGREGATION_LOADED_EVENT = 'blog_media.aggregation.result.loaded';

    final public const BLOG_MEDIA_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_media.id.search.result.loaded';

    final public const BLOG_SEARCH_KEYWORD_WRITTEN_EVENT = 'blog_search_keyword.written';

    final public const BLOG_SEARCH_KEYWORD_DELETED_EVENT = 'blog_search_keyword.deleted';

    final public const BLOG_SEARCH_KEYWORD_LOADED_EVENT = 'blog_search_keyword.loaded';

    final public const BLOG_SEARCH_KEYWORD_SEARCH_RESULT_LOADED_EVENT = 'blog_search_keyword.search.result.loaded';

    final public const BLOG_SEARCH_KEYWORD_AGGREGATION_LOADED_EVENT = 'blog_search_keyword.aggregation.result.loaded';

    final public const BLOG_SEARCH_KEYWORD_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_search_keyword.id.search.result.loaded';

    final public const BLOG_SEO_CATEGORY_WRITTEN_EVENT = 'blog_seo_category.written';

    final public const BLOG_SEO_CATEGORY_DELETED_EVENT = 'blog_seo_category.deleted';

    final public const BLOG_SEO_CATEGORY_LOADED_EVENT = 'blog_seo_category.loaded';

    final public const BLOG_SEO_CATEGORY_SEARCH_RESULT_LOADED_EVENT = 'blog_seo_category.search.result.loaded';

    final public const BLOG_SEO_CATEGORY_AGGREGATION_LOADED_EVENT = 'blog_seo_category.aggregation.result.loaded';

    final public const BLOG_SEO_CATEGORY_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_seo_category.id.search.result.loaded';

    final public const BLOG_SERVICE_WRITTEN_EVENT = 'blog_service.written';

    final public const BLOG_SERVICE_DELETED_EVENT = 'blog_service.deleted';

    final public const BLOG_SERVICE_LOADED_EVENT = 'blog_service.loaded';

    final public const BLOG_SERVICE_SEARCH_RESULT_LOADED_EVENT = 'blog_service.search.result.loaded';

    final public const BLOG_SERVICE_AGGREGATION_LOADED_EVENT = 'blog_service.aggregation.result.loaded';

    final public const BLOG_SERVICE_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_service.id.search.result.loaded';

    final public const BLOG_STREAM_WRITTEN_EVENT = 'blog_stream.written';

    final public const BLOG_STREAM_DELETED_EVENT = 'blog_stream.deleted';

    final public const BLOG_STREAM_LOADED_EVENT = 'blog_stream.loaded';

    final public const BLOG_STREAM_SEARCH_RESULT_LOADED_EVENT = 'blog_stream.search.result.loaded';

    final public const BLOG_STREAM_AGGREGATION_LOADED_EVENT = 'blog_stream.aggregation.result.loaded';

    final public const BLOG_STREAM_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_stream.id.search.result.loaded';

    final public const BLOG_STREAM_ASSIGNMENT_WRITTEN_EVENT = 'blog_stream_assignment.written';

    final public const BLOG_STREAM_ASSIGNMENT_DELETED_EVENT = 'blog_stream_assignment.deleted';

    final public const BLOG_STREAM_ASSIGNMENT_LOADED_EVENT = 'blog_stream_assignment.loaded';

    final public const BLOG_STREAM_ASSIGNMENT_SEARCH_RESULT_LOADED_EVENT = 'blog_stream_assignment.search.result.loaded';

    final public const BLOG_STREAM_ASSIGNMENT_AGGREGATION_LOADED_EVENT = 'blog_stream_assignment.aggregation.result.loaded';

    final public const BLOG_STREAM_ASSIGNMENT_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_stream_assignment.id.search.result.loaded';

    final public const BLOG_STREAM_TAB_WRITTEN_EVENT = 'blog_stream_tab.written';

    final public const BLOG_STREAM_TAB_DELETED_EVENT = 'blog_stream_tab.deleted';

    final public const BLOG_STREAM_TAB_LOADED_EVENT = 'blog_stream_tab.loaded';

    final public const BLOG_STREAM_TAB_SEARCH_RESULT_LOADED_EVENT = 'blog_stream_tab.search.result.loaded';

    final public const BLOG_STREAM_TAB_AGGREGATION_LOADED_EVENT = 'blog_stream_tab.aggregation.result.loaded';

    final public const BLOG_STREAM_TAB_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_stream_tab.id.search.result.loaded';

    final public const BLOG_TRANSLATION_WRITTEN_EVENT = 'blog_translation.written';

    final public const BLOG_TRANSLATION_DELETED_EVENT = 'blog_translation.deleted';

    final public const BLOG_TRANSLATION_LOADED_EVENT = 'blog_translation.loaded';

    final public const BLOG_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'blog_translation.search.result.loaded';

    final public const BLOG_TRANSLATION_AGGREGATION_LOADED_EVENT = 'blog_translation.aggregation.result.loaded';

    final public const BLOG_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_translation.id.search.result.loaded';

    final public const BLOG_OPTION_WRITTEN_EVENT = 'blog_option.written';

    final public const BLOG_OPTION_DELETED_EVENT = 'blog_option.deleted';

    final public const BLOG_OPTION_LOADED_EVENT = 'blog_option.loaded';

    final public const BLOG_OPTION_SEARCH_RESULT_LOADED_EVENT = 'blog_option.search.result.loaded';

    final public const BLOG_OPTION_AGGREGATION_LOADED_EVENT = 'blog_option.aggregation.result.loaded';

    final public const BLOG_OPTION_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_option.id.search.result.loaded';

    final public const BLOG_CATEGORY_TREE_WRITTEN_EVENT = 'blog_category_tree.written';

    final public const BLOG_CATEGORY_TREE_DELETED_EVENT = 'blog_category_tree.deleted';

    final public const BLOG_CATEGORY_TREE_LOADED_EVENT = 'blog_category_tree.loaded';

    final public const BLOG_CATEGORY_TREE_SEARCH_RESULT_LOADED_EVENT = 'blog_category_tree.search.result.loaded';

    final public const BLOG_CATEGORY_TREE_AGGREGATION_LOADED_EVENT = 'blog_category_tree.aggregation.result.loaded';

    final public const BLOG_CATEGORY_TREE_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_category_tree.id.search.result.loaded';

    final public const BLOG_REVIEW_LOADED = 'blog_review.loaded';

    final public const BLOG_REVIEW_WRITTEN_EVENT = 'blog_review.written';

    final public const BLOG_REVIEW_DELETED_EVENT = 'blog_review.deleted';

    final public const BLOG_REVIEW_SEARCH_RESULT_LOADED_EVENT = 'blog_review.search.result.loaded';

    final public const BLOG_REVIEW_AGGREGATION_LOADED_EVENT = 'blog_review.aggregation.result.loaded';

    final public const BLOG_REVIEW_ID_SEARCH_RESULT_LOADED_EVENT = 'blog_review.id.search.result.loaded';
}
