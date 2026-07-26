<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Providers;

/**
 * TikTok Pixel. Standard events: ViewContent, AddToCart, InitiateCheckout,
 * CompletePayment (purchase), SubmitForm (lead) — plus PageView via ttq.page().
 * https://ads.tiktok.com/help/article/standard-events-parameters
 */
final class TikTokProvider extends AbstractTrackingProvider
{
    public function key(): string
    {
        return 'tiktok';
    }

    public function label(): string
    {
        return 'TikTok Pixel';
    }

    public function docsUrl(): string
    {
        return 'https://ads.tiktok.com/help/article/get-started-pixel';
    }

    public function fields(): array
    {
        return [
            $this->field('pixel_id', 'Pixel ID', true, '^[A-Za-z0-9]{16,30}$', 'The alphanumeric TikTok Pixel ID from Events Manager.'),
        ];
    }

    public function headScript(array $config): string
    {
        if (! $this->isConfigured($config)) {
            return '';
        }

        $id = $this->js($this->primaryId($config));

        return <<<HTML
        <!-- TikTok Pixel -->
        <script>
        (window.__ppTrackers=window.__ppTrackers||[]).push({key:'tiktok',init:function(){!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(e,n){e[n]=function(){e.push([n].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(e){for(var n=ttq._i[e]||[],i=0;i<ttq.methods.length;i++)ttq.setAndDefer(n,ttq.methods[i]);return n};ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=d.createElement("script");o.type="text/javascript";o.async=!0;o.src=i+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load({$id});}(window,document,'ttq');},fire:function(type,data){if(typeof ttq==='undefined')return;var m={view_content:'ViewContent',add_to_cart:'AddToCart',initiate_checkout:'InitiateCheckout',purchase:'CompletePayment',lead:'SubmitForm'},p={};if(data.value!=null){p.value=Number(data.value);p.currency=data.currency||'USD';}if(data.product_id!=null){p.content_id=String(data.product_id);p.content_type='product';}if(data.product_name)p.content_name=data.product_name;if(data.quantity!=null)p.quantity=Number(data.quantity);if(type==='page_view'){ttq.page();}else if(m[type]){ttq.track(m[type],p);}else{ttq.track(type,data);}}});
        </script>
        <!-- End TikTok Pixel -->
        HTML;
    }
}
