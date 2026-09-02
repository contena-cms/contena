<template>
    <ct-block extends="ct_admin_menu_user_actions_items">
        <router-link v-if="acl.can('user.update_profile')" v-slot="{ navigate }" :to="{ name: 'ct.profile.index' }" custom>
            <mt-action-menu-item class="ct-profile-menu__profile" icon="regular-user" @click="navigate">
                {{ $t('ct-profile.general.headlineProfile') }}
            </mt-action-menu-item>
        </router-link>

        <mt-action-menu-item class="ct-profile-menu__theme-toggle" :icon="themeToggle.icon" @click="toggleTheme">
            {{ $t(themeToggle.label) }}
        </mt-action-menu-item>

        <ct-block-parent />
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue';
import type AclService from 'src/app/service/acl.service';
import useTheme from 'src/app/composables/use-theme';

const acl = inject<AclService>('acl');

if (!acl) {
    throw new Error('The ACL service is required by the profile menu override.');
}

const { resolvedTheme, saveUserTheme } = useTheme();
const themeToggle = computed(() => ({
    icon: resolvedTheme.value === 'dark' ? 'regular-sun' : 'regular-moon',
    label:
        resolvedTheme.value === 'dark'
            ? 'global.ct-admin-menu.themeToggle.switchToLight'
            : 'global.ct-admin-menu.themeToggle.switchToDark',
}));

async function toggleTheme(): Promise<void> {
    await saveUserTheme(resolvedTheme.value === 'dark' ? 'light' : 'dark');
}

ctDefineOverride({});
</script>
