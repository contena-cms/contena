import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const mainSource = readFileSync(resolve(__dirname, 'main.ts'), 'utf8');
const repositorySource = readFileSync(resolve(__dirname, 'init/repository.init.ts'), 'utf8');

describe('Contena core Administration services', () => {
    it('registers the generic App extension services', () => {
        expect(mainSource).toContain("addServiceProvider('appAclService'");
        expect(mainSource).toContain("addServiceProvider('appCmsService'");
        expect(mainSource).toContain("addServiceProvider('customEntityDefinitionService'");
    });

    it('hydrates App-declared custom entity definitions from the entity schema', () => {
        expect(repositorySource).toContain("key.startsWith('custom_entity_')");
        expect(repositorySource).toContain("key.startsWith('ce_')");
        expect(repositorySource).toContain('customEntityDefinitionService.addDefinition(value');
    });

    it('keeps Commerce-only administration services out of the generic shell', () => {
        expect(mainSource).not.toContain("addServiceProvider('productTypeService'");
        expect(mainSource).not.toContain("addServiceProvider('shopwareDiscountCampaignService'");
        expect(mainSource).not.toContain("addServiceProvider('licenseViolationService'");
    });
});
