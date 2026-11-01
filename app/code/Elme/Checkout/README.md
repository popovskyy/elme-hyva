# Elme_Checkout

The Elme Living one-page checkout. It fills Hyva's `checkout_index_index` page; Magento's `checkout/index/index`
controller stays, so empty or invalid carts still go to the cart and `Elme_LoginOnly` still sends guests to login.

## How it fits together

- **The server owns the state.** `Api/CheckoutManagementInterface` is the only way to change the checkout, and every
  change returns the full `Api/Data/CheckoutStateInterface`. Business rules live in PHP only.
- **The state** is `{sections: {code: data}, errors: {code: message}}`, built by `Model/StateBuilder` from a pool of
  `Api/SectionProviderInterface`. A provider that throws costs only its own section: its data is `null`, its message
  is in `errors`, the exception is logged.
- **Actions** in `Controller/Action/` are thin JSON POST endpoints at `elme_checkout/action/<name>`, form key required.
  They extend `AbstractAction`, which handles login, empty cart, errors and the JSON answer.
- **Alpine store** `elmeCheckout` (`templates/js/store.phtml`) holds the state. The first state is rendered into the
  page, so the first paint needs no request. `call(action, data, sectionCode)` posts and replaces the whole state.
  On failure it fills `fieldError(field)`, `sectionError(code)` or the top `message`.

## Add a section

A section called `foo`:

1. **Provider**: `Model/Section/FooProvider.php` implements `SectionProviderInterface`, returns JSON-safe data, then
   goes into the pool in `etc/di.xml`:

   ```xml
   <type name="Elme\Checkout\Model\StateBuilder">
       <arguments>
           <argument name="providers" xsi:type="array">
               <item name="foo" xsi:type="array">
                   <item name="sortOrder" xsi:type="number">40</item>
                   <item name="provider" xsi:type="object">Elme\Checkout\Model\Section\FooProvider</item>
               </item>
           </argument>
       </arguments>
   </type>
   ```

2. **Action**, if the customer can change something: add the method to `CheckoutManagementInterface`, then
   `Controller/Action/Foo.php` extends `AbstractAction`, and its `run(int $cartId)` reads `getPayload()` and calls the
   service. See `State.php` for the constructor. Throw `Exception/CheckoutInputException` for bad input: it carries the
   section code and the field errors to the store.

3. **Template**: `templates/section/foo.phtml`, added to the `elme.checkout.sections` container in
   `layout/checkout_index_index.xml`. Replace the placeholder block when the section has one.

4. **Component**: a CSP-safe `Alpine.data` in that template which only reads from the store:

   ```js
   function elmeCheckoutFoo() {
       return {
           foo() {
               return Alpine.store('elmeCheckout').section('foo') || {};
           },
           error() {
               return Alpine.store('elmeCheckout').sectionError('foo');
           },
           isBusy() {
               return Alpine.store('elmeCheckout').isLoading('foo');
           },
           save() {
               Alpine.store('elmeCheckout').call('foo', {value: this.$event.target.value}, 'foo');
           }
       };
   }
   window.addEventListener('alpine:init', () => Alpine.data('elmeCheckoutFoo', elmeCheckoutFoo), {once: true});
   ```

   Follow the `<script>` with `<?php $hyvaCsp->registerInlineScript() ?>`: Magento enforces CSP on this page.

Run `setup:di:compile` where the instance is compiled, then `cache:flush`. New Tailwind classes need `npm run build` in
the theme; this module is already in the `include` list of `hyva.config.json`.

## Add a place-order validator

`Api/PlaceOrderValidatorInterface::validate()` throws when the order must not go through. Throw
`CheckoutInputException` to point the error at a section. Validators run in pool order and the first failure stops the
order, in `CheckoutManagement::placeOrder()`. `$input` holds what the customer sent with the request, like the accepted
agreements. For the page to scroll to a new section, its root element needs `data-checkout-section="<code>"`.

```xml
<type name="Elme\Checkout\Model\PlaceOrderValidatorPool">
    <arguments>
        <argument name="validators" xsi:type="array">
            <item name="minimum_order_value" xsi:type="object">Elme\Checkout\Model\Validator\MinimumOrderValue</item>
        </argument>
    </arguments>
</type>
```

## Add a total row

A new Magento total segment shows up by itself: it keeps its own title and uses the generic row. For an Elme title
add it to `rowTitles` of `Model/Section/TotalsMapper` in `etc/di.xml`. A code in `rowTitles` also needs a row block in
`elme.checkout.summary` in the layout, with the segment code as its alias, or the row renders nothing.

## Addresses

`Model/Address/QuoteAddresses` is the only writer of the quote's addresses; `Model/Section/AddressProvider` reads them.

- **First load:** `getState()` calls `initialize()`: an empty quote gets the default addresses, and a quote address linked
  to a saved address follows edits made in the account. A default with missing details (imported data) is not
  preselected; the customer sees the cards instead.
- **`Plugin/KeepQuoteAddressesPlugin`** is needed because core `Onepage::initCheckout()` puts the default addresses over
  the quote's on every page load. Without it a reload shows the default instead of the address the customer picked.
- **Billing:** the default billing address. Without one it follows the delivery address, and the shipping address's
  `same_as_billing` flag says so. Picking a billing address with Change clears the flag.
- **Address book:** "Save to my address book" saves at once and links the quote address, with `save_in_address_book`
  left at 0 so core does not save it a second time at place order. A customer's first address becomes the default for
  whatever has no default yet, the same rule core has.
- **Validation:** `Model/Address/AddressValidator` runs Magento's validators and sorts their answers per form field. The
  field names are those of `templates/section/address/form.phtml`.
- **The form** is read with `FormData` when sent, so browser autofill needs no bindings. The State select skips the
  theme's custom select (`data-elme-select-skip`): autofill only fills a native one.

Country is fixed to AU: `general/country/allow` in `etc/config.xml`, `QuoteAddresses::COUNTRY_ID` on the address.

## Delivery and payment methods

`Model/Method/QuoteShippingMethod` and `QuotePaymentMethod` are the only writers of the quote's methods;
`Model/Section/DeliveryProvider` and `PaymentProvider` read them.

- **Every state checks the methods again** (`refresh()` in `getState()`): a method that is gone is cleared, a single rate
  or method is chosen for the customer, and a new price of the chosen rate is saved. So an address or cart change needs
  no code of its own. Nothing is saved when nothing changed.
- **A new carrier needs no change here:** rates come from the active Magento carriers. A rate that only carries an error
  (a carrier's "show method if not applicable") is not on offer.
- **Rates go through `collectTotals()`:** the shipping total gives the address the qty and weight carriers charge by, and
  these are not stored on a loaded address.
- **Shipping assignment:** core keeps the method a quote was loaded with in a shipping assignment and applies it again
  when the cart is saved. `QuoteShippingMethod` updates it as well, or a new choice would not stick.
- **Locks:** Delivery needs a delivery address, Payment also needs a delivery method. The server says why in `locked` and
  refuses the action.
- **Place order:** `Model/Validator/DeliveryMethodSelected` and `PaymentMethodSelected` are the first two validators.

Add a payment method with its own fields: a block in `elme.checkout.section.payment` with the method code as its alias.
The template has one root element and runs inside the method loop, with `method` and the `isChecked` and `pick` of
`elmeCheckoutPayment`. Every other method uses the `default` renderer.

```xml
<referenceBlock name="elme.checkout.section.payment">
    <block name="elme.checkout.section.payment.renderer.account_credit"
           as="account_credit"
           template="Elme_Checkout::section/payment/account-credit.phtml"/>
</referenceBlock>
```

Limit a method per customer with a plugin on `Magento\Payment\Model\MethodList::getAvailableMethods()`.

## Place order

`Controller/Action/PlaceOrder` calls `CheckoutManagement::placeOrder()` and answers with the URL of the success page.
Core's `QuoteManagement` sets the checkout session's last-order data, and `etc/frontend/sections.xml` refreshes the mini cart.

- **One order per quote:** the store's `placing` flag stops double clicks in the browser. On the server a lock covers the
  validators and the order, and the quote is read again from the database inside it. Core's own lock is not enough: the
  validators can save the quote, and a save from a request that loaded it earlier would make a placed cart active again.
- **Nothing changes behind the customer's back:** the validators refresh the methods, and a method or delivery price that
  changed refuses the order, so the customer sees the new choice first. The answer carries the new state.
- **Errors:** a `CheckoutInputException` shows in its section, other exceptions in the message at the top. `showError()`
  in the store scrolls there and moves the focus to the first invalid field.
- **Agreements** come from Magento (Stores > Terms and Conditions; Stores > Configuration > Sales > Checkout > Enable
  Terms and Conditions). They are rendered once in `templates/section/place-order.phtml`, not in the state: the texts can
  be long and only the ticks change. Mode "Manually" gets a required checkbox.
- **The bar for phones and tablets** (`templates/place-order-bar.phtml`) goes away while a field that opens a keyboard
  has the focus, so it cannot cover what the customer is typing in.

## Order details

The section holds one field, as on the old site: delivery instructions (optional). There is no order reference or PO
number field because the old Elme checkout has none. `Api/OrderDetailsInterface` holds the name `delivery_instructions`
(max 250): the column on `quote`, `sales_order` and `sales_order_grid`, the extension attribute on cart and order, and the
order email variable. The Pronto order export can read it from the order or its extension attributes.

- **Saving:** `Model/Details/QuoteDetails` is the only writer. The field saves 800 ms after typing stops and on blur. Text
  that is too long is refused with an inline error.
- **Before the order:** `onBeforePlaceOrder()` in the store. Place order sends the last change first, and a refused save
  stops the order. A section that saves while typing uses this too.
- **On the order:** `Observer/CopyDetailsToOrder` copies it, `Model\ResourceModel\Order\Grid` in `etc/di.xml` takes it
  into the grid table.
- **Shown in:** the Account Information table of the admin order view, the top of My Orders order view and its print page,
  the order email through `Observer/AddDetailsToOrderEmail` and the theme's copy of `order_new.html`.
- **Success page:** `ViewModel/OrderDetails` gives the details of the order on the page, or else of the last order placed.

## GST

`Setup/Patch/Data/CreateAuGstTaxRule` creates the AU 10% rate and rule for the default product and customer tax classes
and taxes shipping with the same product class. `etc/config.xml` sets catalog prices ex GST, and the cart, checkout and
order to show prices ex GST with GST as its own line.

## Tests

```
bin/clinotty bash -c "vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Elme/Checkout/Test/Unit"
```

End to end, on the host, against the local site (login, add a product, saved address, place order, success page, My
Orders, email in Mailpit; double click). It creates a test customer and product and places real local orders:

```
cd app/code/Elme/Checkout/Test/Playwright
npm install && npx playwright install chromium
npm test
```

The admin part of the long link test runs only with `ADMIN_PASSWORD` set (and `ADMIN_USER`, default `admin`); without it
that test ends as skipped once its My Orders and email checks have passed.
