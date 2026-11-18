# Elme_ProductCarousel (FE-17)

A titled slider of FE-19 product cards. Server-rendered, swipe on touch, arrows from desktop, nothing printed (title
included) when there are no products.

## Use it

| Where | How |
|---|---|
| Home / Content Studio pages | entry type `Product carousel` (`etc/content_types.xml`) |
| CMS page or block | widget `Product carousel` (`etc/widget.xml`) |
| PDP (FE-30), cart, 404 | a layout block: `Elme\ProductCarousel\Block\Carousel`, template `Elme_ProductCarousel::carousel.phtml`, arguments `source`, `title`, `limit`, `category_id`, `product_id` |

Sources: `trending`, `new`, `category` (needs `category_id`), `related` (needs `product_id`; defaults to the product
of the page).

## Where the products come from

One interface, `Api\ProviderInterface`, bound in `etc/di.xml`. Today `Model\SearchProvider`:

- trending: the products of the category set in Stores > Configuration > Elme > Catalog > Product carousel, in its
  position order (empty setting: hidden);
- new: newest by `news_from_date`, only products the card marks New;
- category: that category's products in position order;
- trending / new / category go through `Elme\Search\Api\ProductSearchInterface`; related is Magento's admin
  "Related Products".

When the Search Service offers recommendations, add a class that implements `ProviderInterface` and change the one
`preference` line. The block and templates do not change.

The look is `web/tailwind/elme/product/product-carousel.css` plus the shared `elme/foundation/snap-carousel.css`.
