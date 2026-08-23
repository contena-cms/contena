import initializeUserContext from 'src/app/init-post/user-information.init';
import { initializeUserNotifications } from 'src/app/store/notification.store';

jest.mock('src/app/store/notification.store', () => ({
    initializeUserNotifications: jest.fn(),
}));

describe('src/app/init-post/user-information.init.ts', () => {
    let isLoggedIn = true;
    const logoutMock = jest.fn(() => true);
    const userConfigSearchMock = jest.fn(() => Promise.resolve({ data: {} }));
    const userConfigUpsertMock = jest.fn(() => Promise.resolve());
    let userData = {
        data: {
            username: 'my-fancy-username',
            password: 'my-strong-password',
        },
    };

    beforeAll(() => {
        Contena.Service().register('loginService', () => {
            return {
                isLoggedIn: () => isLoggedIn,
                logout: logoutMock,
                addOnLoginListener: jest.fn(),
            };
        });

        Contena.Service().register('userService', () => {
            return {
                getUser: () => Promise.resolve(userData),
            };
        });

        Contena.Service().register('userConfigService', () => ({
            search: userConfigSearchMock,
            upsert: userConfigUpsertMock,
        }));
    });

    beforeEach(() => {
        Contena.Store.get('session').setCurrentUser(undefined);
        initializeUserNotifications.mockClear();
        logoutMock.mockClear();
        userConfigSearchMock.mockClear();
        userConfigUpsertMock.mockClear();
        isLoggedIn = true;
        userData = {
            data: {
                username: 'my-fancy-username',
                password: 'my-strong-password',
            },
        };
    });

    it('should init the user context service correctly when user is logged in', async () => {
        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(Contena.Store.get('session').currentUser).toBeUndefined();

        await initializeUserContext();

        expect(initializeUserNotifications).toHaveBeenCalled();
        expect(Contena.Store.get('session').currentUser).toEqual({
            username: 'my-fancy-username',
        });
    });

    it('should init the user context service correctly when user is not logged in', async () => {
        isLoggedIn = false;

        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(logoutMock).not.toHaveBeenCalled();
        expect(Contena.Store.get('session').currentUser).toBeUndefined();

        await initializeUserContext();

        expect(logoutMock).toHaveBeenCalled();
        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(Contena.Store.get('session').currentUser).toBeUndefined();
    });

    it('should call logout when user value is not correct', async () => {
        userData = {
            foo: 'not-working',
        };

        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(logoutMock).not.toHaveBeenCalled();
        expect(Contena.Store.get('session').currentUser).toBeUndefined();

        await initializeUserContext();

        expect(logoutMock).toHaveBeenCalled();
        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(Contena.Store.get('session').currentUser).toBeUndefined();
    });
});
