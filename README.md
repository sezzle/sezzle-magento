# Sezzle Extension for Magento 1

## Introduction
This document will help you in installing Sezzle's Magento extension. This extension is a certified one and listed [here](https://marketplace.magento.com/sezzle-sezzle-sezzlepay.html) in the marketplace.

## Installation
### For all purposes assume [Magento] as your root Magento directory.

* Download the .zip or tar.gz file from Sezzle's github repository.
* Unzip the file and follow the following instructions.
* Copy all files in the extracted folder's: `/app/code/community/` to: `[MAGENTO]/app/code/community`
* Copy all files in the extracted folder's `/app/design/frontend/base/default/layout/` to: `[MAGENTO]/app/design/frontend/base/default/layout`
* Copy all files in the extracted folder's `/app/design/frontend/base/default/template/` to: `[MAGENTO]/app/design/frontend/base/default/template`
* Copy all files in the extracted folder's: `/app/etc/` to: `[MAGENTO]/app/etc`
* Copy all files in the extracted folder's: `/js` to: `[MAGENTO]/js`
* Login to Magento Admin and navigate to System/Cache Management.
* Flush the cache storage by selecting Flush Cache Storage.

## Admin Configuration

* To configure your Sezzle Gateway in Magento Admin complete the following steps. Prerequisite for this section is to obtain a Private Key and Public Key from `Sezzle Merchant Dashboard`.

* Go to `System > configuration > Sales > Payment Methods > Sezzle Pay`

* Configure the plugin as follows:
    * Set `Enabled` to `yes`.
    * Set `Merchant Id`.
    * Set `Api Mode` to either `Sandbox/Test` or `Live`.
    * Set `Payment from Applicable Countries` to `Specific Countries`.
    * Set `Payment from Specific Countries` to `United States` or `Canada`.
    * Set `Private Key` as received from `API Keys` section of `Sezzle Merchant Dashboard`.
    * Set `Public Key` as received from your `API Keys` section of `Sezzle Merchant Dashboard`.
    * Set `Payment Action` as `Authorize only` for doing payment authorization only and           `Authorize and Capture` for doing authorization as well as payment capture.

* Save the configuration.
* Go to `System > configuration > General > Sezzle Widget`
* Set the widget display settings and save config.
* Navigate to `System > Cache Management`.
* Flush the cache storage by selecting `Flush Cache Storage`.

### Your store is now ready to accept payments through Sezzle.

### Updating Sezzle Magento Extension
The process of upgrading this extension involves the complete removal of `Sezzle` `Magento` extension files, followed by copying the new files.
* Download the .zip or tar.gz file from Sezzle's github repository.
* Unzip the file and follow the following instructions.
* 
* Login to `Magento` Admin and navigate to `System > Cache Management`.
* Flush the cache storage by selecting `Flush Cache Storage`.
* Flush the js/css cache.

## Troubleshooting
* [a Invalid header line detected](Troubleshooting.md#invalid-header-line-detected)
