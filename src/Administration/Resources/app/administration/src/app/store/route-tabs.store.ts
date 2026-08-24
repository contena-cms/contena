export const DASHBOARD_ROUTE_NAME = 'ct.dashboard.index';

export interface RouteTab {
    key: string;
    routeName: string;
    title: string;
    pinned: boolean;
    refreshKey: number;
}

export type RouteTabCloseMode = 'current' | 'left' | 'right' | 'others' | 'all';

const routeTabsStore = Contena.Store.register({
    id: 'routeTabs',

    state: () => ({
        tabs: [] as RouteTab[],
    }),

    actions: {
        addTab(tab: Omit<RouteTab, 'refreshKey'>) {
            const existingTab = this.tabs.find((item) => item.key === tab.key);
            if (existingTab) {
                existingTab.title = tab.title;
                existingTab.routeName = tab.routeName;
                existingTab.pinned = existingTab.pinned || tab.pinned;
                return;
            }

            this.tabs.push({ ...tab, refreshKey: 0 });
        },

        refreshTab(key: string) {
            const tab = this.tabs.find((item) => item.key === key);
            if (tab) {
                tab.refreshKey += 1;
            }
        },

        closeTab(key: string): string | undefined {
            const index = this.tabs.findIndex((item) => item.key === key);
            if (index < 0 || this.tabs[index].pinned) {
                return key;
            }

            this.tabs.splice(index, 1);
            return this.tabs[index]?.key ?? this.tabs[index - 1]?.key ?? this.tabs[0]?.key;
        },

        closeTabs(mode: RouteTabCloseMode, activeKey: string): string | undefined {
            const activeIndex = this.tabs.findIndex((item) => item.key === activeKey);
            const shouldKeep = (tab: RouteTab, index: number): boolean => {
                if (tab.pinned) {
                    return true;
                }

                if (mode === 'current') {
                    return tab.key !== activeKey;
                }
                if (mode === 'left') {
                    return activeIndex < 0 || index >= activeIndex;
                }
                if (mode === 'right') {
                    return activeIndex < 0 || index <= activeIndex;
                }
                if (mode === 'others') {
                    return tab.key === activeKey;
                }

                return false;
            };

            if (mode === 'current') {
                return this.closeTab(activeKey);
            }

            this.tabs = this.tabs.filter(shouldKeep);
            return this.tabs.some((tab) => tab.key === activeKey) ? activeKey : this.tabs[0]?.key;
        },
    },
});

export type RouteTabsStore = ReturnType<typeof routeTabsStore>;

export default routeTabsStore;
