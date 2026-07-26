<?php

declare(strict_types=1);

namespace App\Shop\Tracking;

/**
 * The internal, provider-agnostic catalogue of things worth tracking on a sales
 * page. Providers map these to their own native event names (see each adapter),
 * so the rest of the app only ever speaks this vocabulary — one event in, every
 * enabled pixel out. Adding a case here is the only change needed to make a new
 * event trackable across ALL providers.
 */
enum TrackingEventType: string
{
    case PageView = 'page_view';
    case ViewContent = 'view_content';
    case CtaClick = 'cta_click';
    case AddToCart = 'add_to_cart';
    case InitiateCheckout = 'initiate_checkout';
    case Purchase = 'purchase';
    case PurchaseFailed = 'purchase_failed';
    case Lead = 'lead';
    case FormSubmitted = 'form_submitted';
    case CouponApplied = 'coupon_applied';
    case UpsellAccepted = 'upsell_accepted';
    case UpsellRejected = 'upsell_rejected';
    case DownsellAccepted = 'downsell_accepted';
    case OrderBumpAdded = 'order_bump_added';
}
