# Sezzle Pay Extension for Magento 1

## This will help you install Sezzle's magento extension

### For all purposes assume [Magento] as your root Magento directory.

1. Download the .zip or tar.gz file from Sezzle's github repository.
2. Unzip the file and follow the following instructions.
3. Copy all files in the extracted folder's: `/app/code/community/` to: `[MAGENTO]/app/code/community`
4. Copy all files in the extracted folder's `/app/design/frontend/base/default/layout/` to: `[MAGENTO]/app/design/frontend/base/default/layout`
5. Copy all files in the extracted folder's `/app/design/frontend/base/default/template/` to: `[MAGENTO]/app/design/frontend/base/default/template`
6. Copy all files in the extracted folder's: `/app/etc/` to: `[MAGENTO]/app/etc`
7. Copy all files in the extracted folder's: `/js` to: `[MAGENTO]/js`
8. Login to Magento Admin and navigate to System/Cache Management.
9. Flush the cache storage by selecting Flush Cache Storage.

## Admin Configuration

1. To configure your Magento Merchant Credentials in Magento Admin complete the following steps. Prerequisite for this section is to obtain a Private Key and Public Key from `Sezzle Merchant Dashboard`.

2. Go to `System > configuration > Sales > Payment Methods > Sezzle Pay`

3. Configure the plugin as follows:
    * Set `Enabled` to `yes`.
    * Set `Merchant Id`.
    * Set `Api Mode` to either `Sandbox/Test` or `Live`.
    * Set `Payment from Applicable Countries` to `Specific Countries`.
    * Set `Payment from Specific Countries` to `United States`.
    * Set `Private Key` as received from `API Keys` section of `Sezzle Merchant Dashboard`.
    * Set `Public Key` as received from your `API Keys` section of `Sezzle Merchant Dashboard`.

4. Save the configuration.
5. Go to `System > configuration > General > Sezzle Widget`
6. Set the widget display settings and save config.
7. Navigate to System/Cache Management.
8. Flush the cache storage by selecting Flush Cache Storage.

### Your store is now ready to accept payments through Sezzle.

### Updating Sezzle Magento Plugin
The process of upgrading the this plugin involves the complete removal of Sezzle Magento plugin files, followed by copying the new files.
1. Download the .zip or tar.gz file from Sezzle's github repository.
2. Unzip the file and follow the following instructions.
3. Remove all files in: 
[MAGENTO]/app/code/community/Sezzle
4. Copy new files to: 
[MAGENTO]/app/code/community/Sezzle
5. Remove file: 
[MAGENTO]/app/design/frontend/base/default/layout/sezzle_sezzlepay.xml
6. Copy file to: 
[MAGENTO]/app/design/frontend/base/default/layout/sezzle_sezzlepay.xml
7. Remove all files in: 
[MAGENTO]/app/design/frontend/base/default/template/sezzlepay
8. Copy new files to:
[MAGENTO]/app/design/frontend/base/default/template/sezzlepay
9. Remove file: 
[MAGENTO]/app/etc/modules/Sezzle_Sezzlepay.xml
10. Copy file to:
[MAGENTO]/app/etc/modules/Sezzle_Sezzlepay.xml
11. Remove all files in: 
[MAGENTO]/js/sezzle
12. Copy new files to: 
[MAGENTO]/js/sezzle
13. Login to Magento Admin and navigate to System/Cache Management
14. Flush the cache storage by selecting Flush Cache Storage
15. Flush the js/css cache.


## Sezzle documentation and testing
All information about testing can be found in `https://docs.sezzle.com/`

## Troubleshooting
1. [a Invalid header line detected](Troubleshooting.md#invalid-header-line-detected)
