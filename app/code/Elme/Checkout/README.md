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
order, in `CheckoutManagement::placeOrder()`.

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

## GST

`Setup/Patch/Data/CreateAuGstTaxRule` creates the AU 10% rate and rule for the default product and customer tax classes
and taxes shipping with the same product class. `etc/config.xml` sets catalog prices ex GST, and the cart, checkout and
order to show prices ex GST with GST as its own line.

## Tests

```
bin/clinotty bash -c "vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Elme/Checkout/Test/Unit"
```
