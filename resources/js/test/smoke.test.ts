describe('test environment', () => {
    it('provides a uuid shaped crypto.randomUUID', () => {
        expect(crypto.randomUUID()).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/,
        );
    });
});
