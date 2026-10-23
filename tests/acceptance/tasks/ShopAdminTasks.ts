import { mergeTests } from '@playwright/test';

/**
 * Media
 */
import { UploadImage } from './ShopAdmin/Blog/UploadImage';

/**
 * Blog
 */
import { GenerateVariants } from './ShopAdmin/Blog/GenerateVariants';

/**
 * First Run Wizard
 */
import { FRWSalesChannelSelectionPossibility } from '@tasks/ShopAdmin/FRW/FRWSalesChannelSelectionPossibility';

/**
 * CustomField
 */
import { CreateCustomField } from '@tasks/ShopAdmin/CustomField/CreateCustomField';

/**
 * Add Landing Page From Category
 */
import { CreateLandingPage } from '@tasks/ShopAdmin/Category/CreateLandingPage';

/**
 * Users
 */
import { UserGroupActivation } from '@tasks/ShopAdmin/Users/UserGroupActivation';

/**
 * Orders
 */
import { AddCreditItem } from '@tasks/ShopAdmin/Orders/AddCreditItemViaAPI';
import { CreateDocument } from '@tasks/ShopAdmin/Orders/CreateDocumentViaAPI';

/**
 * Rules
 */
import { CreateRuleBillingCountry } from '@tasks/ShopAdmin/RuleBuilder/CreateRuleBillingCountry';

/**
 * Flows
 */
import { CreateFlowForValidation } from '@tasks/ShopAdmin/FlowBuilder/CreateFlowForValidation';
import { WaitForFlowValidationSideEffects } from '@tasks/ShopAdmin/FlowBuilder/WaitForFlowValidationSideEffects';

export const test = mergeTests(
    GenerateVariants,
    UploadImage,
    FRWSalesChannelSelectionPossibility,
    CreateCustomField,
    CreateLandingPage,
    UserGroupActivation,
    AddCreditItem,
    CreateDocument,
    CreateRuleBillingCountry,
    CreateFlowForValidation,
    WaitForFlowValidationSideEffects,
);
