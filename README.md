# Whitepay Payment Gateway for Magento 2

This is the Whitepay payment gateway plugin for Magento 2. It allows you to accept cryptocurrency payments via Whitepay.

**Note:** This plugin is a fork from the original one with some improvements. However, more improvements are still welcome.

## Installation

You can install the plugin via Composer or by uploading it to `/app/code/`.

### Composer Installation

1. Open your terminal.
2. Run the following command:

   ```sh
   composer require seb-dev/whitepay-magento2
   ```

3. Enable the module:

   ```sh
   php bin/magento module:enable Whitepay_Payment
   ```

4. Run the setup upgrade command:

   ```sh
   php bin/magento setup:upgrade
   ```

5. Clear the cache:

   ```sh
   php bin/magento cache:clean
   php bin/magento cache:flush
   ```

### Manual Installation

1. Download or clone this repository.
2. Upload the contents to `/app/code/Whitepay/Payment`.
3. Enable the module:

   ```sh
   php bin/magento module:enable Whitepay_Payment
   ```

4. Run the setup upgrade command:

   ```sh
   php bin/magento setup:upgrade
   ```

5. Clear the cache:

   ```sh
   php bin/magento cache:clean
   php bin/magento cache:flush
   ```

## Configuration

1. Log in to the Magento Admin.
2. Navigate to `Stores` > `Configuration` > `Sales` > `Payment Methods`.
3. Find the Whitepay section and configure your settings:
   - Enabled: Enable or disable the Whitepay payment gateway.
   - Title: Set the title for the payment method as it appears on the checkout page.
   - Description: Set the description for the payment method as it appears on the checkout page.
   - Slug: Your Whitepay slug.
   - Token: Your Whitepay token.
   - Webhook Token: Your Whitepay webhook token.
   - New Order Status: The status of new orders created with Whitepay.
   - Status After Payment: The status of orders after successful payment.
   - Status After Declined Payment: The status of orders after declined payment.
   - Send Order Confirmation Email: Enable or disable sending order confirmation emails.
   - Minimum Order Amount: Set the minimum order amount to use Whitepay.
   - Maximum Order Amount: Set the maximum order amount to use Whitepay.
   - Allowed Shipping Methods: Select the shipping methods allowed with Whitepay.
   - Sort Order: Set the sort order of the payment method.
   - Debug: Enable or disable debug mode.

4. Save your configuration.

## Features

- Accept cryptocurrency payments via Whitepay.
- Configurable order statuses for new, paid, and declined orders.
- Customizable payment method title and description.
- Debug mode for troubleshooting.

## Support

This plugin is provided as-is without any support. Improvements are always welcome.

## License

This plugin is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.
