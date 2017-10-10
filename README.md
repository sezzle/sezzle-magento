# Magento Extension for Sezzle Pay

## This will help you install Sezzle's magento extension

### For all purposes assume [Magento] as your root Magento directory.

1. Download the .zip or tar.gz file from Sezzle's github repository.
2. Unzip the file and follow the following instructions.
3. Copy all files in the extracted folder's: `/app/etc/modules/` to: `[MAGENTO]/app/etc/modules`
4. Copy all files in the extracted folder's: `/app/design/frontend/base/default/template/` to: `[MAGENTO]/app/design/frontend/base/default/template`
5. Copy all files in the extracted folder's: `/app/design/frontend/base/default/layout/` to: `[MAGENTO]/app/design/frontend/base/default/layout`
6. Copy all files in the extracted folder's: `/app/code/community/Sezzle` to: `[MAGENTO]/app/code/community`
7. Copy all files in the extracted folder's: `/js` to: `[MAGENTO]/js`
8. Login to Magento Admin and navigate to System/Cache Management.
9. Flush the cache storage by selecting Flush Cache Storage.

## Admin Configuration

1. To configure your Magento Merchant Credentials in Magento Admin complete the following steps. Prerequisite for this section is to obtain a Private Key and Public Key from `Sezzle Merchant Dashboard`.

2. Go to `System > configuration > Sales > Payment Methods > Sezzle Checkout`

3. Configure the plugin as follows:
    * Set `Enabled` to `yes`.
    * Set `Payment from Applicable Countries` to `Specific Countries`.
    * Set `Payment from Specific Countries` to `United States`.
    * Set `Private Key` as received from `API Keys` section of `Sezzle Merchant Dashboard`.
    * Set `Public Key` as received from your `API Keys` section of `Sezzle Merchant Dashboard`.
    * Set `Sezzle Base URL` to `https://magento.sezzle.com/v1`

4. Save the configuration.
5. Go to `System > configuration > Sales > Sezzle Pay > Product Page Widget Display Settings`
6. Set the widget display settings and save config.
7. Navigate to System/Cache Management.
8. Flush the cache storage by selecting Flush Cache Storage.

### Your store is now ready to accept payments through Sezzle.