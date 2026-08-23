<?php declare(strict_types=1);

namespace Contena\Core\System\Member;

class MemberEvents
{
    final public const string MEMBER_WRITTEN_EVENT = 'member.written';

    final public const string MEMBER_DELETED_EVENT = 'member.deleted';

    final public const string MEMBER_LOADED_EVENT = 'member.loaded';

    final public const string MEMBER_SEARCH_RESULT_LOADED_EVENT = 'member.search.result.loaded';

    final public const string MEMBER_AGGREGATION_LOADED_EVENT = 'member.aggregation.result.loaded';

    final public const string MEMBER_ID_SEARCH_RESULT_LOADED_EVENT = 'member.id.search.result.loaded';

    final public const string MEMBER_ADDRESS_WRITTEN_EVENT = 'member_address.written';

    final public const string MEMBER_ADDRESS_DELETED_EVENT = 'member_address.deleted';

    final public const string MEMBER_ADDRESS_LOADED_EVENT = 'member_address.loaded';

    final public const string MEMBER_ADDRESS_SEARCH_RESULT_LOADED_EVENT = 'member_address.search.result.loaded';

    final public const string MEMBER_ADDRESS_AGGREGATION_LOADED_EVENT = 'member_address.aggregation.result.loaded';

    final public const string MEMBER_ADDRESS_ID_SEARCH_RESULT_LOADED_EVENT = 'member_address.id.search.result.loaded';

    final public const string MEMBER_BEFORE_LOGIN_EVENT = 'member.before.login';

    final public const string MEMBER_LOGIN_EVENT = 'member.login';

    final public const string MEMBER_LOGOUT_EVENT = 'member.logout';

    final public const string MEMBER_REGISTER_EVENT = 'member.register';

    final public const string MEMBER_GROUP_WRITTEN_EVENT = 'member_group.written';

    final public const string MEMBER_GROUP_DELETED_EVENT = 'member_group.deleted';

    final public const string MEMBER_GROUP_LOADED_EVENT = 'member_group.loaded';

    final public const string MEMBER_GROUP_SEARCH_RESULT_LOADED_EVENT = 'member_group.search.result.loaded';

    final public const string MEMBER_GROUP_AGGREGATION_LOADED_EVENT = 'member_group.aggregation.result.loaded';

    final public const string MEMBER_GROUP_ID_SEARCH_RESULT_LOADED_EVENT = 'member_group.id.search.result.loaded';

    final public const string MEMBER_GROUP_TRANSLATION_WRITTEN_EVENT = 'member_group_translation.written';

    final public const string MEMBER_GROUP_TRANSLATION_DELETED_EVENT = 'member_group_translation.deleted';

    final public const string MEMBER_GROUP_TRANSLATION_LOADED_EVENT = 'member_group_translation.loaded';

    final public const string MEMBER_GROUP_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'member_group_translation.search.result.loaded';

    final public const string MEMBER_GROUP_TRANSLATION_AGGREGATION_LOADED_EVENT = 'member_group_translation.aggregation.result.loaded';

    final public const string MEMBER_GROUP_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'member_group_translation.id.search.result.loaded';

    final public const string MAPPING_REGISTER_MEMBER = 'member.channel.register.member';

    final public const string MAPPING_MEMBER_PROFILE_SAVE = 'member.channel.profile.update';

    final public const string MAPPING_ADDRESS_CREATE = 'member.channel.address.create';
}
