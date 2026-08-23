import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import { getPropertyControlType } from '../element-settings.util';

describe('module/ct-experience-studio/util/element-settings.util media collection', () => {
    it('maps media collection properties to collection controls', () => {
        const property: ContentSystemElementTypeProperty = {
            type: 'Contena\\Core\\Content\\Media\\MediaCollection',
            translatable: false,
            enum: null,
            default: null,
            required: true,
            title: 'Media',
            description: 'Gallery media',
            adminUI: {
                component: 'media-collection',
            },
        };

        expect(getPropertyControlType(property)).toBe('media-collection');
    });
});
