<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Providers;

/**
 * Meta (Facebook) Pixel. Standard events: PageView, ViewContent, AddToCart,
 * InitiateCheckout, Purchase, Lead — everything else is sent via `trackCustom`.
 * https://developers.facebook.com/docs/meta-pixel
 */
final class MetaProvider extends AbstractTrackingProvider
{
    public function key(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta (Facebook) Pixel';
    }

    public function docsUrl(): string
    {
        return 'https://developers.facebook.com/docs/meta-pixel/get-started';
    }

    public function fields(): array
    {
        return [
            $this->field('pixel_id', 'Pixel ID', true, '^\d{15,16}$', 'Your 15–16 digit Meta Pixel ID.'),
            $this->field('access_token', 'Conversions API token (optional)', false, null, 'For server-side events. Leave blank for browser-only.', true),
        ];
    }

    public function headScript(array $config): string
    {
        if (! $this->isConfigured($config)) {
            return '';
        }

        $id = $this->js($this->primaryId($config));

        return <<<HTML
        <!-- Meta Pixel -->
        <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[]};(window.__ppTrackers=window.__ppTrackers||[]).push({key:'meta',init:function(){!function(f,b,e,v,t,s){t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',{$id});},fire:function(type,data){if(typeof fbq!=='function')return;var m={page_view:'PageView',view_content:'ViewContent',add_to_cart:'AddToCart',initiate_checkout:'InitiateCheckout',purchase:'Purchase',lead:'Lead'},p={};if(data.value!=null){p.value=Number(data.value);p.currency=data.currency||'USD';}if(data.product_id!=null){p.content_ids=[String(data.product_id)];p.content_type='product';}if(data.product_name)p.content_name=data.product_name;if(data.quantity!=null)p.num_items=Number(data.quantity);if(data.order_id!=null)p.order_id=String(data.order_id);var o=data.event_id!=null?{eventID:String(data.event_id)}:undefined;if(m[type]){fbq('track',m[type],p,o);}else{fbq('trackCustom',type,data,o);}}});
        </script>
        <!-- End Meta Pixel -->
        HTML;
    }
}
