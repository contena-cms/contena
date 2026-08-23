describe('src/app/filter/truncate.filter.ts', () => {
    const truncateFilter = Contena.Filter.getByName('truncate');

    it('should contain a filter', () => {
        expect(truncateFilter).toBeDefined();
    });

    it('should return empty string fallback when no value is given', () => {
        expect(truncateFilter()).toBe('');
    });

    [
        [
            [
                'Hello World, welcome to Contenaa.',
                10,
            ],
            'Hello W...',
        ],
        [
            [
                'Hello World, welcome to Contenaa.',
                20,
            ],
            'Hello World, welc...',
        ],
        [
            [
                'Hello World, welcome to <h1>Contena</h1> guys.',
                33,
            ],
            'Hello World, welcome to Sowell...',
        ],
        [
            [
                'Hello World, welcome to <h1>Contena</h1> guys.',
                33,
                true,
                '***',
            ],
            'Hello World, welcome to Sowell***',
        ],
        [
            [
                'Hello World, welcome to <h1>Contena</h1> guys.',
                33,
                false,
            ],
            'Hello World, welcome to <h1>So...',
        ],
        [
            [
                'Hello World, welcome to <h1>Contena</h1> guys.',
                33,
                false,
                '...more',
            ],
            'Hello World, welcome to <h...more',
        ],
        [
            [
                'Hello World.',
                33,
            ],
            'Hello World.',
        ],
    ].forEach(
        ([
            input,
            expected,
        ]) => {
            it(`should return correct result for ${input}`, () => {
                expect(truncateFilter(...input)).toBe(expected);
            });
        },
    );
});
