<template>
    <ct-block extends="ct_admin_menu_user_actions_items">
        <router-link v-if="acl.can('user.update_profile')" v-slot="{ navigate }" :to="{ name: 'ct.profile.index' }" custom>
            <mt-action-menu-item class="ct-profile-menu__profile" icon="regular-user" @click="navigate">
                {{ $t('ct-profile.general.headlineProfile') }}
            </mt-action-menu-item>
        </router-link>

        <ct-block-parent />
    </ct-block>
</template>

<script setup lang="ts">
import { inject } from 'vue';
import type AclService from 'src/app/service/acl.service';

const acl = inject<AclService>('acl');

if (!acl) {
    throw new Error('The ACL service is required by the profile menu override.');
}

ctDefineOverride({});
</script>
