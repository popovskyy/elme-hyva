<?php

declare(strict_types=1);

/**
 * Production FAQ copy for FE-47 seed (Content Studio type faq).
 *
 * @return list<array{topic: string, question: string, anchor: string, answer: string, next_step?: string}>
 */
return [
    [
        'topic' => 'Location',
        'question' => 'Where can I see Elme products?',
        'anchor' => 'where-can-i-see-elme-products',
        'answer' => '<p>Elme’s state of the art, stunning new showroom is conveniently located at 1 Apollo court Blackburn Victoria 3130. Blackburn is an eastern suburb of Melbourne. Approximately 25kms from the Melbourne CBD.</p>
<p>Please phone 03 7067 9725 or email <a href="mailto:sales@elmeliving.com">sales@elmeliving.com</a> to make an appointment to see the full range.</p>
<p>Alternatively, our Account Managers can come to you in store and assist you online or over the phone.</p>
<p>Please <a href="/contact-us">contact us</a> for bookings.</p>',
    ],
    [
        'topic' => 'Location',
        'question' => 'What are your opening hours?',
        'anchor' => 'what-are-your-opening-hours',
        'answer' => '<p>Our showroom and head office are open Monday to Friday 9am-5pm</p>
<p>Showroom viewings are preferably by appointment.</p>
<p>Please phone <strong>03 7067 9725</strong> or email <a href="mailto:sales@elmeliving.com">sales@elmeliving.com</a></p>
<p>If an appointment is required outside of normal hours, please <a href="/contact-us">contact us</a>, and our friendly team will do their best to accommodate your request.</p>',
    ],
    [
        'topic' => 'Orders',
        'question' => 'How can I set up an account?',
        'anchor' => 'how-can-i-set-up-an-account',
        'answer' => '<p>Please complete the <a href="/customer/account/create">Commercial Account Application Form</a>.</p>
<p>Alternatively, you can pay upfront on a proforma invoice.</p>',
        'next_step' => 'become_stockist',
    ],
    [
        'topic' => 'Orders',
        'question' => 'How do I place an order?',
        'anchor' => 'how-do-i-place-an-order',
        'answer' => '<p><strong>Website</strong><br>Please establish an account to gain access to our website and convenient online ordering system. If you would like assistance navigating the site or require further product information, please <a href="/contact-us">contact us</a> and our friendly staff will be happy to assist you.</p>
<p><strong>Showroom</strong><br>View our entire range at our brand new, state of the art showroom conveniently located in Blackburn, Victoria less than 25km from Melbourne’s CBD.</p>
<p><strong>Account Managers</strong><br>Our friendly and well-informed Accounts Managers operate throughout Australia. Covering both metropolitan and regional areas, please <a href="/contact-us">contact us</a> to make an appointment with them.</p>
<p><strong>Phone</strong><br>Please phone <strong>03 7067 9725</strong> for sales assistance. One of our friendly team will be able to provide specialist and individual advice to assist you in selecting the most suitable products. Our staff have detailed product knowledge and will be able to help you through the ordering process and offer you range, and style advise customized for your needs.</p>
<p><strong>Email</strong><br>Please email orders to <a href="mailto:orders@elmeliving.com.au">orders@elmeliving.com.au</a>.</p>',
        'next_step' => 'quick_order',
    ],
    [
        'topic' => 'Orders',
        'question' => 'Do you have a minimum order?',
        'anchor' => 'do-you-have-a-minimum-order',
        'answer' => '<p>Depending on your location, our minimum order is either $500 plus GST or $1000 plus GST. Please <a href="/contact-us">contact us</a> for a quote.</p>
<p>Each item also comes in a minimum pack size. This number varies depending on the size and value of the item. It can range from 1-24 units. We have done our best to make sure the pack sizes are reasonable and by keeping our products in their original packaging, they are well protected during transit.</p>',
        'next_step' => 'contact',
    ],
    [
        'topic' => 'Orders',
        'question' => 'Can I make changes to or cancel my order?',
        'anchor' => 'can-i-make-changes-to-or-cancel-my-order',
        'answer' => '<p>If you would like to add to or amend an order, please get in touch with customer service on <strong>03 7067 9725</strong>. If the order has not been picked, packed or dispatched we will do our best to assist you with the required changes. Orders are unfortunately unable to be cancelled but once again if there are extenuating circumstances please get in touch and we will do our best to help you.</p>',
    ],
    [
        'topic' => 'Orders',
        'question' => 'How do you manage backorders?',
        'anchor' => 'how-do-you-manage-backorders',
        'answer' => '<p>Backordered items are automatically sent out without notification if it is within 4 months of the original order being placed. Invoices indicate the due date of any backordered items, and you may contact us in writing if you wish to cancel any backordered items. Freight is calculated based on the location and volume of the order. We do our best to ensure competitive freight rates. Backordered items are not exempt from freight. If a backorder is returned it is the customer’s responsibility to cover the freight costs. Backorders will attract a restocking fee of 20% of the item(s) cost.</p>',
    ],
    [
        'topic' => 'Orders',
        'question' => 'Can I return a product?',
        'anchor' => 'can-i-return-a-product',
        'answer' => '<p>At Elme we manage returns and refunds in accordance with Australian Consumer Law.</p>
<p>Unfortunately, we are unable to offer refunds on change of mind purchases. If you have a valid reason for a return (damaged, faulty or incorrectly supplied) please contact our <a href="/contact-us">customer service team</a> within 7 days of receipt of goods for assistance and authorisation.</p>
<p>Please note, unfortunately no credits, returns or exchanges are offered on sale items.</p>
<p>Please ensure all returned goods are in original packaging, complete with Elme labels and barcodes.</p>',
        'next_step' => 'shipping_returns',
    ],
    [
        'topic' => 'Payments',
        'question' => 'What types of payment do you accept?',
        'anchor' => 'what-types-of-payment-do-you-accept',
        'answer' => '<p>We accept Visa, Mastercard and American Express.</p>
<p>There is a 1% surcharge for credit card payments using Visa or Mastercard, and 2.19% for payments using American Express.</p>
<p>Alternatively, you can pay via direct deposit. All payment details are on the bottom of your invoice.</p>
<p>Please <a href="/contact-us">contact us</a> for any assistance with accounts.</p>',
    ],
    [
        'topic' => 'Payments',
        'question' => 'Is it safe to pay online, are my details secure?',
        'anchor' => 'is-it-safe-to-pay-online-are-my-details-secure',
        'answer' => '<p>We do not have access to your personal information. Rest assured it is a safe and secure way to process your payment.</p>
<p>This website uses the Braintree Payment Gateway for its online credit card transactions. Braintree processes online credit card transactions for Australian merchants, providing a safe and secure means of collecting payments via the Internet.All online credit card transactions performed on this site using the Braintree gatBraintree are secured payments.</p>
<ul><li>Payments are full automated with an immediate response.</li><li>Your credit card number can not be viewed by Elme Living or any outside party.</li><li>All transactions are performed under 128 Bit SSL Certificate.</li><li>All transaction data is encrypted for storage within Braintree\'s bank-grade data centre, further protecting your credit card data.</li><li>Braintree is an authorised third party processor for all the major Australian banks.</li><li>Braintree at no time touches your funds, all monies are directly transferred from your credit card to the merchant account held by Elme Living. </li></ul>
<p>For more information about Braintree and online credit card payments visit <a href="http://www.braintreepayments.com/">www.braintreepayments.com</a>.</p>',
    ],
    [
        'topic' => 'Shipping',
        'question' => 'What are your freight charges?',
        'anchor' => 'what-are-your-freight-charges',
        'answer' => '<p>We are constantly comparing and negotiating with different companies to make sure we offer the most competitive freight rates. Freight is charged based on your delivery region within Australia and will either be charged as a percentage of your order or as per our carriers’ freight charges. The rate varies depending on your location and the volume of your order. Please <a href="/contact-us">contact us</a> for a quote.</p>',
        'next_step' => 'shipping_returns',
    ],
    [
        'topic' => 'Shipping',
        'question' => 'Do you ship internationally?',
        'anchor' => 'do-you-ship-internationally',
        'answer' => '<p>Yes, we do ship internationally. Please <a href="/contact-us">contact us</a> so that we can assist you with your order and provide a quote on international freight. Please note our trade pricing on the website is in AUD.</p>',
    ],
    [
        'topic' => 'Shipping',
        'question' => 'Can you ship to a residential address?',
        'anchor' => 'can-you-ship-to-a-residential-address',
        'answer' => '<p>For security reasons we recommend you provide a delivery address where someone is present to receive your goods during business hours. Preferably a business address.</p>
<p>If you wish for your order to be delivered to a residential address, please note that couriers cannot provide the exact date and time of delivery. If you have given authority for the goods to be ‘left with nobody home’ it is at your risk. Unfortunately, Elme cannot accept claims for lost or stolen orders when delivered to a residential address.</p>',
    ],
    [
        'topic' => 'Shipping',
        'question' => 'What if there is a problem with my order?',
        'anchor' => 'what-if-there-is-a-problem-with-my-order',
        'answer' => '<p>We strive to offer prompt delivery of our quality items. If you have any concerns or if any product arrives damaged, please ensure you get in touch with us within 7 days of order receipt so we can assist you. Please contact <a href="mailto:claims@elmeliving.com">claims@elmeliving.com</a> or phone <strong>03 7067 9725</strong>.</p>',
        'next_step' => 'contact',
    ],
    [
        'topic' => 'Shipping',
        'question' => 'Dispatch and delivery',
        'anchor' => 'dispatch-and-delivery',
        'answer' => '<p>We aim to dispatch orders as soon as possible. During peak periods there may be some delay.</p>
<p>It is assumed that the delivery address provided is a business address. Deliveries to business addresses require a signature upon receipt. Please note that if there is nobody available to sign for the goods, a calling card is left, and further freight charges can apply.</p>
<p>If there is nobody home and you have given ‘authority to leave’ the goods at a residential address has been given, it is at your risk. Elme will take no responsibility for loss, theft or damage to the goods. If the courier does not leave the goods and instead leaves a calling card you will need to make delivery arrangements with the freight company and further charges can apply.</p>
<p>When receiving your delivery please ensure that before you sign for the goods you double check that all cartons listed on the proof of delivery form are present and accounted for. Elme cannot take responsibility for missing items if they have been signed for.</p>',
    ],
    [
        'topic' => 'Shipping',
        'question' => 'Delivery Times',
        'anchor' => 'delivery-times',
        'answer' => '<p>Delivery times vary depending on distance, location and peak times. Most deliveries to metropolitan areas take 2-4 business days. Please make sure you let us know if your order is urgent or needs to arrive by a certain date and we will prioritise to get your order dispatched as soon as possible.</p>',
    ],
    [
        'topic' => 'Shipping',
        'question' => 'Freight',
        'anchor' => 'freight',
        'answer' => '<p>We are constantly comparing and negotiating with different companies to make sure we offer the most competitive freight rates. Freight is charged based on your delivery region within Australia and will either be charged as a percentage of your order or as per our carriers’ freight charges. The rate varies depending on your location and the volume of your order. For customers with an account, the freight is charged and invoiced at the time the goods are dispatched. For a pro forma customer the freight is quoted prior to dispatch and must be prepaid with the order. Please <a href="/contact-us">contact us</a> for a quote.</p>
<p>Customers also have the option to organize their own nominated courier to collect their order from our Tarneit distribution warehouse, located at 17 Droomer Way Tarneit VIC 3029. Please advise your salesperson of this requirement when placing your order or you can record this information in the delivery notes section of your online order. Please note that once the order has been collected by your nominated courier, the risk of loss or damage to the goods passes to you or your nominated carrier. Elme Living are not responsible for the goods whilst in transit.</p>',
        'next_step' => 'shipping_returns',
    ],
    [
        'topic' => 'Shipping',
        'question' => 'Can I track my order?',
        'anchor' => 'can-i-track-my-order',
        'answer' => '<p>Yes! Once your order has been released by our warehouse and booked with our carriers, you will receive an email or SMS with tracking details for your order. It will also include a number to contact should you wish to call and follow up any queries.</p>',
    ],
    [
        'topic' => 'Marketing',
        'question' => 'Would you consider loaning Elme product for media and marketing opportunities?',
        'anchor' => 'would-you-consider-loaning-elme-product-for-media-and-marketing-opportunities',
        'answer' => '<p>Please <a href="/contact-us">contact us</a> to discuss. Where possible we will try to support our customers and industry associates with product loans for specific photography shoots, events, and marketing campaigns.</p>',
        'next_step' => 'contact',
    ],
];
