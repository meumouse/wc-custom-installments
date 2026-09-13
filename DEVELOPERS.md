# Extending Custom Installments for WooCommerce

Everything below is a supported surface. If you need something that is not here, open an issue
rather than editing the plugin — a patched copy stops receiving updates, and the thing you needed is
usually one filter away.

## Contents

- [The extension helpers](#the-extension-helpers)
- [Registering a gated feature](#registering-a-gated-feature)
- [Adding a settings field](#adding-a-settings-field)
- [Adding a per-product field](#adding-a-per-product-field)
- [Adding a REST route](#adding-a-rest-route)
- [Adding a storefront element](#adding-a-storefront-element)
- [The browser side](#the-browser-side)
- [Hook reference](#hook-reference)
- [Names that cannot change](#names-that-cannot-change)

## The extension helpers

Register on `init` at priority 1, or earlier. The settings schema and the REST catalogue are built
after that, and anything registered later misses them.

```php
add_action( 'init', function() {
    wcci_register_feature( 'my_feature', array(
        'label' => __( 'My feature', 'my-plugin' ),
        'tier'  => 'pro',
    ) );
}, 1 );
```

## Registering a gated feature

A feature is the unit the plugin gates on. Declaring one gets you three behaviours from a single
key: the settings field ships to the browser marked locked, the repository refuses to persist it,
and your own code can ask whether it may run.

```php
use MeuMouse\WC_Custom_Installments\Core\Features;

Features::register( 'my_feature', array(
    'label' => __( 'My feature', 'my-plugin' ),
    'tier'  => 'pro',            // 'free' is always available
) );

if ( Features::is_available( 'my_feature' ) ) {
    // …
}
```

To answer the gate for a `pro` feature — which is what a licensing add-on does:

```php
add_filter( 'WC_Custom_Installments/Features/Is_Available', function( $available, $feature_id ) {
    // Never return false over a true: something else may have granted it.
    return $available ? true : my_plugin_is_licensed();
}, 10, 2 );
```

## Adding a settings field

The schema is sections → cards → fields. Append to any of the three.

```php
add_filter( 'WC_Custom_Installments/Admin/Settings_Schema', function( $schema ) {
    foreach ( $schema as $index => $section ) {
        if ( 'discounts' !== $section['id'] ) {
            continue;
        }

        $schema[ $index ]['cards'][] = array(
            'id'          => 'my-card',
            'title'       => __( 'My settings', 'my-plugin' ),
            'description' => __( 'What these do.', 'my-plugin' ),
            'fields'      => array(
                array(
                    'type'        => 'toggle',
                    'key'         => 'my_setting',
                    'label'       => __( 'Enable the thing', 'my-plugin' ),
                    'description' => __( 'What happens when it is on.', 'my-plugin' ),
                    'default'     => 'no',
                    'feature'     => 'my_feature',   // optional; gates the field
                    'depends_on'  => array( 'enable_all_discount_options' => 'yes' ),
                ),
            ),
        );
    }

    return $schema;
} );

// A field with no default reads as an empty string on a fresh install.
add_filter( 'WC_Custom_Installments/Admin/Default_Options', function( $defaults ) {
    $defaults['my_setting'] = 'no';

    return $defaults;
} );
```

Field types: `toggle`, `text`, `number`, `textarea`, `select`, `color`, `unit`, `placeholder-text`,
`gateway-rates`, `installment-fees`. A type the browser does not know renders as a text input.

Read a setting with `Repository::get( 'my_setting' )`. For a toggle, prefer
`Repository::is_active( 'my_setting' )` — it answers false when the gating feature is unavailable,
so a value stored while licensed cannot keep taking effect after the licence lapses.

## Adding a per-product field

```php
add_filter( 'WC_Custom_Installments/Admin/Product_Fields', function( $fields ) {
    $fields[] = array(
        'key'         => 'my_product_setting',
        'type'        => 'checkbox',      // checkbox | text | number | select
        'label'       => __( 'My option', 'my-plugin' ),
        'description' => __( 'What it does.', 'my-plugin' ),
        'feature'     => '',
        'quick_edit'  => false,           // checkboxes only
    );

    return $fields;
} );
```

Saving is handled for you, gate included. `quick_edit` puts a checkbox in the products list; bulk
edit uses a three-state dropdown there, because an unchecked box and an untouched one look the same
and bulk-clearing every product by accident is not a mistake anyone recovers from quickly.

## Adding a REST route

Extend the base class and the route registers itself — it is discovered like any other class.

```php
namespace My_Plugin\Rest;

use MeuMouse\WC_Custom_Installments\Rest\Abstract_Route;
use WP_REST_Request;

class My_Route extends Abstract_Route {

    protected $route = '/my-route';
    protected $methods = 'POST';

    public function handle( WP_REST_Request $request ) {
        return $this->success_response( array( 'message' => 'Done.' ) );
    }
}
```

The route lands under `wc-custom-installments/v1`, requires `manage_woocommerce` and expects an
`X-WP-Nonce`. Override `permission()` for a different capability, and `should_register()` to keep
the route off sites that cannot use it.

## Adding a storefront element

An element of the price group is data: a selector, an order and its styles.

```php
add_filter( 'WC_Custom_Installments/Frontend/Elements', function( $elements ) {
    $elements['my_element'] = array(
        'id'             => 'wci_my_element',
        'selector'       => '.my-element',
        'settings_title' => __( 'My element', 'my-plugin' ),
        'order'          => 6,
        'icon'           => array( 'class' => 'fa-solid fa-star', 'image' => '' ),
        'styles'         => array( 'mobile' => array(), 'desktop' => array() ),
    );

    return $elements;
} );
```

The stylesheet writer picks it up on its own; missing style keys fall back to the shipped defaults.

## The browser side

The settings screen resolves a field's control through a registry published on the window. A
separately built bundle can add to it:

```js
window.addEventListener('wc-custom-installments:field-registry-ready', (event) => {
  event.detail.register('my-field-type', MyVueComponent);
});

// The event may already have fired when your bundle loads.
if (window.WCCustomInstallmentsFieldComponents?.ready) {
  window.WCCustomInstallmentsFieldComponents.register('my-field-type', MyVueComponent);
}
```

The storefront runtime is exposed as `window.WCCustomInstallments`, with `buildPlan()`,
`formatPrice()`, `updateAmounts()` and `refresh()`. It fires `wc-custom-installments:ready` on the
document, and the 5.x `woo_custom_installments_ready` jQuery event alongside it.

## Hook reference

Every hook below fires under both names when a 5.x name is listed: the current one first, then the
alias, on the same value. There is no deprecation notice — the aliases are supported, not tolerated.

<!-- hooks:start -->

_60 hooks, generated from the source by `npm run docs:hooks`. Do not edit by hand._

| Hook | Kind | 5.x name | Declared in |
|---|---|---|---|
| `WC_Custom_Installments/Admin/Bootstrap_Data` | filter | — | `admin/src/Rest/Settings_Bootstrap.php` |
| `WC_Custom_Installments/Admin/Capability` | filter | — | `admin/src/Core/Helpers.php` |
| `WC_Custom_Installments/Admin/Default_Options` | filter | — | `admin/src/Admin/Default_Options.php` |
| `WC_Custom_Installments/Admin/Onboarding_Bootstrap_Data` | filter | — | `admin/src/Admin/Onboarding/Registry.php` |
| `WC_Custom_Installments/Admin/Product_Fields` | filter | — | `admin/src/Admin/Product_Settings.php` |
| `WC_Custom_Installments/Admin/Settings_Schema` | filter | — | `admin/src/Admin/Settings/Registry.php` |
| `WC_Custom_Installments/Assets/Frontend_Params` | filter | `Woo_Custom_Installments/Assets/Frontend_Params` | `admin/src/Assets/Storefront_Assets.php` |
| `WC_Custom_Installments/Assets/Load_Storefront` | filter | — | `admin/src/Assets/Storefront_Assets.php` |
| `WC_Custom_Installments/Assets/Set_Product_Id` | filter | `Woo_Custom_Installments/Assets/Set_Product_Id` | `admin/src/Assets/Storefront_Assets.php` |
| `WC_Custom_Installments/Cart/Total_Title` | filter | `Woo_Custom_Installments/Cart/Total_Title` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Core/Before_Init` | action | — | `admin/src/Core/Init.php` |
| `WC_Custom_Installments/Core/Init` | action | — | `admin/src/Core/Init.php` |
| `WC_Custom_Installments/Elementor/Inject_Controllers` | filter | `Woo_Custom_Installments/Elementor/Inject_Controllers` | `admin/src/Integrations/Elementor.php` |
| `WC_Custom_Installments/Elementor/Widgets` | filter | — | `admin/src/Integrations/Elementor.php` |
| `WC_Custom_Installments/Elements/Accordion_Footer` | action | `Woo_Custom_Installments/Elements/Accordion_Footer` | `admin/src/Frontend/Components.php` |
| `WC_Custom_Installments/Elements/Accordion_Header` | action | `Woo_Custom_Installments/Elements/Accordion_Header` | `admin/src/Frontend/Components.php` |
| `WC_Custom_Installments/Elements/After_Installments_Container` | action | `Woo_Custom_Installments/Elements/After_Installments_Container` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Elements/Before_Installments_Container` | action | `Woo_Custom_Installments/Elements/Before_Installments_Container` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Elements/Card_Flags` | filter | `Woo_Custom_Installments/Elements/Card_Flags` | `admin/src/Frontend/Components.php` |
| `WC_Custom_Installments/Elements/Modal_Footer` | action | `Woo_Custom_Installments/Elements/Modal_Footer` | `admin/src/Frontend/Components.php` |
| `WC_Custom_Installments/Elements/Modal_Header` | action | `Woo_Custom_Installments/Elements/Modal_Header` | `admin/src/Frontend/Components.php` |
| `WC_Custom_Installments/Features/Flushed` | action | — | `admin/src/Core/Features.php` |
| `WC_Custom_Installments/Features/Is_Available` | filter | — | `admin/src/Core/Features.php` |
| `WC_Custom_Installments/Features/Register` | action | — | `admin/src/Core/Features.php` |
| `WC_Custom_Installments/Frontend/Elements` | filter | — | `admin/src/Frontend/Element_Registry.php` |
| `WC_Custom_Installments/Frontend/Is_Builder_Preview` | filter | — | `admin/src/Core/Helpers.php` |
| `WC_Custom_Installments/Init/Class_Hook` | filter | — | `admin/src/Core/Init.php` |
| `WC_Custom_Installments/Init/Instance_Classes` | filter | — | `admin/src/Core/Init.php` |
| `WC_Custom_Installments/Installments/All_Installments` | filter | `Woo_Custom_Installments/Installments/All_Installments` | `admin/src/Engine/Calculate_Installments.php` |
| `WC_Custom_Installments/Installments/Final_Price` | filter | `Woo_Custom_Installments/Installments/Final_Price` | `admin/src/Engine/Calculate_Values.php` |
| `WC_Custom_Installments/Installments/Get_Fee` | filter | `Woo_Custom_Installments/Installments/Get_Fee` | `admin/src/Core/Helpers.php` |
| `WC_Custom_Installments/Installments/With_Fees` | filter | `Woo_Custom_Installments/Installments/With_Fees` | `admin/src/Engine/Calculate_Values.php` |
| `WC_Custom_Installments/Installments/Without_Fee` | filter | `Woo_Custom_Installments/Installments/Without_Fee` | `admin/src/Engine/Calculate_Values.php` |
| `WC_Custom_Installments/Integrations/Theme_Rules` | filter | — | `admin/src/Integrations/Theme_Compatibility.php` |
| `WC_Custom_Installments/Migrate/Completed` | action | — | `admin/src/Core/Migrator.php` |
| `WC_Custom_Installments/Migrate/Settings` | filter | — | `admin/src/Core/Migrator.php` |
| `WC_Custom_Installments/Onboarding/Allowed_Keys` | filter | — | `admin/src/Admin/Onboarding/Registry.php` |
| `WC_Custom_Installments/Onboarding/Steps` | filter | — | `admin/src/Admin/Onboarding/Registry.php` |
| `WC_Custom_Installments/Payment_Methods/Brands` | filter | — | `admin/src/Admin/Default_Options.php` |
| `WC_Custom_Installments/Payment_Methods/Set_Product` | filter | `Woo_Custom_Installments/Payment_Methods/Set_Product` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Price/After_Group` | action | `Woo_Custom_Installments/Price/After_Group` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Price/Append_Group` | action | `Woo_Custom_Installments/Price/Append_Group` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Price/Before_Group` | action | `Woo_Custom_Installments/Price/Before_Group` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Price/Calculate_Total_Discount` | filter | `Woo_Custom_Installments/Price/Calculate_Total_Discount` | `admin/src/Engine/Calculate_Values.php` |
| `WC_Custom_Installments/Price/Discounted_Price` | filter | `Woo_Custom_Installments/Price/Discounted_Price` | `admin/src/Engine/Calculate_Values.php` |
| `WC_Custom_Installments/Price/Economy_Pix_Price` | filter | `Woo_Custom_Installments/Price/Economy_Pix_Price` | `admin/src/Engine/Calculate_Values.php` |
| `WC_Custom_Installments/Price/Fee_Label` | filter | `Woo_Custom_Installments/Price/Fee_Label` | `admin/src/Engine/Calculate_Installments.php` |
| `WC_Custom_Installments/Price/Group_Classes` | filter | `Woo_Custom_Installments/Price/Group_Classes` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Price/Installment_Info` | filter | `Woo_Custom_Installments/Price/Installment_Info` | `admin/src/Engine/Calculate_Installments.php` |
| `WC_Custom_Installments/Price/Prepend_Group` | action | `Woo_Custom_Installments/Price/Prepend_Group` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Price/Priority` | filter | `Woo_Custom_Installments/Price/Priority` | `admin/src/Frontend/Render_Elements.php` |
| `WC_Custom_Installments/Price/Set_Values_Price` | filter | `Woo_Custom_Installments/Price/Set_Values_Price` | `admin/src/Engine/Calculate_Installments.php` |
| `WC_Custom_Installments/Price/Strings_To_Replace` | filter | `Woo_Custom_Installments/Price/Strings_To_Replace` | `admin/src/Core/Helpers.php` |
| `WC_Custom_Installments/Product/After_Price` | action | `Woo_Custom_Installments/Product/After_Price` | `templates/single-product/price.php` |
| `WC_Custom_Installments/Product/Before_Price` | action | `Woo_Custom_Installments/Product/Before_Price` | `templates/single-product/price.php` |
| `WC_Custom_Installments/Product/Is_Available` | filter | `Woo_Custom_Installments/Product/Is_Available` | `admin/src/Core/Helpers.php` |
| `WC_Custom_Installments/Product/Set_Product` | filter | `Woo_Custom_Installments/Product/Set_Product` | `admin/src/Core/Helpers.php` |
| `WC_Custom_Installments/Settings/Reset` | action | — | `admin/src/Admin/Settings/Repository.php` |
| `WC_Custom_Installments/Settings/Saved` | action | — | `admin/src/Admin/Settings/Repository.php` |
| `WC_Custom_Installments/Styles/Force_Important` | filter | `Woo_Custom_Installments/Styles/Force_Important` | `admin/src/Frontend/Styles.php` |

<!-- hooks:end -->

## Names that cannot change

Some names are stored in places nothing can migrate, so they are fixed for good:

| What | Where it is stored | What a rename does |
|---|---|---|
| The fifteen `woo_custom_installments_*` shortcodes | Post content | Blanks the element on every page using it |
| The nine `wci_*` Elementor widget names | Inside each saved page | Deletes the widget from those pages |
| Product meta keys (`__disable_installments`, `unit_discount_amount`, …) | Product meta, on thousands of products | Silently drops the override |
| Storefront CSS classes (`wci-*`, `woo-custom-installments-*`) | Customer stylesheets | Breaks their styling |
| `window.Woo_Custom_Installments`, `woo_custom_installments_ready` | Customer snippets | Breaks their scripts |
| The single settings option and its keys | The database | Loses the store's configuration |

New names are added alongside the old ones. The old ones stay.
