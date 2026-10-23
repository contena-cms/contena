/**
 * @sw-package buyers-experience
 */
import RecentlySearchService from 'src/app/service/recently-search.service';

describe('app/service/recently-search.service.js', () => {
    let recentlySearchService;

    beforeEach(async () => {
        recentlySearchService = new RecentlySearchService();
    });

    it('get method should return empty array', async () => {
        const data = recentlySearchService.get('admin_userId');

        expect(data).toEqual([]);
    });

    it('get method should return items from localStorage', async () => {
        const items = [{ entity: 'blog', id: 'blogId' }];

        localStorage.setItem(recentlySearchService._key('admin_userId'), JSON.stringify(items));

        const data = recentlySearchService.get('admin_userId');

        expect(data).toEqual(items);
    });

    it('add method should save item into localStorage', async () => {
        localStorage.removeItem(recentlySearchService._key('admin_userId'));

        const items = [{ entity: 'blog', id: 'blogId', payload: {} }];

        recentlySearchService.add('admin_userId', 'blog', 'blogId');

        const actualStorage = JSON.parse(localStorage.getItem(recentlySearchService._key('admin_userId')));

        expect(actualStorage).toHaveLength(1);
        expect(actualStorage[0].timestamp).toBeTruthy();

        delete actualStorage[0].timestamp;

        expect(actualStorage).toEqual(items);
    });

    it('add method should add up to maximum stack', async () => {
        const maximumStack = recentlySearchService._maxStackSize();

        for (let i = 0; i < maximumStack; i += 1) {
            recentlySearchService.add('admin_userId', 'blog', `blogId-${i}`);
        }

        const data = recentlySearchService.get('admin_userId');

        expect(data).toHaveLength(maximumStack);
    });

    it('add method should push newst item to the top', async () => {
        const maximumStack = recentlySearchService._maxStackSize();

        for (let i = 0; i < maximumStack; i += 1) {
            recentlySearchService.add('admin_userId', 'blog', `blogId-${i}`);
        }

        recentlySearchService.add('admin_userId', 'blog', 'blogId-new');

        const data = recentlySearchService.get('admin_userId');

        expect(data).toHaveLength(maximumStack);
        expect(data[0].entity).toBe('blog');
        expect(data[0].id).toBe('blogId-new');
    });

    it('add method should pop oldest out of stack', async () => {
        const maximumStack = recentlySearchService._maxStackSize();

        recentlySearchService.add('admin_userId', 'blog', 'blogId-old');

        for (let i = 0; i < maximumStack; i += 1) {
            recentlySearchService.add('admin_userId', 'blog', `blogId-${i}`);
        }

        const data = recentlySearchService.get('admin_userId');

        const foundOldProduct = data.find((item) => item.id === 'blogId-old');

        expect(foundOldProduct).toBeUndefined();
    });

    it('add method should move duplicated item to the top', async () => {
        const maximumStack = recentlySearchService._maxStackSize();

        recentlySearchService.add('admin_userId', 'blog', 'blogId-unique');

        const uniqueProduct = recentlySearchService.get('admin_userId')[0];

        for (let i = 0; i < maximumStack; i += 1) {
            recentlySearchService.add('admin_userId', 'blog', `blogId-${i}`);
        }

        // Sleep 1 milisecond
        await new Promise((resolve) => {
            setTimeout(resolve, 1);
        });

        recentlySearchService.add('admin_userId', uniqueProduct.entity, uniqueProduct.id);

        const data = recentlySearchService.get('admin_userId');

        const foundOldProducts = data.filter((item) => item.id === uniqueProduct.id);

        expect(foundOldProducts).toBeTruthy();
        expect(foundOldProducts).toHaveLength(1);

        const foundOldProduct = foundOldProducts[0];

        expect(foundOldProduct).toEqual(data[0]);
        expect(foundOldProduct.entity).toEqual(uniqueProduct.entity);
        expect(foundOldProduct.id).toEqual(uniqueProduct.id);
        expect(foundOldProduct.timestamp).toBeGreaterThanOrEqual(uniqueProduct.timestamp);
    });
});
