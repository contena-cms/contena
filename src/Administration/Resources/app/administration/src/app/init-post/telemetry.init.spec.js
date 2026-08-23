import initTelemetry from './telemetry.init';

describe('src/app/init-post/telemetry.init.ts', () => {
    it('calls Telemetry.init', async () => {
        jest.spyOn(Contena.Telemetry, 'initialize');

        await initTelemetry();

        expect(Contena.Telemetry.initialize).toHaveBeenCalled();
    });
});
