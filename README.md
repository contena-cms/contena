<div align="center">

[![Nightly Build Status](https://github.com/contena-cms/contena/actions/workflows/nightly.yml/badge.svg?event=schedule&branch=trunk)](https://github.com/contena-cms/contena/actions/workflows/nightly.yml?query=event%3Aschedule+branch%3Atrunk)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/contena-cms/contena/badges/quality-score.png)](https://scrutinizer-ci.com/g/contena-cms/contena/)
[![Latest Stable Version](https://poser.pugx.org/contena-cms/contena/v/stable)](https://packagist.org/packages/contena-cms/contena)
[![Total Downloads](https://poser.pugx.org/contena-cms/contena/downloads)](https://packagist.org/packages/contena-cms/contena)
[![Crowdin](https://badges.crowdin.net/contena6/localized.svg)](https://translate.contena.cn/project/contena6)
[![License](https://img.shields.io/github/license/contena-cms/contena.svg)](https://github.com/contena-cms/contena/blob/trunk/LICENSE)
[![GitHub closed pull requests](https://img.shields.io/github/issues-pr-closed/contena-cms/contena.svg)](https://github.com/contena-cms/contena/pulls)
[![Discord](https://img.shields.io/badge/chat-on%20discord-%23ECB22E)](https://chat.contena.cn/?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

</div>


<p align="center">
    <a href="https://contena.cn" target="_blank" rel="noopener noreferrer">
        <img width="250" src="https://images.ctfassets.net/nqzs8zsepqpi/34zKqvPxTYtsQppJpgC9It/3b6901d9ba7082d5b4081d7171b268bf/composable-customer-experience-illustration.png" alt="Contena logo surrounded with images of people and screenshots of Contena">
    </a>
</p>

<h1 align="center">Contena</h1>

<p align="center"><strong>Modern open source e-Commerce</strong>

Contena 6 is an open headless commerce platform powered by [Symfony 7](https://symfony.com) and [Vue.js 3](https://vuejs.org) that is used by thousands of shops and supported by a huge, [worldwide community](https://chat.contena.cn/) of developers, agencies, and merchants.

If you like Contena 6, give us a&nbsp;⭐️ &nbsp;on GitHub!

* 🙋‍♂️ &nbsp;[Be part of contena!](https://www.contena.cn/en/jobs/) ‍&nbsp;We are hiring!  🙋
* 🌎 &nbsp;Discover our [website](https://www.contena.cn/en/)
* 🧩 &nbsp;Browse more than [3,100 extensions](https://store.contena.cn/en/) in our community store
* 📖 &nbsp;Learn how to [develop extensions](https://developer.contena.cn) and everything else about the tech behind Contena via our developer docs.
* 🉐 &nbsp;[Translate](https://translate.contena.cn) Contena or help by contributing to existing languages
* 🛠 &nbsp;[Report bugs](https://github.com/contena-cms/contena/issues) in our issue tracker
* 💡 &nbsp;Give us [feedback](https://feedback.contena.cn/) or vote existing ideas
* 👪 &nbsp;Exchange with other Contena developers in our own [Community Hub](https://hub.contena.cn/) or the [Discord community](https://chat.contena.cn/)
* 🗨 &nbsp;Help and get helped on [Stack Overflow](https://stackoverflow.com/questions/tagged/contena6?tab=Newest) or in our [Community forum](https://forum.contena.cn/)

## Table of contents

- [Table of contents](#table-of-contents)
- [Project overview](#project-overview)
  - [Platform and Framework](#platform-and-framework)
- [Installation](#installation)
  - [Production setup](#production-setup)
  - [Code Contribution](#code-contribution)
    - [Contribution setup](#contribution-setup)
- [The Contena CLA](#the-contena-cla)
- [Authors \& Contributors](#authors--contributors)
- [License](#license)
- [Bugs \& Feedback](#bugs--feedback)
- [Reporting security issues](#reporting-security-issues)
- [Extending Contena](#extending-contena)

## Project overview

To discover the features of Contena and what sets us apart from other content management systems, take the [feature tour](https://www.contena.cn/en/products/product-tour/) on the Contena home page.

From a developer's perspective, here are some highlights that make Contena easy and fun to work with:

### Platform and Framework

Contena is primarily based on [Symfony](https://symfony.com/what-is-symfony) and [Vue.js](https://vuejs.org/).
It is a fully functional content platform, but it also serves as a **content management framework**.

Contena is:

- a ready-to-use [content management system](https://docs.contena.cn/en/contena-6-en/getting-started).
- a vendor dependency in your [flex project](https://developer.contena.cn/docs/guides/installation/template).
- [API-first](https://developer.contena.cn/docs/guides/integrations-api).
- [extensible through plugins](https://developer.contena.cn/docs/guides/plugins/plugins/plugin-base-guide):
  - Harness the full power of Symfony by creating bundles and loading them as part of the application.
- [extensible through apps](https://developer.contena.cn/docs/guides/plugins/apps/app-base-guide):
  - A modern, lightweight but powerful way to add functionality, requiring very little Contena-specific knowledge.
- headless if you need it to be.

## Installation

### Production setup

The easiest way to run a Contena site is by booking a commercial plan in the [Contena cloud](https://www.contena.cn/en/contena-cloud/), a fully managed setup, ready to use.

The recommended way for on-premise sites is to install Contena [through the flex template](https://developer.contena.cn/docs/guides/installation/template).
To unlock the full potential Contena has to offer, [commercial plans](https://www.contena.cn/en/pricing/) are also available for on-premise.   
These plans enrich your site with unique functionality, giving you an additional advantage over your competition.

There is a list of [hosting partners](https://www.contena.cn/en/partner/hosting/), who offer a pre-installed site, making your start a lot faster.

We also provide a [web-based installer](https://www.contena.cn/en/download/), [installation instructions](https://developer.contena.cn/docs/guides/installation/) on docs, and the [course](https://hub.contena.cn/learn/course/contena-setup) walks you through the necessary steps.

### Code Contribution

If you have decided to contribute code to Contena and become a member of the Contena community,
We appreciate your hard work and want to handle it with much respect.
To ensure the quality of our code and our products, we have created a guideline that we all should endorse.
It helps us collaborate with you.
Following these guidelines will help us integrate your changes into our daily workflow effectively.

Read more in [our contribution guideline](https://docs.contena.cn/en/contena-platform-dev-en/contribution/contribution-guideline) on how to contribute code.

#### Contribution setup

For Contributing setup see [CONTRIBUTING.md](CONTRIBUTING.md). If you want to run Contena locally for extension/theme/project development, check out the [development environment setup](https://developer.contena.cn/docs/guides/installation/setups/docker.html).

## The Contena CLA

When submitting your code to Contena, you are required to sign our CLA (Contributor License Agreement) automatically.
This CLA ensures that Contena will stay an open and living product.
In short, you give the explicit right to use your code in Contena to Contena.

## Authors & Contributors

Contena is built with the help of our community.

You can find an overview of everyone who contributed to the platform repository in the [official GitHub overview](https://github.com/contena-cms/contena/graphs/contributors).
Additionally, numerous people contribute to the ecosystem through activities unrelated to the codebase.
Thank you all for being part of this!

## License

Contena 6 is completely free and released under the [MIT License](LICENSE).

## Bugs & Feedback

No software is perfect, and Contena is no exception.
Should you spot a bug, please report it in our [issue tracker](https://github.com/contena-cms/contena/issues).

If you want to suggest features or how certain parts of Contena 6 work, we'd be happy to [hear from you](https://feedback.contena.cn/).

## Reporting security issues

Please review our [security policy](SECURITY.md).

### Extending Contena

There are already a lot of extensions available in the [Contena store](https://store.contena.cn/).

After setting up [Contena locally for development](https://developer.contena.cn/docs/guides/installation), you can start with our extension guides in the documentation.

The preferred way of extending Contena is through the [App System](https://developer.contena.cn/docs/guides/plugins/apps/app-base-guide).
If the feature you want to implement needs direct access to the Contena process and the database, you can also use the [plugin system](https://developer.contena.cn/docs/guides/plugins/plugins/plugin-base-guide).    
You can find an [overview and differentiation in the documentation](https://developer.contena.cn/docs/concepts/extensions).

### Privacy

Find out more about [privacy and data protection](https://www.contena.cn/en/privacy/website/).
