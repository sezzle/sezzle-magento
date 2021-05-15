<div align="center">
    <a href="https://sezzle.com">
        <img src="https://media.sezzle.com/branding/2.0/Sezzle_Logo_FullColor.svg" width="300px" alt="Sezzle" />
    </a>
</div>

# Sezzle Magento 2 Extension Changelog

## Version 4.4.3
_Mon 17 May 2021

### Supported Editions & Versions

Tested and verified in clean installations of Magento 1:

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.7 and later.

### Highlights

- Default widget config.

## Version 4.4.2

_Wed 28 Apr 2021_

### Supported Editions & Versions

Tested and verified in clean installations of Magento 1:

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.7 and later.

### Highlights

- Allow store language settings to determine Sezzle-Checkout language.

## Version 4.4.1

_Tue 24 Sep 2020_

### Supported Editions & Versions

Tested and verified in clean installations of Magento 1:

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.7 and later.

### Highlights

- Aheadworks OneStepCheckout Support.

## Version 4.4.0

_Tue 8 Sep 2020_

### Supported Editions & Versions

Tested and verified in clean installations of Magento 1:

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.7 and later.

### Highlights

- Removed sezzle_capture_expiry, is_captured and is_refunded column from :
    - sales_flat_order_payment
    - sales_flat_quote_payment
    - sales_flat_order
    - sales_flat_quote
- Values of sezzle_capture_expiry, is_captured and is_refunded are now saved in additional_information column of :
    - sales_flat_order_payment
    - sales_flat_quote_payment
- Sezzle Capture Status column removed from Order Grid in Admin.
- Sezzle Capture Expiry and Sezzle Capture Status has been moved to Payment Information section of Order and Invoice Details page in Admin.
- Overriding of invoice save action has been removed.
- Extension should be upgraded once all the pending Sezzle orders are settled.
