# Sezzle Extension for Magento 1

## Introduction
This document will help you in installing `Sezzle's Magento` extension. This extension is a certified one and listed [here](https://marketplace.magento.com/sezzle-sezzle-sezzlepay.html) in the marketplace. The plugin can also be downloaded from [github](https://github.com/sezzle/sezzle-magento).

### For all purposes assume [Magento] as your root Magento directory.
## How to install the extension?

* Download the .zip or tar.gz file from `Sezzle's` github repository.
* Unzip the file and follow the following instructions.
* Copy all files in the extracted folder's: `/app/code/community/` to: `[MAGENTO]/app/code/community`
* Copy all files in the extracted folder's `/app/design/frontend/base/default/layout/` to: `[MAGENTO]/app/design/frontend/base/default/layout`
* Copy all files in the extracted folder's `/app/design/frontend/base/default/template/` to: `[MAGENTO]/app/design/frontend/base/default/template`
* Copy all files in the extracted folder's: `/app/etc/` to: `[MAGENTO]/app/etc`
* Copy all files in the extracted folder's: `/js` to: `[MAGENTO]/js`
* Login to `Magento` Admin and navigate to `System > Cache Management`.
* Flush the cache storage by selecting `Flush Cache Storage`.

## How to upgrade the extension?

* Download the .zip or tar.gz file from Sezzle's github repository.
* Unzip the file and follow the following instructions.
* Copy the `app` directory from unzipped folder to the `Magento` root.
* Login to `Magento` Admin and navigate to `System > Cache Management`.
* Flush the cache storage by selecting `Flush Cache Storage`.
* Flush the js/css cache.

## Configure Sezzle

* To configure your `Sezzle Gateway` in `Magento` Admin complete the following steps. Prerequisite for this section is to obtain a `Private Key` and `Public Key` from `Sezzle Merchant Dashboard`.
* Go to `System > Configuration > Sales > Payment Methods > Sezzle`
* Configure the plugin as follows:
    * Set `Enabled` to `yes`.
    * Set `Merchant Id`.
    * Set `Api Mode` to either `Sandbox/Test` or `Live`.
    * Set `Payment from Applicable Countries` to `Specific Countries`.
    * Set `Payment from Specific Countries` to `United States` or `Canada`.
    * Set `Add Widget Script in PDP` to `Yes` for adding widget script in the Product Display Page which will help in enabling `Sezzle Widget` Modal in PDP.
    * Set `Add Widget Script in Cart Page` to `Yes` for adding widget script in the Cart Page which will help in enabling `Sezzle Widget` Modal in Cart Page.
    * Set `Private Key` as received from `API Keys` section of `Sezzle Merchant Dashboard`.
    * Set `Public Key` as received from your `API Keys` section of `Sezzle Merchant Dashboard`.
    * Set `Payment Action` as `Authorize only` for doing payment authorization only and `Authorize and Capture` for doing authorization as well as payment capture.
* Save the configuration.
* Navigate to `System > Cache Management`.
* Flush the cache storage by selecting `Flush Cache Storage`.

### Your store is now ready to accept payments through Sezzle.

## Frontend Functonality

* If you have correctly set up `Sezzle`, you will see `Sezzle` as a payment method in the checkout page.
* Select `Sezzle` and move forward.
* Once you click `Place Order`, you will be redirected to `Sezzle Checkout` to complete the checkout and eventually in `Magento` too.

## Capture Payment

* If `Payment Action` is set to `Authorize and Capture`, capture will be performed instantly from the extension after order is created and validated in `Magento`.
* If `Payment Action` is set to `Authorize`, capture needs to be performed manually from the `Magento` admin. Follow the below steps to do so.
    * Go the order and click on `Invoice`.
    * Verify your input in the `Create Invoice` page and click on `Save` to create the invoice.
    * This will automatically capture the payment in `Sezzle`.

## Refund Payment

* Go to `Sales > Orders` in the `Magento` admin.
* Select the order you want to refund.
* Click on `Credit Memo` and verify your input in the `Create Credit Memo` page.
* Save it and the refunded will be initiated in `Sezzle`.
* In `Sezzle Merchant Dashboard`, `Order Status` as `Refunded` means payment has been fully refunded and `Order Status` as `Partially Refunded` means payment has been partially refunded.

## Order Verification in Magento Admin

* Login to `Magento` admin and navigate to `Sales > Orders`.
* Proceed into the corresponding order.
* Sezzle Capture Status as `Captured` OR if `Total Paid` is equals to `Grand Total` means payment is successfully captured by `Sezzle`.
* Sezzle Capture Status as `Not Captured` OR if `Total Paid` is not equals to `Grand Total` means payment is authorized but yet not captured.

## Order Verification in Sezzle Merchant Dashboard

* Login to `Sezzle Merchant Dashboard` and navigate to `Orders`.
* Proceed into the corresponding order.
* Status as `Approved` means payment is successfully captured by `Sezzle`.
* Status as `Authorized`, uncaptured means payment is authorized but yet not captured.

## Troubleshooting/Debugging
* [a Invalid header line detected](Troubleshooting.md#invalid-header-line-detected)
* There is logging enabled by `Sezzle` for tracing the `Sezzle` actions.
* In case merchant is facing issues which is unknown to `Merchant Success` and `Support` team, they can ask for this logs and forward to the `Platform Integrations` team.
* Name of the log should be like `sezzle-pay.log`.Its always recommended to send the `system.log` and `exception.log` for better tracing of issues.
