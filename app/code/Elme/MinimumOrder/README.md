# Elme_MinimumOrder

Per-customer minimum order value (MOV). Cart, Quick Order, mini-cart progress and checkout all read the
same numbers from one service.

## Where the threshold lives

- **Customer attribute** `minimum_order_value` (admin: Customers > Edit customer). Decimal, optional.
- **Empty attribute** falls back to Stores > Configuration > Sales > Minimum Order Amount
  (`sales/minimum_order/amount`). This module turns the check on and sets the store default to 500,
  with tax excluded (`tax_including` = 0) so the compare is ex GST.

## Reading the status

`Model\MinimumOrderValue` is also a view model. Templates never read the attribute or config themselves:

```php
$mov = $viewModels->require(\Elme\MinimumOrder\Model\MinimumOrderValue::class);
$status = $mov->getStatus();
// or for a known subtotal (Quick Order cart_list totals):
$status = $mov->evaluate($subtotalExGst, $customer);
```

| Key | Meaning |
| --- | --- |
| `enabled` | `sales/minimum_order/active` |
| `minimum` | threshold for this customer |
| `subtotal` | current subtotal ex GST |
| `remaining` | how much more is needed (0 when met) |
| `met` | true when the order may be placed |
| `*_formatted` | store currency strings for the same numbers |

Customer section data `minimum_order` returns the same keys for the logged-in quote (mini-cart progress).

## Enforcement

`Model\Validator\MinimumOrderValue` is registered on `Elme\Checkout\Model\PlaceOrderValidatorPool`. Below the
minimum it throws `CheckoutInputException` with `Add %1 more to place your order.` using the remaining
amount. Magento's own address minimum check is not the Elme place-order path; this validator is.

## Tests

```
bin/clinotty bash -c "vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Elme/MinimumOrder/Test/Unit"
```
