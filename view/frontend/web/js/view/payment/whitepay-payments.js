/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,
              rendererList) {
        'use strict';
        rendererList.push(
            {
                type: 'whitepay',
                component: 'Whitepay_Payment/js/view/payment/method-renderer/whitepay-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
