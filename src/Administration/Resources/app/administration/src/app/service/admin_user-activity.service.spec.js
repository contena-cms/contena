/**
 * @sw-package framework
 */
import AdminUserActivityService from './admin_user-activity.service';

describe('src/app/service/admin_user-activity.service.ts', () => {
    let service;

    beforeEach(() => {
        service = new AdminUserActivityService();
    });

    afterEach(() => {
        localStorage.removeItem('lastActivity');
    });

    it('should instantiate', () => {
        expect(service instanceof AdminUserActivityService).toBe(true);
    });

    it('should change last admin_user activity', () => {
        const date = new Date();

        service.updateLastUserActivity(date);

        expect(service.getLastUserActivity().getTime()).toBe(date.getTime());
    });
});
