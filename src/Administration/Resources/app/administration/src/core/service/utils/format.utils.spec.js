import { fileSize, date, dateWithUserTimezone, toISODate } from 'src/core/service/utils/format.utils';

describe('src/core/service/utils/format.utils.js', () => {
    describe('filesize', () => {
        it('should convert bytes to a readable format', async () => {
            expect(fileSize(0)).toBe('0.00B');
            expect(fileSize(1018)).toBe('0.99KB');
            expect(fileSize(1023)).toBe('1.00KB');
            expect(fileSize(1024)).toBe('1.00KB');
            expect(fileSize(102400000)).toBe('97.66MB');
        });
    });

    describe('date', () => {
        const setLocale = (locale) => {
            jest.spyOn(Contena.Application.getContainer('factory').locale, 'getLastKnownLocale').mockImplementation(
                () => locale,
            );
        };
        const setTimeZone = (timeZone) => Contena.Store.get('session').setCurrentUser({ timeZone });

        beforeEach(async () => {
            setLocale('en-GB');
            setTimeZone('UTC');
        });

        it('should return empty string for null value', async () => {
            expect(date(null)).toBe('');
        });

        it('should convert the date correctly with timezone UTC in en-GB', async () => {
            setLocale('en-GB');
            setTimeZone('UTC');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('18 June 2000 at 08:30');
        });

        it('should convert the date correctly with timezone UTC in zh-CN', async () => {
            setLocale('zh-CN');
            setTimeZone('UTC');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('2000年6月18日 08:30');
        });

        it('should convert the date correctly with timezone America/New_York in en-GB', async () => {
            setLocale('en-GB');
            setTimeZone('America/New_York');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('18 June 2000 at 04:30');
        });

        it('should convert the date correctly with timezone America/New_York in zh-CN', async () => {
            setLocale('zh-CN');
            setTimeZone('America/New_York');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('2000年6月18日 04:30');
        });

        it('should skip timezone conversion in zh-CN when requested', async () => {
            setLocale('zh-CN');
            setTimeZone('America/New_York');

            expect(
                date('2000-06-18T08:30:00.000+00:00', {
                    skipTimezoneConversion: true,
                }),
            ).toBe('2000年6月18日 08:30');
        });

        it('should use Asia/Shanghai when the user has no timezone', async () => {
            setLocale('zh-CN');
            setTimeZone(null);

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('2000年6月18日 16:30');
        });
    });

    describe('dateWithUserTimezone', () => {
        const setLocale = (locale) => {
            jest.spyOn(Contena.Application.getContainer('factory').locale, 'getLastKnownLocale').mockImplementation(
                () => locale,
            );
        };
        const setTimeZone = (timeZone) => Contena.Store.get('session').setCurrentUser({ timeZone });

        beforeEach(async () => {
            setLocale('en-GB');
            setTimeZone('UTC');
        });

        it('should convert the date correctly with timezone Pacific/Pago_Pago', async () => {
            setTimeZone('Pacific/Samoa');
            const date = new Date(2000, 1, 1, 11, 13, 37);

            expect(dateWithUserTimezone(date).toString()).toBe(
                'Tue Feb 01 2000 00:13:37 GMT+0000 (Coordinated Universal Time)',
            );
        });

        it('should convert the date with Asia/Shanghai as fallback', async () => {
            setTimeZone(null);
            const date = new Date(2000, 1, 1, 0, 13, 37);

            expect(dateWithUserTimezone(date).toString()).toBe(
                'Tue Feb 01 2000 08:13:37 GMT+0000 (Coordinated Universal Time)',
            );
        });
    });

    describe('toISODate', () => {
        it('formats the date with time', async () => {
            const dateWithTime = new Date(Date.UTC(2021, 0, 1, 13, 37, 0));

            expect(toISODate(dateWithTime)).toBe('2021-01-01T13:37:00.000Z');
        });

        it('formats the date without time', async () => {
            const dateWithoutTime = new Date(Date.UTC(2021, 0, 1, 13, 37, 0));

            expect(toISODate(dateWithoutTime, false)).toBe('2021-01-01');
        });
    });
});
